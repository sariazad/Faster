<?php

namespace Tests\Feature;

use App\Models\AlefYa\Score;
use App\Models\Category;
use App\Models\Game;
use App\Models\PlayLog;
use App\Models\User;
use App\Services\PlayStats;
use Database\Seeders\AkherSeeder;
use Database\Seeders\QuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private function akherGame(): string
    {
        $this->seed(AkherSeeder::class);
        $code = $this->json('POST', '/akher/api/rooms', ['name' => 'سارة', 'device' => 'host-1'])->assertOk()->json('code');
        $this->json('POST', "/akher/api/rooms/$code/join", ['name' => 'خالد', 'device' => 'phone-2'])->assertOk();
        $this->json('POST', "/akher/api/rooms/$code/settings", ['device' => 'host-1', 'list_id' => 'area'])->assertOk();
        $this->json('POST', "/akher/api/rooms/$code/start", ['device' => 'host-1'])->assertOk();

        return $code;
    }

    public function test_akher_game_is_logged_from_start_to_end(): void
    {
        $code = $this->akherGame();

        $log = PlayLog::sole();
        $this->assertSame(PlayLog::AKHER, $log->game);
        $this->assertFalse($log->finished);
        $this->assertSame(2, $log->players);
        $this->assertSame(['area'], $log->meta['lists']);

        $this->json('POST', "/akher/api/rooms/$code/end", ['device' => 'host-1'])->assertOk();

        $log->refresh();
        $this->assertTrue($log->finished);
        $this->assertNotNull($log->ended_at);
        $this->assertSame(1, $log->rounds);
        $this->assertSame('تعادل', $log->winner); // nobody scored
        $this->assertSame(['area'], $log->meta['played']);

        // a new round with the same room opens a new row
        $this->json('POST', "/akher/api/rooms/$code/reset", ['device' => 'host-1'])->assertOk();
        $this->json('POST', "/akher/api/rooms/$code/start", ['device' => 'host-1'])->assertOk();
        $this->assertSame(2, PlayLog::count());
        $this->assertSame(1, PlayLog::where('finished', true)->count());
    }

    public function test_alefya_team_game_beacon(): void
    {
        $base = ['sid' => 'abc123xyz789', 'mode' => 'classic', 'size' => 5, 'rounds' => 2, 'names' => ['الصقور', 'النمور'], 'correct' => [0, 0], 'wins' => [0, 0]];

        $this->postJson('/alefya/api/play', $base + ['event' => 'start', 'elapsed' => 0])->assertOk();
        $this->postJson('/alefya/api/play', ['event' => 'round', 'elapsed' => 300, 'wins' => [1, 0], 'correct' => [6, 3]] + $base)->assertOk();
        $this->postJson('/alefya/api/play', ['event' => 'end', 'elapsed' => 600, 'wins' => [2, 0], 'correct' => [12, 5]] + $base)->assertOk();
        // late repeats change nothing
        $this->postJson('/alefya/api/play', ['event' => 'round', 'wins' => [2, 1]] + $base)->assertOk();

        $log = PlayLog::sole();
        $this->assertSame(PlayLog::ALEFYA, $log->game);
        $this->assertTrue($log->finished);
        $this->assertSame('الصقور', $log->winner);
        $this->assertSame(2, $log->rounds);
        $this->assertSame([12, 5], $log->meta['correct']);

        $this->postJson('/alefya/api/play', ['sid' => 'bad id!', 'event' => 'start'])->assertStatus(422);
        $this->postJson('/alefya/api/play', ['sid' => 'abc123xyz789', 'event' => 'oops'])->assertStatus(422);
    }

    public function test_stats_gather_every_game(): void
    {
        $this->seed(QuestionBankSeeder::class);
        $ids = Category::orderBy('id')->pluck('id');
        $game = Game::start('أ', 'ب', $ids->slice(0, 3)->all(), $ids->slice(3, 3)->all());
        $game->finish();
        Game::start('ج', 'د', $ids->slice(0, 3)->all(), $ids->slice(3, 3)->all()); // still playing

        Score::create(['won' => true, 'correct' => 10, 'wrong' => 2, 'time_left' => 12]);
        Score::create(['won' => false, 'correct' => 4, 'wrong' => 5, 'time_left' => 0]);

        PlayLog::create(['game' => PlayLog::AKHER, 'started_at' => now()->subMinutes(20), 'last_at' => now()->subMinutes(5), 'ended_at' => now()->subMinutes(5), 'finished' => true, 'players' => 4, 'rounds' => 2, 'winner' => 'الفريق الأحمر', 'meta' => ['lists' => ['area', 'population'], 'played' => ['area', 'population'], 'guesses' => 16]]);
        PlayLog::create(['game' => PlayLog::ALEFYA, 'ref' => 'old', 'started_at' => now()->subDays(3)->subHours(1), 'last_at' => now()->subDays(3), 'players' => 2, 'meta' => ['names' => ['x', 'y']]]);
        PlayLog::create(['game' => PlayLog::ALEFYA, 'ref' => 'ancient', 'started_at' => now()->subDays(60), 'last_at' => now()->subDays(60), 'finished' => true]);

        $stats = new PlayStats('30d');
        $games = $stats->perGame();

        $this->assertSame(2, $games['classic']['sessions']);
        $this->assertSame(1, $games['classic']['finished']);
        $this->assertSame(2, $games['alefya_solo']['sessions']);
        $this->assertSame(1, $games['alefya_solo']['wins']);
        $this->assertSame(1, $games['akher']['sessions']);
        $this->assertSame(15 * 60, $games['akher']['avg']);
        $this->assertSame(16, $games['akher']['questions']);
        $this->assertSame(1, $games['alefya']['sessions']);      // the 60-day-old one is outside the period
        $this->assertSame(1, $games['alefya']['abandoned']);
        $this->assertSame(6, $stats->totals()['sessions']);
        $this->assertSame(7, (new PlayStats('all'))->totals()['sessions']);
        $this->assertSame(2, (new PlayStats('all'))->topLists()->count());

        foreach (array_keys(PlayStats::PERIODS) as $period) {
            $s = new PlayStats($period);
            $timeline = $s->timeline();
            $this->assertNotEmpty($timeline['labels']);
            foreach ($timeline['datasets'] as $set) {
                $this->assertCount(count($timeline['labels']), $set['data']);
            }
            $this->assertCount(24, $s->hours()['data']);
            $this->assertCount(7, $s->weekdays()['data']);
        }
    }

    public function test_statistics_page_renders(): void
    {
        $this->akherGame();
        Score::create(['won' => true, 'correct' => 10, 'wrong' => 2, 'time_left' => 12]);

        $this->actingAs(User::factory()->create());

        foreach (array_keys(PlayStats::PERIODS) as $period) {
            $this->get('/admin/statistics?period='.$period)->assertOk()->assertSee('الإحصائيات')->assertSee('آخر القائمة');
        }
        $this->get('/admin/statistics?period=nonsense')->assertOk();
    }

    public function test_duration_format(): void
    {
        $this->assertSame('—', PlayStats::duration(0));
        $this->assertSame('45 ث', PlayStats::duration(45));
        $this->assertSame('3 د 5 ث', PlayStats::duration(185));
        $this->assertSame('25 د', PlayStats::duration(1500));
        $this->assertSame('1 س 2 د', PlayStats::duration(3725));
    }
}
