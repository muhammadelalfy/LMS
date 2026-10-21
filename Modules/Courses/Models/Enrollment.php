<?php

declare(strict_types=1);

namespace Modules\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payments\Models\Payment;
use Modules\Students\Models\Student;

class Enrollment extends Model
{
    protected $table = 'course_enrollments';

    protected $fillable = ['course_id', 'student_id', 'payment_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** A free course is open at once; a paid one once its payment has been paid. */
    public function isActive(): bool
    {
        return $this->payment_id === null || $this->payment?->status === 'paid';
    }
}
