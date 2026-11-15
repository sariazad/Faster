<?php

namespace Tests\Feature;

use App\Http\Controllers\AkherController;
use App\Models\Akher\GameList;
use App\Models\Akher\Room;
use App\Services\Akher\RoomEngine;
use Database\Seeders\AkherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AkherRoomTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'host-device-1';
    private const PHONE = 'phone-device-2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AkherSeeder::class);
    }

    private function api(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->json($method, '/akher/api'.$uri, $data);
    }

    /** Host (team A) + phone player (team B), game started on the area list. */
    private function startedRoom(array $settings = []): string
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->assertOk()->json('code');
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_id' => 'area'] + $settings)->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();

        return $code;
    }

    private function state(string $code, string $device = self::HOST): array
    {
        return $this->getJson("/akher/api/rooms/$code?device=$device")->assertOk()->json();
    }

    public function test_lists_live_in_their_own_database(): void
    {
        $this->assertSame('akher', (new GameList)->getConnectionName());
        $this->assertSame(7, DB::connection('akher')->table('lists')->count());
        $this->assertSame(576, DB::connection('akher')->table('list_items')->count());
        $this->artisan('akher:validate-lists')->assertSuccessful();
    }

    public function test_turn_order_alternates_teams_and_smaller_team_repeats(): void
    {
        $this->assertSame([1, 9, 2, 9, 3, 9, 1, 9, 2, 9, 3, 9], RoomEngine::buildOrder([1, 2, 3], [9], 2));
    }

    public function test_solo_order_repeats_players_each_round(): void
    {
        $this->assertSame([5, 7, 9, 5, 7, 9], RoomEngine::buildSoloOrder([5, 7, 9], 2));
    }

    public function test_teams_must_have_the_same_number_of_players(): void
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/players", ['device' => self::HOST, 'name' => 'جدّو', 'team' => 'A'])->assertOk();
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'turns_per_player' => 1])->assertOk();

        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertStatus(422)->assertJsonPath('message', 'uneven_teams');

        $this->api('POST', "/rooms/$code/players", ['device' => self::HOST, 'name' => 'ستّو', 'team' => 'B'])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();
        $this->assertSame(4, $this->state($code)['turns_total']);
    }

    public function test_solo_mode_every_player_scores_alone(): void
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_id' => 'area', 'mode' => 'solo', 'wrong_penalty' => true, 'turns_per_player' => 1])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertStatus(422)->assertJsonPath('message', 'need_two');

        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $grandpa = $this->api('POST', "/rooms/$code/players", ['device' => self::HOST, 'name' => 'جدّو'])->assertOk()->json('player');
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk(); // 3 players, uneven teams don't matter

        $s = $this->state($code);
        $this->assertSame('solo', $s['mode']);
        $this->assertSame(3, $s['turns_total']);
        [$sara, $khalid] = [$s['players'][0]['id'], $s['players'][1]['id']];

        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'تونس'])->assertOk()->assertJsonPath('points', 91);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'كندا'])->assertOk()->assertJsonPath('points', 2);
        $this->assertSame($grandpa, $this->state($code)['current_player']);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 2, 'text' => 'مالطا'])->assertOk()->assertJsonPath('points', 0);

        $end = $this->state($code);
        $this->assertSame('done', $end['status']);
        $this->assertSame([(string) $sara => 91, (string) $khalid => 2, (string) $grandpa => 0], $end['scores']);
        $this->assertSame($khalid, collect($end['revealed'])->firstWhere('name', 'كندا')['player_id']);
    }

    public function test_host_can_run_the_game_without_playing(): void
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'host_plays' => false, 'turns_per_player' => 1])->assertOk();
        $this->assertFalse($this->state($code)['settings']['host_plays']);

        // With the host out, the next two players fill A and B.
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/join", ['name' => 'ليلى', 'device' => 'phone-device-3'])->assertOk();
        $s = $this->state($code);
        $this->assertSame(['A', 'B'], collect($s['players'])->where('host', false)->pluck('team')->values()->all());

        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();
        $s = $this->state($code);
        $this->assertSame(2, $s['turns_total']);
        $this->assertNotSame($s['players'][0]['id'], $s['current_player'], 'the host never gets a turn');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'كندا'])->assertStatus(422)->assertJsonPath('message', 'not_your_turn');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 0, 'text' => 'كندا'])->assertOk();

        // A new round keeps the host out.
        $this->api('POST', "/rooms/$code/reset", ['device' => self::HOST])->assertOk();
        $this->assertTrue($this->state($code)['players'][0]['spectator']);

        // Switching back puts the host in the game again.
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'host_plays' => true])->assertOk();
        $this->assertFalse($this->state($code)['players'][0]['spectator']);
    }

    public function test_more_turns_and_a_cleared_list_ends_the_round(): void
    {
        $fruit = ['تفاح', 'موز', 'برتقال', 'عنب', 'رمان', 'مشمش', 'خوخ', 'كرز', 'تين', 'ليمون'];
        \App\Models\Akher\GameList::fromFile(['id' => 'tiny', 'title_ar' => 'قائمة صغيرة', 'items' => array_map(
            fn ($name, $i) => ['rank' => $i + 1, 'name_ar' => $name, 'aliases' => []], $fruit, array_keys($fruit))]);
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'turns_per_player' => 8, 'list_id' => 'tiny'])->assertOk();
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();
        $this->assertSame(16, $this->state($code)['turns_total']);

        foreach ($fruit as $i => $name) {
            $this->assertSame('playing', $this->state($code)['status']);
            $this->api('POST', "/rooms/$code/guess", ['device' => $i % 2 ? self::PHONE : self::HOST, 'turn_idx' => $i, 'text' => $name])
                ->assertOk()->assertJsonPath('result', 'match');
        }
        $this->assertSame('done', $this->state($code)['status'], 'nothing left to find, so the round is over (10 of 16 turns)');
    }

    private function smallList(string $id, array $names): void
    {
        \App\Models\Akher\GameList::fromFile(['id' => $id, 'title_ar' => "قائمة {$id}", 'items' => array_map(
            fn ($name, $i) => ['rank' => $i + 1, 'name_ar' => $name, 'aliases' => []], $names, array_keys($names))]);
    }

    public function test_series_of_lists_adds_up_and_the_other_side_starts_the_next_list(): void
    {
        $this->smallList('fruit', ['تفاح', 'موز', 'برتقال', 'عنب', 'رمان', 'مشمش', 'خوخ', 'كرز', 'تين', 'ليمون']);
        $this->smallList('veg', ['بطاطا', 'جزر', 'خيار', 'بندورة', 'بصل', 'ثوم', 'كوسا', 'باذنجان', 'فلفل', 'ملفوف']);

        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_ids' => ['fruit', 'nope']])->assertStatus(422);
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_ids' => ['fruit', 'veg'], 'turns_per_player' => 1])->assertOk();
        $this->assertSame(['fruit', 'veg'], $this->state($code)['settings']['list_ids']);
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();

        // List 1: سارة (A) finds rank 10, خالد (B) finds rank 2.
        $s = $this->state($code);
        [$sara, $khalid] = [$s['players'][0]['id'], $s['players'][1]['id']];
        $this->assertSame('fruit', $s['list']['id']);
        $this->assertSame($sara, $s['current_player']);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'ليمون'])->assertOk();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'موز'])->assertOk();

        $s = $this->state($code);
        $this->assertSame('break', $s['status']);
        $this->assertCount(10, $s['full_list'], 'the finished list is shown in full during the break');
        $this->assertSame(['A' => 10, 'B' => 2], (array) $s['series']['totals']);
        $this->api('POST', "/rooms/$code/next", ['device' => self::PHONE])->assertStatus(422)->assertJsonPath('message', 'host_only');

        // List 2: the other side starts.
        $this->api('POST', "/rooms/$code/next", ['device' => self::HOST])->assertOk();
        $s = $this->state($code);
        $this->assertSame(['playing', 'veg', $khalid, 1], [$s['status'], $s['list']['id'], $s['current_player'], $s['series']['idx']]);
        $this->assertSame([], $s['revealed']);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 0, 'text' => 'ملفوف'])->assertOk();
        $this->assertSame(['A' => 10, 'B' => 12], (array) $this->state($code)['series']['totals'], 'running total while playing');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 1, 'text' => 'جزر'])->assertOk();

        $s = $this->state($code);
        $this->assertSame('done', $s['status']);
        $this->assertSame(['A' => 12, 'B' => 12], (array) $s['series']['totals']);
        $this->assertSame(['قائمة fruit', 'قائمة veg'], array_column($s['series']['results'], 'title'));

        // A new round starts the series again from the first list.
        $this->api('POST', "/rooms/$code/reset", ['device' => self::HOST])->assertOk();
        $s = $this->state($code);
        $this->assertSame(['lobby', 'fruit', []], [$s['status'], $s['list']['id'], $s['series']['results']]);
    }

    public function test_host_can_stop_a_series_during_the_break(): void
    {
        $this->smallList('fruit', ['تفاح', 'موز', 'برتقال', 'عنب', 'رمان', 'مشمش', 'خوخ', 'كرز', 'تين', 'ليمون']);
        $this->smallList('veg', ['بطاطا', 'جزر', 'خيار', 'بندورة', 'بصل', 'ثوم', 'كوسا', 'باذنجان', 'فلفل', 'ملفوف']);
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_ids' => ['fruit', 'veg'], 'turns_per_player' => 1])->assertOk();
        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'خوخ'])->assertOk();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'شي غلط'])->assertOk();
        $this->assertSame('break', $this->state($code)['status']);

        $this->api('POST', "/rooms/$code/end", ['device' => self::HOST])->assertOk();
        $s = $this->state($code);
        $this->assertSame('done', $s['status']);
        $this->assertSame(['A' => 7, 'B' => 0], (array) $s['series']['totals']);
    }

    public function test_room_flow_lobby_to_end(): void
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->assertOk()->json('code');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);

        // Only one team so far → can't start.
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertStatus(422)->assertJsonPath('message', 'empty_team');

        $this->api('POST', "/rooms/$code/join", ['name' => 'خالد', 'device' => self::PHONE])->assertOk();
        $lobby = $this->state($code, self::PHONE);
        $this->assertFalse($lobby['is_host']);
        $this->assertSame(['A', 'B'], collect($lobby['players'])->pluck('team')->all());

        // Non-host can't change settings or start.
        $this->api('POST', "/rooms/$code/start", ['device' => self::PHONE])->assertStatus(422)->assertJsonPath('message', 'host_only');
        $this->api('POST', "/rooms/$code/settings", ['device' => self::HOST, 'list_id' => 'area', 'turns_per_player' => 1, 'turn_seconds' => 30])->assertOk();
        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();

        $s = $this->state($code);
        $this->assertSame('playing', $s['status']);
        $this->assertSame(2, $s['turns_total']);
        $this->assertNull($s['full_list'], 'the list stays hidden while playing');

        // Sara (host, team A): the phone can't answer for her.
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 0, 'text' => 'تونس'])->assertStatus(422)->assertJsonPath('message', 'not_your_turn');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'تونس'])
            ->assertOk()->assertJsonPath('result', 'match')->assertJsonPath('rank', 91)->assertJsonPath('points', 91);

        // Khalid: "unclear" keeps the turn, then a duplicate keeps it too, then a miss ends it.
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'جنوب'])->assertOk()->assertJsonPath('result', 'unclear');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'تونس'])->assertOk()->assertJsonPath('result', 'dup');
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'مالطا'])->assertOk()->assertJsonPath('result', 'none');

        $end = $this->state($code, self::PHONE);
        $this->assertSame('done', $end['status']);
        $this->assertSame(['A' => 91, 'B' => 0], $end['scores']);
        $this->assertCount(100, $end['full_list']);
        $this->assertSame('تونس', $end['revealed'][0]['name']);
        $this->assertCount(4, $end['guesses']);

        // New round with the same players.
        $this->api('POST', "/rooms/$code/reset", ['device' => self::HOST])->assertOk();
        $again = $this->state($code);
        $this->assertSame('lobby', $again['status']);
        $this->assertSame(2, $again['round']);
        $this->assertSame([], $again['guesses']);
    }

    public function test_only_revealed_items_are_sent_while_playing(): void
    {
        $code = $this->startedRoom();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'روسيا'])->assertOk();

        $s = $this->state($code, self::PHONE);
        $this->assertSame([['rank' => 1, 'team' => 'A', 'player_id' => $s['players'][0]['id'], 'player' => 'سارة', 'name' => 'روسيا', 'extra' => '17,098,246']], $s['revealed']);
        $this->assertNull($s['full_list']);
        $this->assertStringNotContainsString('كندا', json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    public function test_timeout_is_checked_by_the_server(): void
    {
        $code = $this->startedRoom();

        $this->api('POST', "/rooms/$code/expire", ['turn_idx' => 0])->assertOk()->assertJsonPath('expired', false);

        $this->travel(33)->seconds();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'روسيا'])->assertStatus(422)->assertJsonPath('message', 'time_up');
        $this->api('POST', "/rooms/$code/expire", ['turn_idx' => 0])->assertOk()->assertJsonPath('expired', true);
        $this->api('POST', "/rooms/$code/expire", ['turn_idx' => 0])->assertOk()->assertJsonPath('expired', false);

        $s = $this->state($code);
        $this->assertSame(1, $s['turn_idx']);
        $this->assertSame('timeout', $s['guesses'][0]['result']);
    }

    public function test_wrong_penalty_never_goes_below_zero(): void
    {
        $code = $this->startedRoom(['wrong_penalty' => true, 'turns_per_player' => 2]);

        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'مالطا'])->assertOk()->assertJsonPath('points', 0);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 1, 'text' => 'تونس'])->assertOk();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 2, 'text' => 'كندا'])->assertOk()->assertJsonPath('points', 2);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::PHONE, 'turn_idx' => 3, 'text' => 'النمسا'])->assertOk()->assertJsonPath('points', -10);

        $this->assertSame(['A' => 2, 'B' => 81], $this->state($code)['scores']);
    }

    public function test_offline_player_plays_from_the_host_screen(): void
    {
        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->api('POST', "/rooms/$code/players", ['device' => self::PHONE, 'name' => 'جدّو', 'team' => 'B'])->assertStatus(422);
        $player = $this->api('POST', "/rooms/$code/players", ['device' => self::HOST, 'name' => 'جدّو', 'team' => 'B'])->assertOk()->json('player');

        $this->api('POST', "/rooms/$code/start", ['device' => self::HOST])->assertOk();
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 0, 'text' => 'كندا'])->assertOk();
        $this->assertSame($player, $this->state($code)['current_player']);
        $this->api('POST', "/rooms/$code/guess", ['device' => self::HOST, 'turn_idx' => 1, 'text' => 'الصين'])->assertOk()->assertJsonPath('points', 4);
    }

    public function test_late_joiners_watch_until_next_round_and_reconnect_keeps_the_player(): void
    {
        $code = $this->startedRoom();

        $p1 = $this->api('POST', "/rooms/$code/join", ['name' => 'نورة', 'device' => 'late-device-3'])->assertOk()->json('player');
        $p2 = $this->api('POST', "/rooms/$code/join", ['name' => 'نورة', 'device' => 'late-device-3'])->assertOk()->json('player');
        $this->assertSame($p1, $p2);
        $this->assertTrue(collect($this->state($code)['players'])->firstWhere('id', $p1)['spectator']);

        $this->api('POST', "/rooms/$code/end", ['device' => self::HOST])->assertOk();
        $this->api('POST', "/rooms/$code/reset", ['device' => self::HOST])->assertOk();
        $this->assertFalse(collect($this->state($code)['players'])->firstWhere('id', $p1)['spectator']);
    }

    public function test_polling_returns_unchanged_when_nothing_happened(): void
    {
        $code = $this->startedRoom();
        $version = $this->state($code)['version'];

        $this->getJson("/akher/api/rooms/$code?device=x&since=$version")->assertOk()->assertJsonPath('unchanged', true);
        $this->api('POST', "/rooms/$code/skip", ['device' => self::HOST])->assertOk();
        $this->assertArrayNotHasKey('unchanged', $this->getJson("/akher/api/rooms/$code?device=x&since=$version")->json());
    }

    public function test_expired_or_unknown_rooms_are_gone(): void
    {
        $this->getJson('/akher/api/rooms/0000?device=abcdefgh')->assertNotFound();

        $code = $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->json('code');
        $this->travel(7)->hours();
        $this->getJson("/akher/api/rooms/$code?device=abcdefgh")->assertNotFound();
        $this->api('POST', '/rooms', ['name' => 'سارة', 'device' => self::HOST])->assertOk();
        $this->assertFalse(Room::whereKey($code)->exists() && Room::find($code)->expires_at->isPast());
    }

    public function test_pages_load(): void
    {
        foreach (AkherController::PAGES as $page) {
            $this->get($page === 'home' ? '/akher' : "/akher/$page")->assertOk()->assertSee('القائمة', false);
        }
        $this->get('/akher/room/1234')->assertOk();
        $this->get('/')->assertSee('آخر القائمة', false);
    }

    public function test_admin_list_editor_loads(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $this->get('/admin/akher-lists')->assertOk()->assertSee('أكبر 100 دولة بالمساحة');
        $this->get('/admin/akher-lists/area/edit')->assertOk();
    }
}
