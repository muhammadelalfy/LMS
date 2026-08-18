<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'type', 'prompt_html', 'options', 'correct_answer', 'points', 'sort_order'];
    protected $casts = ['options' => 'array', 'points' => 'integer', 'sort_order' => 'integer'];

    public function template(): BelongsTo { return $this->belongsTo(ExamTemplate::class, 'template_id'); }
}
