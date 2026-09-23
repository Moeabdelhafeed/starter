<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One saved chat with the AI assistant.
 *
 * Always reached through `$admin->aiConversations()`, never `find()` — a
 * conversation holds questions about the business and whatever the assistant
 * read back, so one admin's history is not another's to open.
 */
class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    /**
     * Name the chat after the question that started it, the way every chat UI
     * does — an untitled list is unusable once it is more than a few rows.
     */
    public static function titleFrom(string $question): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $question) ?? ''), 60);
    }
}
