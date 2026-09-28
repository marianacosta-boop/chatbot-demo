<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;

class ClaudeClient
{
    public function messages(string $system, array $messages, array $tools = []): array
    {
        return Http::withHeaders([
                'x-api-key'         => config('chatbot.api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(60)
            ->retry(2, 500)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('chatbot.model'),
                'max_tokens' => config('chatbot.max_tokens'),
                'system'     => $system,
                'messages'   => $messages,
                'tools'      => $tools,
            ])
            ->throw()
            ->json(); // ['content' => [...], 'stop_reason' => 'end_turn'|'tool_use', ...]
    }
}
