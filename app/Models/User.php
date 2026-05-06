<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Exportable;
use App\Traits\HasImage;
use App\Traits\LogsActivity;
use App\Traits\NotifiesAdmin;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Exportable, HasApiTokens, HasFactory, HasImage, HasRoles, LogsActivity, Notifiable, NotifiesAdmin, SoftDeletes;

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
        'current_lang',
        'account_deleted_at',
        'is_guest',
        'platform',
        'guest_id',
        'last_seen_at',
    ];

    /**
     * Columns included in CSV export. See Traits\Exportable.
     */
    protected array $exportable = ['id', 'name', 'email', 'phone', 'username', 'is_active', 'is_guest', 'platform', 'guest_id', 'last_seen_at', 'verified_at', 'created_at'];

    /**
     * Relations to delete alongside the user.
     * Add relation method names here, e.g. ['posts', 'comments'].
     * Soft-delete on admin trash; force-delete on permanent purge.
     */
    protected array $cascadeOnDelete = [];

    /**
     * Relations to restore alongside the user when admin restores from trash.
     * Only rows whose deleted_at matches the parent's deleted_at are restored,
     * so children trashed independently of the user are left alone.
     */
    protected array $cascadeOnRestore = [];

    protected ?Carbon $cascadeRestoreDeletedAt = null;

    protected static function booted(): void
    {
        static::deleted(function (User $user): void {
            if ($user->isForceDeleting()) {
                return;
            }
            $user->cascadeDelete(force: false);
        });

        static::forceDeleted(function (User $user): void {
            $user->cascadeDelete(force: true);
        });

        static::restoring(function (User $user): void {
            // deleted_at is still set here; capture it for the restored hook
            // so cascadeRestore can match children stamped with the same timestamp.
            $user->cascadeRestoreDeletedAt = $user->deleted_at;
        });

        static::restored(function (User $user): void {
            $user->cascadeRestore();
        });
    }

    public function cascadeDelete(bool $force = false): void
    {
        foreach ($this->cascadeOnDelete as $relation) {
            $query = $this->{$relation}();

            if ($force) {
                // Iterate so HasImage / HasVideo / boot-event hooks fire on each
                // child — bare `forceDelete()` on a hasMany skips them and leaks
                // storage files + image/video table rows.
                $query->withTrashed()->get()->each(function ($child) {
                    if (method_exists($child, 'deleteImage')) {
                        $child->deleteImage();
                    }
                    if (method_exists($child, 'deleteVideo')) {
                        $child->deleteVideo();
                    }
                    $child->forceDelete();
                });

                continue;
            }

            // Stamp children with parent's exact deleted_at so cascadeRestore can match.
            // Bypasses child model events — declarative cascade is the contract here.
            $query->update(['deleted_at' => $this->deleted_at]);
        }

        // Parent's own files on force-delete: clean before the row is gone.
        if ($force) {
            if (method_exists($this, 'deleteImage')) {
                $this->deleteImage();
            }
            if (method_exists($this, 'deleteVideo')) {
                $this->deleteVideo();
            }
        }
    }

    public function cascadeRestore(): void
    {
        $deletedAt = $this->cascadeRestoreDeletedAt;
        $this->cascadeRestoreDeletedAt = null;

        if (! $deletedAt) {
            return;
        }

        foreach ($this->cascadeOnRestore as $relation) {
            $this->{$relation}()
                ->onlyTrashed()
                ->where('deleted_at', $deletedAt)
                ->restore();
        }
    }

    public function isPendingDeletion(): bool
    {
        return $this->account_deleted_at !== null;
    }

    public function markAccountDeleted(): void
    {
        $this->forceFill(['account_deleted_at' => now()])->save();
    }

    public function restoreAccount(): void
    {
        $this->forceFill(['account_deleted_at' => null])->save();
    }

    public function otps()
    {
        return $this->hasMany(Otp::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Active FCM tokens across every logged-in device. Use this for any
     * push-notification send so multi-session users get notified everywhere.
     *
     * @return array<int, string>
     */
    public function fcmTokens(): array
    {
        return $this->devices()
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();
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
            'account_deleted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
        ];
    }

    public function scopeGuests($query)
    {
        return $query->where('is_guest', true);
    }

    public function scopeRealUsers($query)
    {
        return $query->where('is_guest', false);
    }

    public function scopeWeb($query)
    {
        return $query->where('platform', 'web');
    }

    public function scopeIos($query)
    {
        return $query->where('platform', 'ios');
    }

    public function scopeAndroid($query)
    {
        return $query->where('platform', 'android');
    }

    /**
     * Resolve or lazily create a guest user keyed by `(guest_id)`. Throttles
     * `last_seen_at` writes to once per minute so every api hit doesn't churn
     * the row.
     */
    public static function findOrCreateGuest(string $platform, string $guestId): self
    {
        $user = self::where('guest_id', $guestId)->where('is_guest', true)->first();

        if ($user) {
            if (! $user->last_seen_at || $user->last_seen_at->diffInSeconds(now()) > 60) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }

            return $user;
        }

        $user = self::create([
            'name' => 'Guest',
            'is_active' => true,
            'is_guest' => true,
            'platform' => $platform,
            'guest_id' => $guestId,
            'last_seen_at' => now(),
            'verified_at' => now(),
        ]);

        $role = Role::where('name', 'user')
            ->where('guard_name', 'api')
            ->first();

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Wipe a guest row matched by `guest_id`. Called from `trackDevice` after a
     * real auth flow issues a token so the device promotes from guest to user
     * without leaving an orphan row.
     */
    public static function convertFromGuest(string $guestId): void
    {
        self::where('guest_id', $guestId)
            ->where('is_guest', true)
            ->get()
            ->each(fn (self $u) => $u->forceDelete());
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

        // Lets the client branch UI between "Set password" (social-only) and
        // "Change password" without exposing the password column itself.
        $array['has_password'] = $this->password !== null;

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
