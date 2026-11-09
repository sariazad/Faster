<?php

namespace App\Services\Akher;

use App\Models\Akher\GameList;
use App\Models\Akher\Guess;
use App\Models\Akher\ListItem;
use App\Models\Akher\Player;
use App\Models\Akher\Room;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * آخر القائمة — the server is the referee: it builds the turn order, owns the clock,
 * matches guesses and moves the turn, all inside one transaction per action.
 * Devices only ever see the items that have been revealed (the full list once the game is done).
 */
class RoomEngine
{
    public const TURN_OPTIONS = [1, 2, 3, 4];
    public const SECONDS_OPTIONS = [20, 30, 45, 60];
    public const PENALTY = 10;
    public const ROOM_HOURS = 6;
    public const MAX_PLAYERS = 30;
    public const GRACE_SECONDS = 1.5;

    // Modes
    public const TEAMS = 'teams';   // two teams with the same number of players
    public const SOLO = 'solo';     // every player is their own team

    // Guess results
    public const MATCH = 'match';
    public const NONE = 'none';
    public const DUP = 'dup';
    public const UNCLEAR = 'unclear';
    public const TIMEOUT = 'timeout';
    public const SKIP = 'skip';

    // ───────────────────────────── Rooms & players ─────────────────────────────

    public function create(string $name, string $device): array
    {
        $name = $this->cleanName($name);

        return $this->tx(function () use ($name, $device) {
            Room::where('expires_at', '<', now())->delete();

            do {
                $code = (string) random_int(1000, 9999);
            } while (Room::whereKey($code)->exists());

            $room = Room::create([
                'code' => $code,
                'host_device' => $device,
                'status' => Room::LOBBY,
                'list_id' => GameList::playable()->value('id'),
                'scores' => ['A' => 0, 'B' => 0],
                'expires_at' => now()->addHours(self::ROOM_HOURS),
            ]);

            $player = Player::create(['room_code' => $code, 'name' => $name, 'team' => 'A', 'device_id' => $device]);

            return [$room, $player];
        });
    }

    public function join(Room $room, string $name, string $device): Player
    {
        $name = $this->cleanName($name);

        return $this->tx(function () use ($room, $name, $device) {
            $room = $this->fresh($room);

            if ($existing = $room->players()->where('device_id', $device)->first()) {
                return $existing; // reconnect
            }

            if ($room->players()->count() >= self::MAX_PLAYERS) {
                throw new DomainException('room_full');
            }

            $player = Player::create([
                'room_code' => $room->code,
                'name' => $name,
                'team' => $this->smallerTeam($room),
                'device_id' => $device,
                'spectator' => $room->status !== Room::LOBBY,
            ]);
            $this->bump($room);

            return $player;
        });
    }

    /** A player without a phone: plays from the host's screen. */
    public function addOffline(Room $room, string $device, string $name, ?string $team = null): Player
    {
        $name = $this->cleanName($name);

        return $this->tx(function () use ($room, $device, $name, $team) {
            $room = $this->fresh($room);
            // No team given (solo lobby): keep the teams balanced in case the host switches back.
            $team = in_array($team, ['A', 'B'], true) ? $team : $this->smallerTeam($room);
            $this->mustBeHost($room, $device);
            $this->mustBe($room, Room::LOBBY);

            $player = Player::create(['room_code' => $room->code, 'name' => $name, 'team' => $team, 'device_id' => null]);
            $this->bump($room);

            return $player;
        });
    }

    public function switchTeam(Room $room, Player $player, string $device): void
    {
        $this->tx(function () use ($room, $player, $device) {
            $room = $this->fresh($room);
            $this->mustBe($room, Room::LOBBY);
            $this->mustControl($room, $player, $device);
            $player->update(['team' => $player->team === 'A' ? 'B' : 'A']);
            $this->bump($room);
        });
    }

    public function remove(Room $room, Player $player, string $device): void
    {
        $this->tx(function () use ($room, $player, $device) {
            $room = $this->fresh($room);
            $this->mustBe($room, Room::LOBBY);
            $this->mustControl($room, $player, $device);
            if ($player->device_id === $room->host_device) {
                throw new DomainException('host_stays');
            }
            $player->delete();
            $this->bump($room);
        });
    }

