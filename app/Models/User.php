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
}
