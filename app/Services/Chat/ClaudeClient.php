<?php

namespace App\Services\Chat;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;

class ClaudeClient
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    public function messages(string $system, array $messages, array $tools = []): array
    {
        return Http::withHeaders($this->headers())
            ->timeout(60)
            ->retry(2, 500)
            ->post(self::URL, $this->payload($system, $messages, $tools))
            ->throw()
            ->json(); // ['content' => [...], 'stop_reason' => 'end_turn'|'tool_use', ...]
    }

    /**
     * Same call as messages(), but reads the response as an SSE stream and invokes $onText with
     * every text delta as soon as it arrives, so the client can show tokens as they are generated.
     * Returns the fully assembled response in the same shape as messages().
     */
    public function stream(string $system, array $messages, array $tools, callable $onText): array
    {
        $response = (new GuzzleClient())->post(self::URL, [
            'headers' => $this->headers(),
            'json'    => $this->payload($system, $messages, $tools) + ['stream' => true],
            'stream'  => true,
            'timeout' => 60,
        ]);

        $body       = $response->getBody();
        $blocks     = [];
        $stopReason = null;
        $buffer     = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $this->consumeEvent(substr($buffer, 0, $pos), $blocks, $stopReason, $onText);
                $buffer = substr($buffer, $pos + 2);
            }
        }

        $content = collect($blocks)->map(fn ($b) => $b['type'] === 'text'
            ? ['type' => 'text', 'text' => $b['text']]
            : ['type' => 'tool_use', 'id' => $b['id'], 'name' => $b['name'], 'input' => json_decode($b['input_json'] ?: '{}', true) ?: []]
        )->values()->all();

        return ['content' => $content, 'stop_reason' => $stopReason];
    }

    private function consumeEvent(string $rawEvent, array &$blocks, ?string &$stopReason, callable $onText): void
    {
        foreach (explode("\n", $rawEvent) as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }
            $data = json_decode(trim(substr($line, 5)), true);
            if (! is_array($data)) {
                continue;
            }

            match ($data['type'] ?? null) {
                'content_block_start' => $blocks[$data['index']] = ($data['content_block']['type'] ?? null) === 'tool_use'
                    ? ['type' => 'tool_use', 'id' => $data['content_block']['id'], 'name' => $data['content_block']['name'], 'input_json' => '']
                    : ['type' => 'text', 'text' => ''],
                'content_block_delta' => $this->applyDelta($blocks, $data, $onText),
                'message_delta'       => $stopReason = $data['delta']['stop_reason'] ?? $stopReason,
                default => null,
            };
        }
    }

    private function applyDelta(array &$blocks, array $data, callable $onText): void
    {
        $index = $data['index'];
        $delta = $data['delta'];

        if (($delta['type'] ?? null) === 'text_delta') {
            $blocks[$index]['text'] .= $delta['text'];
            $onText($delta['text']);
        } elseif (($delta['type'] ?? null) === 'input_json_delta') {
            $blocks[$index]['input_json'] .= $delta['partial_json'];
        }
    }

    private function headers(): array
    {
        return [
            'x-api-key'         => config('chatbot.api_key'),
            'anthropic-version' => '2023-06-01',
        ];
    }

    private function payload(string $system, array $messages, array $tools): array
    {
        return [
            'model'      => config('chatbot.model'),
            'max_tokens' => config('chatbot.max_tokens'),
            'system'     => $system,
            'messages'   => $messages,
            'tools'      => $tools,
        ];
    }
}
