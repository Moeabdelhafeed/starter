<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    use HasFactory;

    /**
     * Wrong guesses a single code tolerates before it is destroyed. Six digits over a
     * five-minute window is otherwise a short walk for a script.
     */
    public const MAX_ATTEMPTS = 5;

    /** Digits in a generated code. Everything below derives from it — change it here only. */
    public const LENGTH = 6;

    protected $fillable = [
        'user_id',
        'identifier',
        'otp',
        'type',
        'expires_at',
    ];

    /** Never let a code ride along in a serialized model. */
    protected $hidden = ['otp'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * A fresh code of `LENGTH` digits.
     *
     * Random in every real install. Under `IS_TESTING` it is the ascending sequence
     * ("123456") instead: testing mode already echoes the code back in the API response,
     * and a predictable one lets a tester — or an automated client — type it without
     * reading the response at all. Every sender goes through here, so there is one
     * answer to "what code did we just send".
     */
    public static function generate(): string
    {
        if (config('app.is_testing')) {
            return substr('123456789', 0, self::LENGTH);
        }

        return (string) random_int(10 ** (self::LENGTH - 1), 10 ** self::LENGTH - 1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user's live code of this type, if `$code` matches it.
     *
     * Every OTP-verifying endpoint goes through here so the attempt counter cannot be
     * sidestepped by picking a different one: at most one unexpired row exists per
     * (user, type) — both senders delete the previous row first — so a miss is a wrong
     * guess against that row, and MAX_ATTEMPTS misses burn it. Returns null for a wrong,
     * expired, or exhausted code; callers answer with the same `errors.otp` either way,
     * which is what keeps the response from confirming whether the code was close.
     *
     * The caller decides whether to delete the row on success — the forgot-password
     * flow verifies once and consumes on a later request.
     */
    public static function attempt(User $user, string $code, string $type, ?string $identifier = null): ?self
    {
        $query = $user->otps()
            ->where('type', $type)
            ->where('expires_at', '>', now());

        if ($identifier !== null) {
            $query->where('identifier', $identifier);
        }

        /** @var self|null $otp */
        $otp = $query->latest('id')->first();

        if (! $otp) {
            return null;
        }

        if (hash_equals($otp->otp, $code)) {
            return $otp;
        }

        $otp->increment('attempts');

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->delete();
        }

        return null;
    }
}
