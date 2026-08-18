<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSessionAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['session_id', 'question_id', 'answer', 'answered_at'];
    protected $casts = ['answered_at' => 'datetime'];

    public function session(): BelongsTo { return $this->belongsTo(ExamSession::class, 'session_id'); }
    public function question(): BelongsTo { return $this->belongsTo(ExamQuestion::class, 'question_id'); }
}
