<?php

namespace Modules\Exams\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamDepartment extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\ExamDepartmentFactory
    {
        return \Database\Factories\ExamDepartmentFactory::new();
    }

    protected $fillable = ['name', 'slug', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function templates(): HasMany
    {
        return $this->hasMany(ExamTemplate::class, 'department_id');
    }
}