    public function settings(Room $room, string $device, array $data): void
    {
        $this->tx(function () use ($room, $device, $data) {
            $room = $this->fresh($room);
            $this->mustBeHost($room, $device);
            $this->mustBe($room, Room::LOBBY);

            if (isset($data['list_id'])) {
                if (! GameList::playable()->whereKey($data['list_id'])->exists()) {
                    throw new DomainException('bad_list');
                }
                $room->list_id = $data['list_id'];
            }
            if (isset($data['turns_per_player']) && in_array((int) $data['turns_per_player'], self::TURN_OPTIONS, true)) {
                $room->turns_per_player = (int) $data['turns_per_player'];
            }
            if (isset($data['turn_seconds']) && in_array((int) $data['turn_seconds'], self::SECONDS_OPTIONS, true)) {
                $room->turn_seconds = (int) $data['turn_seconds'];
            }
            if (isset($data['mode']) && in_array($data['mode'], [self::TEAMS, self::SOLO], true)) {
                $room->mode = $data['mode'];
            }
            if (array_key_exists('wrong_penalty', $data)) {
                $room->wrong_penalty = (bool) $data['wrong_penalty'];
            }
            $this->bump($room);
        });
    }

    // ───────────────────────────── Game flow ─────────────────────────────

    /** Solo: every player in join order, round after round. */
    public static function buildSoloOrder(array $players, int $turnsPerPlayer): array
    {
        $order = [];
        for ($k = 0; $k < $turnsPerPlayer; $k++) {
            array_push($order, ...$players);
        }

        return $order;
    }

    /** Where a player's points go: their team, or (solo) their own id. */
    public static function scoreKey(Room $room, Player $player): string
    {
        return $room->mode === self::SOLO ? (string) $player->id : $player->team;
    }

    /** Builds the order once: A[0], B[0], A[1], B[1]… each team cycling through its members. */
    public static function buildOrder(array $teamA, array $teamB, int $turnsPerPlayer): array
    {
        $n = $turnsPerPlayer * max(count($teamA), count($teamB));
        $order = [];
        for ($k = 0; $k < $n; $k++) {
            $order[] = $teamA[$k % count($teamA)];
            $order[] = $teamB[$k % count($teamB)];
        }

        return $order;
    }

    public function start(Room $room, string $device): void
    {
        $this->tx(function () use ($room, $device) {
            $room = $this->fresh($room);
            $this->mustBeHost($room, $device);
            $this->mustBe($room, Room::LOBBY);

            $players = $room->players()->where('spectator', false)->get();

            if ($room->mode === self::SOLO) {
                if ($players->count() < 2) {
                    throw new DomainException('need_two');
                }
                $ids = $players->pluck('id')->values()->all();
                $order = self::buildSoloOrder($ids, $room->turns_per_player);
                $scores = array_fill_keys(array_map('strval', $ids), 0);
            } else {
                $a = $players->where('team', 'A')->pluck('id')->values()->all();
                $b = $players->where('team', 'B')->pluck('id')->values()->all();
                if (! $a || ! $b) {
                    throw new DomainException('empty_team');
                }
                if (count($a) !== count($b)) {
                    throw new DomainException('uneven_teams'); // fair totals: same number of players
                }
                $order = self::buildOrder($a, $b, $room->turns_per_player);
                $scores = ['A' => 0, 'B' => 0];
            }

            if (! $room->list_id || ! GameList::playable()->whereKey($room->list_id)->exists()) {
                throw new DomainException('bad_list');
            }

            $room->order = $order;
            $room->status = Room::PLAYING;
            $room->turn_idx = 0;
            $room->scores = $scores;
            $room->turn_deadline = $this->deadline($room);
            $room->expires_at = now()->addHours(self::ROOM_HOURS);
            $this->bump($room);
        });
    }

    /**
     * @return array{result: string, rank: ?int, points: int, name: ?string}
     */
    public function guess(Room $room, string $device, int $turnIdx, string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || mb_strlen($text) > 80) {
            throw new DomainException('bad_text');
        }

