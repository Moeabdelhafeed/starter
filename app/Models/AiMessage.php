<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in a saved chat.
 *
 * Only `user` and `assistant` are ever stored. The system prompt is rebuilt per
 * request from the current code rather than persisted, so an install that
 * changes its rules does not keep replaying the old ones; tool results are not
 * stored either — they are intermediate, and the answer already carries what
 * mattered.
 */
class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_conversation_id', 'role', 'content', 'tools_used', 'pending_action', 'performed_at'];

    protected function casts(): array
    {
        return ['tools_used' => 'array', 'pending_action' => 'array', 'performed_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
