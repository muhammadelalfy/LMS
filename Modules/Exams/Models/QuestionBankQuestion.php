<?php

namespace Modules\Exams\Models;

use Modules\Auth\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankQuestion extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\QuestionBankQuestionFactory
    {
        return \Database\Factories\QuestionBankQuestionFactory::new();
    }

    protected $fillable = ['created_by', 'department_id', 'type', 'title', 'grade', 'prompt_html', 'options', 'correct_answer', 'points', 'tags', 'is_active'];
    protected $casts = ['options' => 'array', 'points' => 'integer', 'is_active' => 'boolean'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function department(): BelongsTo { return $this->belongsTo(ExamDepartment::class, 'department_id'); }
}
