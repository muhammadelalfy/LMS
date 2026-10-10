<?php

namespace Modules\Students\Models;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Payments\Models\Payment;
use Modules\Exams\Models\ExamResult;
use Modules\Learning\Models\WorksheetAssignment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory, \Modules\Reports\Models\Concerns\BumpsReportVersion;

    protected static function newFactory(): \Database\Factories\StudentFactory
    {
        return \Database\Factories\StudentFactory::new();
    }

    protected $fillable = ['name', 'group', 'grade', 'phone', 'parent_phone', 'status'];

    protected $hidden = ['qr_token'];

    public function ensureQrToken(): string
    {
        if (! $this->qr_token) {
            $this->forceFill(['qr_token' => Str::random(64)])->save();
        }

        return $this->qr_token;
    }

    /** Rotates the attendance QR so a lost or shared card stops working. */
    public function regenerateQrToken(): string
    {
        $this->forceFill(['qr_token' => Str::random(64)])->save();

        return $this->qr_token;
    }

    public function account(): HasOne
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorksheetAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
