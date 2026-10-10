<?php

namespace Modules\Exams\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSessionEvent extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\ExamSessionEventFactory
    {
        return \Database\Factories\ExamSessionEventFactory::new();
    }

    protected $fillable = ['session_id', 'type', 'metadata', 'occurred_at'];
    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];

    public function session(): BelongsTo { return $this->belongsTo(ExamSession::class, 'session_id'); }
}
