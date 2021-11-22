<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'message_type', 'message_text', 'message_content_url', 'meta_title', 'meta_description', 'meta_image', 'is_deleted'];

    public function conversation()
    {
        return $this->belongsTo('App\Models\Conversation');
    }
}
