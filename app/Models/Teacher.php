<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * Query siswa yang tergabung di salah satu kelas guru ini.
     *
     * @return Builder<Student>
     */
    public function studentsQuery(): Builder
    {
        return Student::query()->whereHas('classrooms', fn (Builder $query) => $query->where('teacher_id', $this->id));
    }

    public function hasStudent(Student $student): bool
    {
        return $this->studentsQuery()->whereKey($student->id)->exists();
    }

    public function assignmentsCount(): int
    {
        return $this->assignments()->count();
    }
}
