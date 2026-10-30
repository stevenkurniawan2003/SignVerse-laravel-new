<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Membuat akun guru asli untuk produksi — tanpa data dummy.
 *
 *   php artisan signtech:create-teacher
 *
 * Kata sandi diketik rahasia (tidak tampil di layar/chat), email diverifikasi.
 * Jika akun dengan email tsb sudah ada, profilnya diubah menjadi guru
 * (idempoten, aman dijalankan berulang).
 */
class CreateTeacher extends Command
{
    protected $signature = 'signtech:create-teacher
        {--name= : Nama lengkap guru}
        {--email= : Email guru}';

    protected $description = 'Membuat akun guru SignTech (tanpa data dummy)';

    public function handle(): int
    {
        $name = ($name = (string) $this->option('name')) !== '' ? $name : $this->ask('Nama lengkap guru');
        $email = ($email = (string) $this->option('email')) !== '' ? $email : $this->ask('Email guru');
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Email \"{$email}\" tidak valid.");

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isTeacher()) {
            $this->info("Akun guru \"{$name}\" <{$email}> sudah ada — tidak ada yang berubah.");

            return self::SUCCESS;
        }

        $password = (string) ($this->secret('Kata sandi guru (min. 8 karakter, masukan tersembunyi)') ?: '');

        if (! $this->validPassword($password)) {
            $this->error('Kata sandi gagal syarat validasi Laravel (minimal 8 karakter).');

            return self::FAILURE;
        }

        if ($user) {
            $user->update([
                'name' => $name,
                'role' => User::ROLE_TEACHER,
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            $user->teacher()->firstOrCreate();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => User::ROLE_TEACHER,
                'email_verified_at' => now(),
            ]);
            $user->teacher()->create();
        }

        $this->info("Akun guru berhasil: {$user->name} <{$user->email}> — silakan login di halaman masuk dengan kata sandi yang sudah disembunyikan tadi.");

        return self::SUCCESS;
    }

    private function validPassword(string $password): bool
    {
        return validator(['password' => $password], [
            'password' => Password::min(8),
        ])->validate();
    }
}
