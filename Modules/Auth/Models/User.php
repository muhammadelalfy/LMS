<?php

namespace Modules\Auth\Models;

use Modules\Students\Models\StudentAccount;
use Modules\Notifications\Models\DeviceToken;
use Modules\PluginStore\Models\PluginPurchase;
use Modules\PluginStore\Models\InstalledModule;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): \Database\Factories\UserFactory
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = ['name', 'email', 'password', 'role', 'disabled_at', 'expires_at'];

    protected $hidden = ['password', 'remember_token'];

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
        return $this->disabled_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
