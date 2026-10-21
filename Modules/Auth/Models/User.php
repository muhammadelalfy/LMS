<?php

namespace Modules\Auth\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Notifications\Models\DeviceToken;
use Modules\PluginStore\Models\InstalledModule;
use Modules\PluginStore\Models\PluginPurchase;
use Modules\Students\Models\StudentAccount;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = ['name', 'email', 'password', 'role', 'disabled_at', 'expires_at'];

    protected $hidden = ['password', 'remember_token'];

    public const STATUS_ACTIVE = 'active';

    /** A teacher who registered and is waiting for the school to approve them. */
    public const STATUS_PENDING = 'pending';

    protected $attributes = ['status' => self::STATUS_ACTIVE, 'is_demo' => false];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'is_demo' => 'boolean',
            'expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function studentAccount()
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function pluginPurchases(): HasMany
    {
        return $this->hasMany(PluginPurchase::class);
    }

    public function installedModules(): HasMany
    {
        return $this->hasMany(InstalledModule::class, 'installed_by');
    }

    /** Whether the account may sign in and use the API right now. */
    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->disabled_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** A "try it" account: it cannot change its own sign-in details or manage other accounts. */
    public function isDemo(): bool
    {
        return $this->is_demo === true;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** pending, disabled or active, in the words the admin screens use. */
    public function accountState(): string
    {
        return match (true) {
            $this->isPending() => 'pending',
            $this->disabled_at !== null => 'disabled',
            default => 'active',
        };
    }

    public function isAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
