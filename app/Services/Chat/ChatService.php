<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Services\Chat\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        private ClaudeClient $claude,
        private ClientContextService $context,
        private SystemPromptBuilder $prompt,
        private ToolRegistry $tools,
    ) {}

    /** First turn: no real user message; the model is asked to greet and raise expiring products. */
    public function open(Conversation $conversation, ?\Closure $onChunk = null): string
    {
        return $this->reply($conversation, '[conversation_start]', hidden: true, onChunk: $onChunk);
    }

    /** @param \Closure|null $onChunk when given, the reply is streamed and each text delta is passed to it as it arrives. */
    public function reply(Conversation $conversation, string $userMessage, bool $hidden = false, ?\Closure $onChunk = null): string
    {
        $user     = $conversation->user;
        $system   = $user
            ? $this->prompt->build($this->context->snapshot($user))
            : $this->prompt->buildGuest();

        if (! $hidden) {
            $conversation->messages()->create(['role' => 'user', 'content' => $userMessage]);
        }

        $messages = $this->history($conversation);
        if ($hidden) {
            $messages[] = ['role' => 'user', 'content' => $userMessage];
        }

        $tools  = $this->tools->definitions(guest: ! $user);
        $rounds = 0;
        $textParts = [];
        while (true) {
            $response = $onChunk
                ? $this->claude->stream($system, $messages, $tools, $onChunk)
                : $this->claude->messages($system, $messages, $tools);

            // Append the assistant turn exactly as returned (text + tool_use blocks).
            $messages[] = ['role' => 'assistant', 'content' => $response['content']];
            $roundText = collect($response['content'])->where('type', 'text')->pluck('text')->implode("\n");
            if ($roundText !== '') {
                $textParts[] = $roundText;
            }

            if ($response['stop_reason'] !== 'tool_use' || ++$rounds > config('chatbot.max_tool_rounds')) {
                break;
            }

            $results = [];
            foreach ($response['content'] as $block) {
                if ($block['type'] !== 'tool_use') {
                    continue;
                }
                Log::info('chatbot.tool_call', ['tool' => $block['name'], 'input' => $block['input'], 'user' => $user?->id]);

                // Guest conversations only receive public, non-account tools.
                $output = $this->tools->execute($block['name'], $block['input'], $user);

                $results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => is_string($output) ? $output : json_encode($output),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        $text = implode("\n", $textParts);

        $conversation->messages()->create(['role' => 'assistant', 'content' => $text]);

        return $text;
    }

    private function history(Conversation $conversation): array
    {
        return $conversation->messages()
            ->latest()->limit(config('chatbot.history_messages'))->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()->all();
    }
}
