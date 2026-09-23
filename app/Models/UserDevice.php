<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'device_id',
        'fcm_token',
        'device_name',
        'platform',
        'ip',
        'user_agent',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /** A push token is a send-capability for that device — never serialize it. */
    protected $hidden = ['fcm_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    /**
     * An FCM token belongs to one app install, so exactly one row may hold it: take it off
     * every other device row.
     *
     * A reinstall hands the same token to a fresh `X-Device-Id`, and signing in on a shared
     * phone hands it to another user. In both cases the old row keeps its copy, and
     * `User::fcmTokens()` then feeds `sendMulticast()` the same token twice — the same push
     * arriving twice on one handset — or keeps pushing the previous account's notifications
     * to a phone somebody else is now signed in on.
     */
    public function claimFcmToken(): void
    {
        if (! $this->fcm_token) {
            return;
        }

        static::where('fcm_token', $this->fcm_token)
            ->whereKeyNot($this->getKey())
            ->update(['fcm_token' => null]);
    }
}
