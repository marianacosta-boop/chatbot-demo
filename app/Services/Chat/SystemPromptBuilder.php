<?php

namespace App\Services\Chat;

class SystemPromptBuilder
{
    public function build(array $snapshot): string
    {
        $template = file_get_contents(resource_path('prompts/chatbot-system.md'));

        return strtr($template, [
            '{{COMPANY_NAME}}'         => 'AcinGOV',
            '{{CLIENT_SNAPSHOT_JSON}}' => json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function buildGuest(): string
    {
        return file_get_contents(resource_path('prompts/chatbot-guest-system.md'));
    }
}
