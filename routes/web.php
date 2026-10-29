<?php

use App\Http\Controllers\Api\SignTechApiController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\AchievementController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\ChallengeController;
use App\Http\Controllers\Student\MaterialController;
use App\Http\Controllers\Student\PracticeController;
use App\Http\Controllers\Student\ProgressController;
use App\Http\Controllers\Student\StudentDashboardController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes SignTech — Jembatan Inertia.js (PHP Laravel Backend + Vue 3)
|--------------------------------------------------------------------------
*/

// Public Landing Routes
Route::get('/', fn () => Inertia::render('Landing'))->name('home');
Route::get('/landing', fn () => Inertia::render('Landing'))->name('landing');

// Protected Application Routes (Requires Login — Data Fetched Strictly from Database)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function (Request $request, SignTechApiController $api) {
        $data = $api->dashboard($request)->getData(true);
        return Inertia::render('Dashboard', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.dashboard');

    Route::get('/learn', function (Request $request, SignTechApiController $api) {
        $data = $api->materials($request)->getData(true);
        return Inertia::render('Learn', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.learn');

    Route::get('/lesson/{id}', function (Request $request, $id, SignTechApiController $api) {
        $data = $api->getLesson($request, $id)->getData(true);
        return Inertia::render('Lesson', ($data['status'] ?? '') === 'success' ? $data : ['id' => (string) $id]);
    })->name('vue.lesson');

    Route::get('/practice', function (Request $request, SignTechApiController $api) {
        $data = $api->practiceTargets($request)->getData(true);
        return Inertia::render('Practice', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.practice');

    Route::get('/challenge', function (Request $request, SignTechApiController $api) {
        $data = $api->challenges($request)->getData(true);
        return Inertia::render('Challenge', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.challenge');

    Route::get('/achievement', function (Request $request, SignTechApiController $api) {
        $data = $api->achievements($request)->getData(true);
        return Inertia::render('Achievement', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.achievement');

    Route::get('/progress', function (Request $request, SignTechApiController $api) {
        $data = $api->dashboard($request)->getData(true);
        return Inertia::render('Progress', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.progress');

    Route::get('/classroom', function (Request $request, SignTechApiController $api) {
        $data = $api->classroom($request)->getData(true);
        return Inertia::render('Classroom', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('vue.classroom');

    Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile.edit');
});

// Landing page publik alternatif
Route::get('/landing', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// ── Area Student (Jembatan Inertia ke Vue) ───────────────────
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', function (Request $request, SignTechApiController $api) {
        $data = $api->dashboard($request)->getData(true);
        return Inertia::render('Dashboard', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('dashboard');

    Route::get('/materials', function (Request $request, SignTechApiController $api) {
        $data = $api->materials($request)->getData(true);
        return Inertia::render('Learn', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('materials.index');

    Route::get('/materials/{material}', function (Request $request, $id, SignTechApiController $api) {
        $data = $api->getLesson($request, $id)->getData(true);
        return Inertia::render('Lesson', ($data['status'] ?? '') === 'success' ? $data : ['id' => (string) $id]);
    })->name('materials.show');

    Route::get('/practice', function (Request $request, SignTechApiController $api) {
        $data = $api->practiceTargets($request)->getData(true);
        return Inertia::render('Practice', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('practice.index');

    Route::get('/practice/start/{lesson}', [PracticeController::class, 'start'])->name('practice.start');
    Route::get('/practice/{session}', [PracticeController::class, 'show'])->name('practice.show');
    Route::post('/practice/{session}/attempt', [PracticeController::class, 'attempt'])->name('practice.attempt');
    Route::post('/practice/{session}/complete', [PracticeController::class, 'complete'])->name('practice.complete');
    Route::get('/practice/{session}/result', [PracticeController::class, 'result'])->name('practice.result');

    Route::get('/challenge', function (Request $request, SignTechApiController $api) {
        $data = $api->challenges($request)->getData(true);
        return Inertia::render('Challenge', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('challenge');

    Route::get('/progress', function (Request $request, SignTechApiController $api) {
        $data = $api->dashboard($request)->getData(true);
        return Inertia::render('Progress', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('progress');

    Route::get('/achievements', function (Request $request, SignTechApiController $api) {
        $data = $api->achievements($request)->getData(true);
        return Inertia::render('Achievement', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('achievements.index');

    Route::get('/assignments', [StudentAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
    Route::post('/assignments/{assignment}/submit', [StudentAssignmentController::class, 'submit'])->name('assignments.submit');
});

// ── Area Teacher (Jembatan Inertia ke Vue) ───────────────────
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', function (Request $request, SignTechApiController $api) {
        $data = $api->classroom($request)->getData(true);
        return Inertia::render('Classroom', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('dashboard');

    Route::get('/students', function (Request $request, SignTechApiController $api) {
        $data = $api->classroom($request)->getData(true);
        return Inertia::render('Classroom', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('students.index');

    Route::get('/students/{student}', function (Request $request, $student, SignTechApiController $api) {
        $data = $api->classroom($request)->getData(true);
        return Inertia::render('Classroom', ($data['status'] ?? '') === 'success' ? $data : []);
    })->name('students.show');

    Route::get('/assignments', [TeacherAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/create', [TeacherAssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/assignments', [TeacherAssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/assignments/{assignment}', [TeacherAssignmentController::class, 'show'])->name('assignments.show');
    Route::delete('/assignments/{assignment}', [TeacherAssignmentController::class, 'destroy'])->name('assignments.destroy');
});

// ── Shared authenticated area ────────────────────────────────────────
Route::middleware(['auth', 'role:student,teacher'])->group(function () {
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/profile', fn () => Inertia::render('Profile'))->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/{path?}', fn () => redirect()->route('home'))
    ->where('path', '^(?!api).*$')
    ->name('fallback');
