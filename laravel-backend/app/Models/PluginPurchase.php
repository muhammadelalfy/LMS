<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'plugin_product_id', 'status', 'purchased_at'];

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(PluginProduct::class, 'plugin_product_id');
    }
}