        return $this->tx(function () use ($room, $device, $turnIdx, $text) {
            $room = $this->fresh($room);
            $this->mustBe($room, Room::PLAYING);

            if ($room->turn_idx !== $turnIdx) {
                throw new DomainException('not_your_turn');
            }

            $player = Player::findOrFail($room->currentPlayerId());
            $allowed = $player->device_id === $device || ($player->device_id === null && $device === $room->host_device);
            if (! $allowed) {
                throw new DomainException('not_your_turn');
            }
            if (now()->greaterThan($room->turn_deadline->copy()->addMilliseconds((int) (self::GRACE_SECONDS * 1000)))) {
                throw new DomainException('time_up');
            }

            $list = $room->list()->with('items')->firstOrFail();
            $m = $list->matcher()->match($text);
            $result = $m['result'];
            $rank = $m['rank'];
            $points = 0;

            if ($result === Matcher::MATCH) {
                $taken = Guess::where('room_code', $room->code)->where('round', $room->round)
                    ->where('result', self::MATCH)->where('item_rank', $rank)->exists();
                if ($taken) {
                    $result = self::DUP;
                } else {
                    $points = $rank;
                }
            } elseif ($result === Matcher::NONE && $room->wrong_penalty) {
                $points = -min(self::PENALTY, (int) ($room->scores[self::scoreKey($room, $player)] ?? 0)); // never below zero
            }

            Guess::create([
                'room_code' => $room->code,
                'round' => $room->round,
                'turn_idx' => $room->turn_idx,
                'player_id' => $player->id,
                'player_name' => $player->name,
                'team' => $player->team,
                'text' => $text,
                'result' => $result,
                'item_rank' => in_array($result, [self::MATCH, self::DUP], true) ? $rank : null,
                'points' => $points,
            ]);

            if ($points !== 0) {
                $scores = $room->scores;
                $key = self::scoreKey($room, $player);
                $scores[$key] = max(0, ($scores[$key] ?? 0) + $points);
                $room->scores = $scores;
            }

            // match / none end the turn; dup / unclear let the player try again on the same clock.
            if (in_array($result, [self::MATCH, self::NONE], true)) {
                $this->advance($room);
            }
            $this->bump($room);

            $item = $rank ? $list->items->firstWhere('rank', $rank) : null;

            return ['result' => $result, 'rank' => $result === self::MATCH ? $rank : null, 'points' => $points, 'name' => $result === self::MATCH ? $item?->name_ar : null];
        });
    }

    /** Any device may call this once the clock runs out; the server checks the time itself. */
    public function expire(Room $room, int $turnIdx): bool
    {
        return $this->tx(function () use ($room, $turnIdx) {
            $room = $this->fresh($room);
            if ($room->status !== Room::PLAYING || $room->turn_idx !== $turnIdx || now()->lessThan($room->turn_deadline)) {
                return false;
            }
            $this->closeTurn($room, self::TIMEOUT);

            return true;
        });
    }

    public function skip(Room $room, string $device): void
    {
        $this->tx(function () use ($room, $device) {
            $room = $this->fresh($room);
            $this->mustBeHost($room, $device);
            $this->mustBe($room, Room::PLAYING);
            $this->closeTurn($room, self::SKIP);
        });
    }

    public function end(Room $room, string $device): void
    {
        $this->tx(function () use ($room, $device) {
            $room = $this->fresh($room);
            $this->mustBeHost($room, $device);
            $this->mustBe($room, Room::PLAYING);
            $room->status = Room::DONE;
            $room->turn_deadline = null;
            $this->bump($room);
        });
    }

    /** New round with the same players (back to the lobby; spectators join in). */
    public function reset(Room $room, string $device): void
    {
        $this->tx(function () use ($room, $device) {
            $room = $this->fresh($room);
            $this->mustBeHost($room, $device);
            $room->status = Room::LOBBY;
            $room->round++;
            $room->order = null;
            $room->turn_idx = 0;
            $room->turn_deadline = null;
            $room->scores = ['A' => 0, 'B' => 0];
            $room->expires_at = now()->addHours(self::ROOM_HOURS);
            $room->players()->update(['spectator' => false]);
            $this->bump($room);
        });
    }

    public function leave(Room $room, string $device): void
    {
        $this->tx(function () use ($room, $device) {
            $room = $this->fresh($room);
            if ($room->status === Room::LOBBY && $device !== $room->host_device) {
                $room->players()->where('device_id', $device)->delete();
                $this->bump($room);
            }
        });
    }

    // ───────────────────────────── State for devices ─────────────────────────────

    public function state(Room $room, string $device): array
    {
        $room->loadMissing('players');
        $list = $room->list_id ? GameList::withCount('items')->find($room->list_id) : null;
        $guesses = Guess::where('room_code', $room->code)->where('round', $room->round)->orderBy('id')->get();
        $matched = $guesses->where('result', self::MATCH);
        $items = $list && ($matched->isNotEmpty() || $room->status === Room::DONE)
            ? ListItem::where('list_id', $list->id)->when($room->status !== Room::DONE, fn ($q) => $q->whereIn('rank', $matched->pluck('item_rank')))->get()->keyBy('rank')
            : collect();

        $isHost = $device === $room->host_device;
        $mine = $room->players->filter(fn ($p) => $p->device_id === $device || ($isHost && $p->device_id === null))->pluck('id')->values();

        $state = [
            'server_now' => (int) round(microtime(true) * 1000),
            'version' => $room->version,
            'code' => $room->code,
            'status' => $room->status,
            'mode' => $room->mode ?? self::TEAMS,
            'round' => $room->round,
            'is_host' => $isHost,
            'my_players' => $mine,
            'list' => $list ? ['id' => $list->id, 'title' => $list->title_ar, 'extra_label' => $list->extra_label, 'hint' => $list->unit_hint, 'count' => $list->items_count] : null,
            'settings' => ['turns_per_player' => $room->turns_per_player, 'turn_seconds' => $room->turn_seconds, 'wrong_penalty' => $room->wrong_penalty],
            'turn_idx' => $room->turn_idx,
            'turns_total' => count($room->order ?? []),
            'current_player' => $room->currentPlayerId(),
            'deadline' => $room->turn_deadline ? (int) round($room->turn_deadline->getPreciseTimestamp(3)) : null,
            'scores' => (object) ($room->scores ?? ['A' => 0, 'B' => 0]),
            'players' => $room->players->map(fn (Player $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'team' => $p->team,
                'offline' => $p->device_id === null,
                'host' => $p->device_id !== null && $p->device_id === $room->host_device,
                'mine' => $p->device_id === $device,
                'spectator' => $p->spectator,
            ])->values(),
            'guesses' => $guesses->map(fn (Guess $g) => [
                'id' => $g->id,
                'player_id' => $g->player_id,
                'player' => $g->player_name,
                'team' => $g->team,
                'text' => $g->text,
                'result' => $g->result,
                'rank' => $g->item_rank,
                'points' => $g->points,
                'name' => $g->item_rank ? $items->get($g->item_rank)?->name_ar : null,
            ])->values(),
            'revealed' => $matched->map(fn (Guess $g) => [
                'rank' => $g->item_rank,
                'team' => $g->team,
                'player_id' => $g->player_id,
                'player' => $g->player_name,
                'name' => $items->get($g->item_rank)?->name_ar,
                'extra' => $items->get($g->item_rank)?->extra,
            ])->sortBy('rank')->values(),
            'full_list' => $room->status === Room::DONE
                ? $items->sortBy('rank')->map(fn (ListItem $i) => ['rank' => $i->rank, 'name' => $i->name_ar, 'extra' => $i->extra])->values()
                : null,
        ];

        if ($isHost && $room->status === Room::LOBBY) {
            $state['lists'] = GameList::playable()->get()->map(fn (GameList $l) => ['id' => $l->id, 'title' => $l->title_ar, 'count' => $l->items_count])->values();
        }

        return $state;
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    private function closeTurn(Room $room, string $result): void
    {
        $player = Player::find($room->currentPlayerId());
        Guess::create([
            'room_code' => $room->code,
            'round' => $room->round,
            'turn_idx' => $room->turn_idx,
            'player_id' => $player?->id,
            'player_name' => $player?->name ?? '—',
            'team' => $player?->team ?? 'A',
            'text' => '',
            'result' => $result,
            'points' => 0,
        ]);
        $this->advance($room);
        $this->bump($room);
    }

    private function advance(Room $room): void
    {
        $room->turn_idx++;
        if ($room->turn_idx >= count($room->order ?? [])) {
            $room->status = Room::DONE;
            $room->turn_deadline = null;
        } else {
            $room->turn_deadline = $this->deadline($room);
        }
    }

    private function deadline(Room $room): Carbon
    {
        return now()->addSeconds($room->turn_seconds);
    }

    private function bump(Room $room): void
    {
        $room->touchVersion();
        $room->save();
    }

    private function fresh(Room $room): Room
    {
        return Room::findOrFail($room->code);
    }

    private function smallerTeam(Room $room): string
    {
        $counts = $room->players()->selectRaw('team, count(*) as n')->groupBy('team')->pluck('n', 'team');

        return ($counts['B'] ?? 0) < ($counts['A'] ?? 0) ? 'B' : 'A';
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)));
        if ($name === '') {
            throw new DomainException('bad_name');
        }

        return mb_substr($name, 0, 20);
    }

    private function mustBeHost(Room $room, string $device): void
    {
        if ($device !== $room->host_device) {
            throw new DomainException('host_only');
        }
    }

    private function mustControl(Room $room, Player $player, string $device): void
    {
        if ($player->room_code !== $room->code || ($player->device_id !== $device && $device !== $room->host_device)) {
            throw new DomainException('not_allowed');
        }
    }

    private function mustBe(Room $room, string $status): void
    {
        if ($room->status !== $status) {
            throw new DomainException('wrong_state');
        }
    }

    private function tx(callable $fn): mixed
    {
        return DB::connection('akher')->transaction($fn, 3);
    }
}
