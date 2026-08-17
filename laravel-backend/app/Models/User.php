<?php

namespace App\Models;

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

    protected $fillable = ['name', 'email', 'password', 'role'];

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
            'password' => 'hashed',
        ];
    }

    public function studentAccount()
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function pluginPurchases(): HasMany
    {
        return $this->hasMany(PluginPurchase::class);
    }

    public function installedModules(): HasMany
    {
        return $this->hasMany(InstalledModule::class, 'installed_by');
    }

    public function isAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
