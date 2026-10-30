<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Manajemen kelas guru: buat kelas, keanggotaan siswa (kode kelas atau
 * ditambahkan guru), dan pembuatan akun siswa oleh guru.
 */
class ClassroomService
{
    public function __construct(
        private AssignmentService $assignments,
    ) {}

    /**
     * @param  array{name: string, description?: ?string}  $data
     */
    public function create(Teacher $teacher, array $data): Classroom
    {
        return $teacher->classrooms()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'code' => Classroom::generateCode(),
        ]);
    }

    public function regenerateCode(Classroom $classroom): Classroom
    {
        $classroom->update(['code' => Classroom::generateCode()]);

        return $classroom;
    }

    /**
     * Siswa bergabung lewat kode kelas. Null bila kode tidak ditemukan.
     */
    public function joinByCode(Student $student, string $code): ?Classroom
    {
        $classroom = Classroom::query()->where('code', strtoupper(trim($code)))->first();

        if ($classroom) {
            $this->addStudent($classroom, $student);
        }

        return $classroom;
    }

    /**
     * Masukkan siswa ke kelas lalu bagikan tugas kelas yang masih aktif.
     */
    public function addStudent(Classroom $classroom, Student $student): void
    {
        DB::transaction(function () use ($classroom, $student) {
            $classroom->students()->syncWithoutDetaching([$student->id]);
            $this->assignments->distributeClassroomAssignmentsTo($classroom, $student);
        });
    }

    /**
     * Keluarkan siswa dari kelas. Tugas kelas yang belum selesai ikut dicabut,
     * tugas yang sudah selesai (dan XP-nya) tetap tersimpan.
     */
    public function removeStudent(Classroom $classroom, Student $student): void
    {
        DB::transaction(function () use ($classroom, $student) {
            $classroom->students()->detach($student->id);

            AssignmentSubmission::query()
                ->where('student_id', $student->id)
                ->where('status', '!=', AssignmentSubmission::STATUS_COMPLETED)
                ->whereHas('assignment', fn ($query) => $query->where('classroom_id', $classroom->id))
                ->delete();
        });
    }

    /**
     * Guru membuatkan akun siswa baru dan langsung memasukkannya ke kelas.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function createStudentAccount(Classroom $classroom, array $data): Student
    {
        return DB::transaction(function () use ($classroom, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_STUDENT,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $student = $user->student()->create();
            $this->addStudent($classroom, $student);

            return $student;
        });
    }
}
