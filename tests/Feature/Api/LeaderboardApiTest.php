<?php

namespace Tests\Feature\Api;

use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leaderboard XP realtime (di-poll dashboard siswa).
 */
class LeaderboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithXp(string $name, int $practiceXp): User
    {
        $user = User::factory()->student()->has(Student::factory(), 'student')->create(['name' => $name]);
        if ($practiceXp > 0) {
            PracticeSession::factory()->completed()->for($user->student)->create(['xp_earned' => $practiceXp]);
        }

        return $user;
    }

    public function test_leaderboard_ranks_students_by_total_xp_with_ties(): void
    {
        $ani = $this->studentWithXp('Ani', 50);
        $budi = $this->studentWithXp('Budi', 30);
        $cici = $this->studentWithXp('Cici', 30);
        $dodi = $this->studentWithXp('Dodi', 0);

        // XP challenge ikut dihitung: Budi naik ke peringkat 1.
        $challenge = Challenge::create([
            'challenge_date' => today(),
            'title' => 'Praktik 5 huruf',
            'category' => 'alfabet',
            'target' => 5,
            'reward_xp' => 40,
        ]);
        ChallengeProgress::create([
            'user_id' => $budi->id,
            'challenge_id' => $challenge->id,
            'progress' => 5,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($cici)->getJson('/api/leaderboard')->assertOk();

        $this->assertSame(
            [['Budi', 1, 70], ['Ani', 2, 50], ['Cici', 3, 30], ['Dodi', 4, 0]],
            collect($response->json('entries'))->map(fn (array $e) => [$e['name'], $e['rank'], $e['xp']])->all(),
        );
        $this->assertSame($cici->id, $response->json('me.user_id'));
        $this->assertTrue(collect($response->json('entries'))->firstWhere('user_id', $cici->id)['is_me']);
        $this->assertSame(4, $response->json('total'));

        // XP sama → peringkat sama.
        PracticeSession::factory()->completed()->for($dodi->student)->create(['xp_earned' => 30]);
        $ranks = collect($this->actingAs($cici)->getJson('/api/leaderboard')->json('entries'))->pluck('rank', 'name');
        $this->assertSame(3, $ranks['Cici']);
        $this->assertSame(3, $ranks['Dodi']);

        $this->assertSame($ani->student->totalXp(), collect($response->json('entries'))->firstWhere('name', 'Ani')['xp']);
    }

    public function test_leaderboard_shows_top_ten_and_still_returns_my_rank(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->studentWithXp("Siswa {$i}", 100 + $i);
        }
        $me = $this->studentWithXp('Saya', 5);

        $response = $this->actingAs($me)->getJson('/api/leaderboard')->assertOk();

        $this->assertCount(10, $response->json('entries'));
        $this->assertSame(12, $response->json('me.rank'));
    }

    public function test_leaderboard_requires_student_account(): void
    {
        $this->getJson('/api/leaderboard')->assertUnauthorized();

        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->getJson('/api/leaderboard')->assertNotFound();
    }
}
