<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'tool_calls'];
    protected $casts = ['tool_calls' => 'array'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
}
