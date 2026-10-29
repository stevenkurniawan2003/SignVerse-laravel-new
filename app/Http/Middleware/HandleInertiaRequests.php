<?php

namespace App\Http\Middleware;

use App\Services\GamificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $studentData = null;

        if ($user && $user->isStudent()) {
            $student = $user->student ?? $user->student()->firstOrCreate(['user_id' => $user->id]);
            $xp = $student->totalXp();
            $level = GamificationService::levelFromXp($xp);
            $streak = GamificationService::streakInfo($user->id);

            $studentData = [
                'id' => $student->id,
                'total_xp' => $xp,
                'level' => $level,
                'current_streak' => $streak['streak'],
                'longest_streak' => (int) $student->longest_streak,
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'initials' => collect(explode(' ', $user->name))
                        ->filter()
                        ->take(2)
                        ->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->implode(''),
                ] : null,
                'student' => $studentData,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
