<?php

namespace Tests\Feature;

use App\Models\PlayLog;
use App\Models\Qaddaha\Category;
use App\Models\Qaddaha\Intent;
use App\Models\Qaddaha\Question;
use App\Models\Qaddaha\Room;
use App\Models\User;
use App\Services\PlayStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaddahaTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_A = 'phone-device-aaaa';

    private const PHONE_B = 'phone-device-bbbb';

    public function test_the_original_bank_is_loaded_into_its_own_database(): void
    {
        $this->assertSame(12, Category::count());
        $this->assertGreaterThanOrEqual(144, Question::count());
        $this->assertSame(0, Question::where('level', '!=', 200)->where('level', '!=', 400)->where('level', '!=', 600)->count());
        foreach (Question::all() as $q) {
            $this->assertGreaterThanOrEqual(8, count($q->answers), $q->text);
        }
        foreach (Category::withLevelCounts()->get() as $c) {
            $this->assertGreaterThanOrEqual(4, min($c->q200_count, $c->q400_count, $c->q600_count), $c->name);
        }
        $this->assertSame('qaddaha', (new Question)->getConnectionName());
    }

    public function test_pages_render(): void
    {
        $this->get('/qaddaha')->assertOk()->assertSee('window.QD', false);
        $this->get('/qaddaha/join/ABC234')->assertOk()->assertSee('ABC234');
        $this->get('/qaddaha/join/abc')->assertNotFound();
        $this->get('/')->assertOk()->assertSee('قدّها؟');
    }

    public function test_categories_and_a_full_board_of_questions(): void
    {
        $cats = $this->getJson('/qaddaha/api/categories')->assertOk()->json('categories');
        $this->assertCount(12, $cats);

        $ids = array_column(array_slice($cats, 0, 4), 'id');
        $r = $this->postJson('/qaddaha/api/questions', ['cats' => $ids, 'levels' => [200, 400, 600]])->assertOk()->json();

        $all = [];
        foreach ($ids as $id) {
            foreach ([200, 400, 600] as $level) {
                $this->assertCount(2, $r['questions'][$id][$level]);
                foreach ($r['questions'][$id][$level] as $q) {
                    $this->assertSame($level, $q['level']);
                    $this->assertGreaterThanOrEqual(8, count($q['answers']));
                    $all[] = $q['id'];
                }
            }
        }
        $this->assertSame(600, $r['final']['level']);
        $this->assertNotContains(Question::find($r['final']['id'])->category_id, $ids); // final comes from another category
        $all[] = $r['final']['id'];
        $this->assertCount(25, array_unique($all));
        $this->assertSame(25, Question::where('times_used', '>', 0)->count());
    }

    public function test_fresh_questions_come_first_and_excluded_ones_are_skipped(): void
    {
        $cat = Category::first();
        $first = $this->postJson('/qaddaha/api/questions', ['cats' => [$cat->id, Category::skip(1)->first()->id], 'levels' => [200]])->json('questions.'.$cat->id.'.200');
        $second = $this->postJson('/qaddaha/api/questions', ['cats' => [$cat->id, Category::skip(1)->first()->id], 'levels' => [200]])->json('questions.'.$cat->id.'.200');
        $this->assertEmpty(array_intersect(array_column($first, 'id'), array_column($second, 'id')), 'least used first');

        $ids = Question::where('category_id', $cat->id)->where('level', 400)->pluck('id')->all();
        $picked = Question::pick($cat->id, 400, 2, array_slice($ids, 0, 2));
        $this->assertEmpty(array_intersect($picked->pluck('id')->all(), array_slice($ids, 0, 2)));
    }

    public function test_questions_validation(): void
    {
        $this->postJson('/qaddaha/api/questions', ['cats' => [1], 'levels' => [200]])->assertStatus(422);
        $this->postJson('/qaddaha/api/questions', ['cats' => [1, 2], 'levels' => [300]])->assertStatus(422);
        $this->postJson('/qaddaha/api/questions', ['cats' => [1, 999], 'levels' => [200]])->assertStatus(422);
    }

    private function room(): array
    {
        return $this->postJson('/qaddaha/api/rooms')->assertOk()->json();
    }

    public function test_room_codes_avoid_look_alike_characters(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]{6}$/', Room::newCode());
        }
        ['code' => $code, 'token' => $token] = $this->room();
        $this->assertNotSame($token, Room::find($code)->host_token, 'only the hash is stored');
    }

    public function test_a_seat_belongs_to_the_first_phone_and_the_same_phone_can_come_back(): void
    {
        ['code' => $code, 'token' => $token] = $this->room();

        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_A, 'team' => 0])->assertOk()->assertJson(['team' => 0]);
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_B, 'team' => 0])->assertStatus(409)->assertJson(['message' => 'seat_taken']);
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_A, 'team' => 0])->assertOk();          // rejoin
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_A, 'team' => 1])->assertStatus(409);   // one team per phone
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_B, 'team' => 1])->assertOk();

        $this->getJson("/qaddaha/api/rooms/$code/host?token=$token&after=0")->assertOk()->assertJson(['seats' => [true, true]]);

        // the host can free a seat for a new phone
        $this->postJson("/qaddaha/api/rooms/$code/seats/0/free", ['token' => 'wrong'])->assertStatus(403);
        $this->postJson("/qaddaha/api/rooms/$code/seats/0/free", ['token' => $token])->assertOk()->assertJson(['seats' => [false, true]]);
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => 'phone-device-cccc', 'team' => 0])->assertOk();
    }

    public function test_only_the_host_writes_the_state_and_phones_see_the_public_copy(): void
    {
        ['code' => $code, 'token' => $token] = $this->room();
        $full = ['phase' => 'final_bet', 'final' => ['bets' => [300, 50]]];
        $public = ['phase' => 'final_bet', 'final' => ['bets' => [null, null]]];

        $this->postJson("/qaddaha/api/rooms/$code/state", ['token' => 'nope', 'state' => $full, 'public' => $public])->assertStatus(403);
        $v = $this->postJson("/qaddaha/api/rooms/$code/state", ['token' => $token, 'state' => $full, 'public' => $public])->assertOk()->json('version');

        $phone = $this->getJson("/qaddaha/api/rooms/$code?device=".self::PHONE_A.'&since=-1')->assertOk()->json();
        $this->assertSame($public, $phone['public']);
        $this->assertArrayNotHasKey('state', $phone);
        $this->getJson("/qaddaha/api/rooms/$code?device=".self::PHONE_A."&since=$v")->assertOk()->assertJson(['same' => true]);

        // a reloaded host gets its full state back
        $this->getJson("/qaddaha/api/rooms/$code/host?token=$token&after=0&full=1")->assertOk()->assertJson(['state' => $full]);
        $this->getJson("/qaddaha/api/rooms/$code/host?token=bad&after=0")->assertStatus(403);
    }

    public function test_intents_carry_the_seat_team_and_are_cleared_once_acknowledged(): void
    {
        ['code' => $code, 'token' => $token] = $this->room();
        $this->postJson("/qaddaha/api/rooms/$code/intent", ['device' => self::PHONE_A, 'intent' => ['id' => 'i1', 'type' => 'raise', 'value' => 4]])
            ->assertStatus(403)->assertJson(['message' => 'not_seated']);

        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_B, 'team' => 1])->assertOk();
        $this->postJson("/qaddaha/api/rooms/$code/intent", ['device' => self::PHONE_B, 'intent' => ['id' => 'i1', 'type' => 'raise', 'value' => 4, 'team' => 0, 'evil' => 'x']])
            ->assertStatus(202);

        $intents = $this->getJson("/qaddaha/api/rooms/$code/host?token=$token&after=0")->json('intents');
        $this->assertCount(1, $intents);
        $this->assertSame(1, $intents[0]['team']);
        $this->assertSame(1, $intents[0]['intent']['team'], 'the seat decides the team, not the phone');
        $this->assertArrayNotHasKey('evil', $intents[0]['intent']);

        $this->postJson("/qaddaha/api/rooms/$code/state", ['token' => $token, 'state' => ['phase' => 'auction'], 'public' => ['phase' => 'auction'], 'ack' => $intents[0]['id']])->assertOk();
        $this->assertSame(0, Intent::where('room_code', $code)->count());
    }

    public function test_unknown_or_expired_rooms(): void
    {
        $this->getJson('/qaddaha/api/rooms/ZZZZZZ?device=x&since=-1')->assertNotFound();
        ['code' => $code] = $this->room();
        Room::whereKey($code)->update(['expires_at' => now()->subMinute()]);
        $this->postJson("/qaddaha/api/rooms/$code/join", ['device' => self::PHONE_A, 'team' => 0])->assertNotFound();
        $this->room(); // creating a room clears expired ones
        $this->assertNull(Room::find($code));
    }

    public function test_statistics_beacon_shows_on_the_statistics_page(): void
    {
        $base = ['sid' => 'qd12345678', 'kind' => 'quick', 'mode' => 'multi', 'names' => ['الصقور', 'النمور']];
        $this->postJson('/qaddaha/api/play', $base + ['event' => 'start'])->assertOk();
        $this->postJson('/qaddaha/api/play', $base + ['event' => 'end', 'elapsed' => 900, 'scores' => [2400, 1800], 'played' => 16, 'winner' => 'الصقور'])->assertOk();

        $log = PlayLog::where('game', 'qaddaha')->sole();
        $this->assertTrue($log->finished);
        $this->assertSame('الصقور', $log->winner);

        $games = (new PlayStats('7d'))->perGame();
        $this->assertSame(1, $games['qaddaha']['sessions']);
        $this->assertSame(16, $games['qaddaha']['questions']);

        $this->actingAs(User::factory()->create());
        $this->get('/admin/statistics')->assertOk()->assertSee('قدّها؟');
        $this->get('/admin/qaddaha-questions')->assertOk()->assertSee('كم لعبة شعبية أو لعبة طفولة تقدر تذكر؟');
        $this->get('/admin/qaddaha-categories')->assertOk()->assertSee('جغرافيا');
        $this->get('/admin/qaddaha-questions/create')->assertOk();
    }
}
