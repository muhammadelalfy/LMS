<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PluginProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'version', 'module_name', 'artifact_path',
        'artifact_sha256', 'price', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PluginPurchase::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(InstalledModule::class);
    }
}
