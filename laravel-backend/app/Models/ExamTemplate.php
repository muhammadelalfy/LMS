<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'created_by', 'title', 'grade', 'duration_minutes', 'instructions', 'watermark_text', 'watermark_opacity', 'status'];
    protected $casts = ['duration_minutes' => 'integer', 'watermark_opacity' => 'integer'];

    public function department(): BelongsTo { return $this->belongsTo(ExamDepartment::class, 'department_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function questions(): HasMany { return $this->hasMany(ExamQuestion::class, 'template_id')->orderBy('sort_order'); }
    public function sessions(): HasMany { return $this->hasMany(ExamSession::class, 'template_id'); }
}
