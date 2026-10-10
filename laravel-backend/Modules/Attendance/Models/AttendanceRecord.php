<?php
namespace Modules\Attendance\Models;

use Modules\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AttendanceRecord extends Model {
    use HasFactory, \Modules\Reports\Models\Concerns\BumpsReportVersion;

    protected static function newFactory(): \Database\Factories\AttendanceRecordFactory
    {
        return \Database\Factories\AttendanceRecordFactory::new();
    }
    protected $fillable = ['student_id', 'date_at', 'attendance_date', 'status', 'note', 'recorded_by'];
    protected function casts(): array { return ['date_at' => 'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
