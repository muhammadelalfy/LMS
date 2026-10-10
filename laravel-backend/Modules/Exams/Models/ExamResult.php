<?php
namespace Modules\Exams\Models;

use Modules\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ExamResult extends Model {
    use HasFactory, \Modules\Reports\Models\Concerns\BumpsReportVersion;

    protected static function newFactory(): \Database\Factories\ExamResultFactory
    {
        return \Database\Factories\ExamResultFactory::new();
    }
    protected $fillable = ['student_id', 'title', 'score', 'max_score', 'taken_at', 'recorded_by'];
    protected function casts(): array { return ['taken_at' => 'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
