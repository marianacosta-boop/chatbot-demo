<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function start(Request $request)
    {
        $conversation = Conversation::create(['user_id' => $request->user()->id]);

        // Proactive opener: greets the client and mentions expiring products if any.
        return $this->sse($conversation->id, fn ($onChunk) => $this->chat->open($conversation, $onChunk));
    }

    public function startGuest(Request $request)
    {
        $conversation = Conversation::create(['guest_session_id' => $this->guestSessionKey($request)]);

        return $this->sse($conversation->id, fn ($onChunk) => $this->chat->open($conversation, $onChunk));
    }

    public function send(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $data = $request->validate(['message' => 'required|string|max:4000']);

        return $this->sse(null, fn ($onChunk) => $this->chat->reply($conversation, $data['message'], onChunk: $onChunk));
    }

    public function sendGuest(Request $request, Conversation $conversation)
    {
        abort_unless(
            $conversation->user_id === null &&
            hash_equals((string) $conversation->guest_session_id, $this->guestSessionKey($request)),
            403,
        );

        $data = $request->validate(['message' => 'required|string|max:4000']);

        return $this->sse(null, fn ($onChunk) => $this->chat->reply($conversation, $data['message'], onChunk: $onChunk));
    }

    private function guestSessionKey(Request $request): string
    {
        if (! $request->session()->has('guest_chat_key')) {
            $request->session()->put('guest_chat_key', Str::random(64));
        }

        return $request->session()->get('guest_chat_key');
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return $conversation->load('messages:id,conversation_id,role,content,created_at');
    }

    /**
     * Streams the reply to the browser as Server-Sent Events, so the client sees text as it is
     * generated instead of waiting for the full (possibly multi-round, tool-using) completion.
     */
    private function sse(?int $conversationId, \Closure $run): StreamedResponse
    {
        return response()->stream(function () use ($conversationId, $run) {
            set_time_limit(0);

            if ($conversationId !== null) {
                $this->emit(['conversation_id' => $conversationId]);
            }

            try {
                $reply = $run(fn (string $chunk) => $this->emit(['delta' => $chunk]));
                // Always send the full text too: guarantees the client ends up in sync even if
                // some deltas were never streamed (e.g. an already-buffered/non-streamed reply).
                $this->emit(['reply' => $reply, 'done' => true]);
            } catch (\Throwable $e) {
                report($e);
                $this->emit(['error' => 'Ocorreu um erro. Tente novamente.']);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function emit(array $data): void
    {
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
