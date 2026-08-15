<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WorksheetAssignment extends Model {
    use HasFactory;
    protected $fillable = ['worksheet_id', 'student_id', 'status', 'assigned_at', 'submitted_at', 'score', 'max_score', 'feedback'];
    protected function casts(): array { return ['assigned_at' => 'datetime', 'submitted_at' => 'datetime']; }
    public function worksheet(): BelongsTo { return $this->belongsTo(Worksheet::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
