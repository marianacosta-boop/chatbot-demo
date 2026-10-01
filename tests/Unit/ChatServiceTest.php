<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Services\Chat\ChatService;
use App\Services\Chat\ClaudeClient;
use App\Services\Chat\ClientContextService;
use App\Services\Chat\SystemPromptBuilder;
use App\Services\Chat\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_preserves_a_greeting_emitted_before_a_tool_call(): void
    {
        $conversation = Conversation::create(['guest_session_id' => 'guest-session']);

        $claude = Mockery::mock(ClaudeClient::class);
        $claude->shouldReceive('messages')->twice()->andReturn(
            [
                'content' => [
                    ['type' => 'text', 'text' => 'Olá!'],
                    ['type' => 'tool_use', 'id' => 'tool-1', 'name' => 'search_knowledge_base', 'input' => ['query' => 'produto']],
                ],
                'stop_reason' => 'tool_use',
            ],
            [
                'content' => [['type' => 'text', 'text' => 'Como posso ajudar?']],
                'stop_reason' => 'end_turn',
            ],
        );

        $prompt = Mockery::mock(SystemPromptBuilder::class);
        $prompt->shouldReceive('buildGuest')->once()->andReturn('System prompt');

        $tools = Mockery::mock(ToolRegistry::class);
        $tools->shouldReceive('definitions')->once()->with(true)->andReturn([]);
        $tools->shouldReceive('execute')
            ->once()
            ->with('search_knowledge_base', ['query' => 'produto'], null)
            ->andReturn(['results' => []]);

        $service = new ChatService(
            $claude,
            Mockery::mock(ClientContextService::class),
            $prompt,
            $tools,
        );

        $reply = $service->open($conversation);

        $this->assertSame("Olá!\nComo posso ajudar?", $reply);
        $this->assertSame($reply, $conversation->messages()->sole()->content);
    }
}