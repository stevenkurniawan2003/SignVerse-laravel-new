<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelas guru:
     *   - classrooms        : kelas milik guru, dengan kode unik untuk bergabung.
     *   - classroom_student : keanggotaan siswa (siswa bisa ikut beberapa kelas).
     *   - assignments.classroom_id : tugas dibagikan ke anggota satu kelas.
     */
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('code', 12)->unique();
            $table->timestamps();
        });

        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['classroom_id', 'student_id']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('teacher_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });

        Schema::dropIfExists('classroom_student');
        Schema::dropIfExists('classrooms');
    }
};
