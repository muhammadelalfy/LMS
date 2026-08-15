<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model {
    use HasFactory;
    protected $fillable = ['student_id', 'amount', 'status', 'due_at', 'paid_at', 'note', 'recorded_by'];
    protected function casts(): array { return ['due_at' => 'datetime', 'paid_at' => 'datetime']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
