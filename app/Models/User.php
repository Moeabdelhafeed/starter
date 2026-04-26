<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasImage;
use App\Traits\LogsActivity;
use App\Traits\NotifiesAdmin;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasImage, HasRoles, LogsActivity, Notifiable, NotifiesAdmin, SoftDeletes;

    /**
     * Events that trigger admin notifications.
     * Only 'created' for new app user registrations.
     */
    protected static array $notifyEvents = ['created'];

    /**
     * Notification type for this model.
     */
    protected static string $notifyType = 'app_users';

    /**
     * Determine if a notification should be created for this event.
     * Only notify for app users (created via API, not admin panel).
     */
    protected function shouldNotify(string $event): bool
    {
        // Only notify if no admin user is logged in (API registration)
        // This means the user is being created via the mobile app, not the admin panel
        return ! auth()->guard('web')->check();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'is_active',
        'fcm_token',
    ];

    public function otps()
    {
        return $this->hasMany(Otp::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Check if user has any linked social accounts.
     */
    public function hasSocialAccounts(): bool
    {
        return $this->socialAccounts()->exists();
    }

    /**
     * Check if user has a specific provider linked.
     */
    public function hasSocialProvider(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }

    protected $guard_name = ['web', 'api'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Hide identifier-like fields (`email`, `phone`, `username`) from API serialization
     * when they are NOT configured (neither in `AUTH_IDENTIFIERS` nor enabled via `HAS_*_FIELD`).
     * Admin/web responses keep all columns so the admin panel can manage every field.
     */
    public function toArray()
    {
        $array = parent::toArray();

        if (! request()->is('api/*')) {
            return $array;
        }

        foreach (['email', 'phone', 'username'] as $field) {
            if (! self::isAuthFieldEnabled($field)) {
                unset($array[$field]);
            }
        }

        return $array;
    }

    /**
     * Normalize email to lowercase on every write to keep lookups case-insensitive
     * across MySQL/PostgreSQL.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value !== null ? strtolower(trim($value)) : null;
    }

    private static function isAuthFieldEnabled(string $field): bool
    {
        $identifiers = array_map('trim', explode(',', env('AUTH_IDENTIFIERS', 'email')));

        if (in_array($field, $identifiers, true)) {
            return true;
        }

        return filter_var(env('HAS_'.strtoupper($field).'_FIELD', false), FILTER_VALIDATE_BOOLEAN);
    }
}
