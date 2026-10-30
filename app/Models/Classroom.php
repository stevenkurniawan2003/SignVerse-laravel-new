<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kelas milik guru. Siswa bergabung lewat kode kelas atau ditambahkan guru;
 * tugas guru dibagikan ke anggota kelas.
 */
class Classroom extends Model
{
    /** @use HasFactory<\Database\Factories\ClassroomFactory> */
    use HasFactory;
    protected $fillable = ['teacher_id', 'name', 'description', 'code'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Kode kelas unik 6 karakter tanpa huruf/angka yang mirip (O/0, I/1).
     */
    public static function generateCode(): string
    {
        do {
            $code = collect(range(1, 6))
                ->map(fn () => Str::of('ABCDEFGHJKLMNPQRSTUVWXYZ23456789')->charAt(random_int(0, 31)))
                ->implode('');
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
