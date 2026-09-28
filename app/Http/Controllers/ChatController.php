<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function start(Request $request)
    {
        $conversation = Conversation::create(['user_id' => $request->user()->id]);

        // Proactive opener: greets the client and mentions expiring products if any.
        $reply = $this->chat->open($conversation);

        return response()->json(['conversation_id' => $conversation->id, 'reply' => $reply]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $data = $request->validate(['message' => 'required|string|max:4000']);

        return response()->json(['reply' => $this->chat->reply($conversation, $data['message'])]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return $conversation->load('messages:id,conversation_id,role,content,created_at');
    }
}
