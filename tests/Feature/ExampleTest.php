<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Services\Chat\ChatService;
use App\Services\Chat\Tools\Tool;
use App\Services\Chat\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_chat_without_authentication_and_cannot_access_another_session(): void
    {
        $chat = $this->mock(ChatService::class);
        $chat->shouldReceive('open')->once()->andReturn('Olá! Tem alguma dúvida?');
        $chat->shouldReceive('reply')->once()->withArgs(fn ($conversation, $message) => $message === 'Tenho uma dúvida.')
            ->andReturn('Como posso ajudar?');

        $start = $this->postJson('/chat/guest/conversations')->assertOk();
        $startEvents = $this->parseSse($start->streamedContent());
        $conversationId = $startEvents['conversation_id'];

        $conversation = Conversation::findOrFail($conversationId);
        $this->assertNull($conversation->user_id);
        $this->assertNotEmpty($conversation->guest_session_id);

        $reply = $this->postJson("/chat/guest/conversations/{$conversationId}/messages", ['message' => 'Tenho uma dúvida.'])
            ->assertOk();
        $this->assertSame('Como posso ajudar?', $this->parseSse($reply->streamedContent())['reply']);

        $otherSessionConversation = Conversation::create(['guest_session_id' => 'another-session']);
        $this->postJson("/chat/guest/conversations/{$otherSessionConversation->id}/messages", ['message' => 'Olá'])
            ->assertForbidden();

        $this->get('/chat-demo')->assertOk();
    }

    /** Decodes an SSE (`data: {...}\n\n`) body into its conversation_id and final reply text. */
    private function parseSse(string $content): array
    {
        $result = ['conversation_id' => null, 'reply' => null];

        foreach (explode("\n\n", $content) as $event) {
            foreach (explode("\n", $event) as $line) {
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }
                $data = json_decode(trim(substr($line, 5)), true);
                if (! is_array($data)) {
                    continue;
                }
                $result['conversation_id'] ??= $data['conversation_id'] ?? null;
                if (isset($data['reply'])) {
                    $result['reply'] = $data['reply'];
                }
            }
        }

        return $result;
    }

    public function test_guest_tool_registry_only_exposes_public_search(): void
    {
        $knowledgeTool = Mockery::mock(Tool::class);
        $knowledgeTool->shouldReceive('name')->andReturn('search_knowledge_base');
        $knowledgeTool->shouldReceive('definition')->once()->andReturn(['name' => 'search_knowledge_base']);

        $accountTool = Mockery::mock(Tool::class);
        $accountTool->shouldReceive('name')->andReturn('create_renewal_opportunity');
        $accountTool->shouldNotReceive('handle');

        $tools = new ToolRegistry([$knowledgeTool, $accountTool]);

        $this->assertSame([['name' => 'search_knowledge_base']], $tools->definitions(guest: true));
        $this->assertSame(
            ['error' => 'This tool requires an authenticated client.'],
            $tools->execute('create_renewal_opportunity', [], null),
        );
    }
}
