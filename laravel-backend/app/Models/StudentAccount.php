<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StudentAccount extends Model {
    protected $fillable = ['student_id', 'user_id', 'relationship'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
