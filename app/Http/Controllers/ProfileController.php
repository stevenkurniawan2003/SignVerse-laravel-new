<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('app');
    }

    /**
     * Halaman profil: guru mendapat profil mengajar tanpa gamifikasi,
     * siswa tetap memakai profil dengan XP, streak, dan level.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isTeacher()) {
            return Inertia::render('Profile');
        }

        $teacher = $user->teacher ?? $user->teacher()->create();

        return Inertia::render('Teacher/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'joined_at' => $user->created_at?->toIso8601String(),
            ],
            'teaching' => [
                'classes' => $teacher->classrooms()->count(),
                'students' => $teacher->studentsQuery()->count(),
                'assignments' => $teacher->assignments()->count(),
                'active_assignments' => $teacher->assignments()
                    ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
                    ->count(),
            ],
            'classes' => $teacher->classrooms()
                ->withCount('students')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'Profil berhasil diperbarui.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
