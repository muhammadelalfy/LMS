<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AttendanceRecord extends Model {
    use HasFactory;
    protected $fillable = ['student_id', 'date_at', 'status', 'note', 'recorded_by'];
    protected function casts(): array { return ['date_at' => 'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
