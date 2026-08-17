<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plugin_product_id', 'installed_by', 'module_name', 'version', 'path',
        'status', 'config', 'installed_at', 'last_error',
    ];

    protected function casts(): array
    {
        return ['config' => 'array', 'installed_at' => 'datetime'];
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(PluginProduct::class, 'plugin_product_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }
}
