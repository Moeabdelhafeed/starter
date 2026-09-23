<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'name',
    ];

    /**
     * The provider's own user id is a credential-adjacent identifier and is never part
     * of the documented social-account shape — keep it off every serialized response.
     */
    protected $hidden = ['provider_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a human-readable provider name.
     */
    public function getProviderNameAttribute(): string
    {
        return match ($this->provider) {
            'google.com' => 'Google',
            'apple.com' => 'Apple',
            'facebook.com' => 'Facebook',
            'twitter.com' => 'Twitter',
            'github.com' => 'GitHub',
            default => ucfirst(str_replace('.com', '', $this->provider)),
        };
    }

    protected $appends = ['provider_name'];
}
