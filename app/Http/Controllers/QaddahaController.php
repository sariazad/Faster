<?php

namespace App\Http\Controllers;

use App\Models\PlayLog;
use App\Models\Qaddaha\Category;
use App\Models\Qaddaha\Intent;
use App\Models\Qaddaha\Question;
use App\Models\Qaddaha\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * قدّها؟ — pages, the question bank, and a thin relay for multi-device games.
 *
 * The rules live in the browser (resources/js/qaddaha/engine.js). In multi-device games the
 * host's browser is the only writer: phones queue intents here, the host polls them, validates
 * them with the same reducer, applies them and publishes the new state (full copy for itself,
 * a public copy without answers or secret bets for the phones).
 */
class QaddahaController extends Controller
{
    public const ERRORS = [
        'no_room' => 'ما لقينا هالغرفة. تأكد من الكود.',
        'host_only' => 'هالشي بس لشاشة المضيف.',
        'seat_taken' => 'في جوال تاني ماسك هالفريق.',
        'not_seated' => 'انضم لفريق أول.',
        'bad_team' => 'اختار فريق.',
        'too_big' => 'الحالة كبيرة زيادة.',
    ];

    /** Max size of a stored state (bytes). */
    public const MAX_STATE = 400_000;

    public function host(): View
    {
        return view('qaddaha.host');
    }

    public function join(string $code): View
    {
        $code = strtoupper($code);
        abort_unless(preg_match('/^['.Room::ALPHABET.']{6}$/', $code), 404);

        return view('qaddaha.join', ['code' => $code]);
    }

    // ───────────────────────── question bank ─────────────────────────

    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => Category::playable()->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'count' => $c->q200_count + $c->q400_count + $c->q600_count,
            ])->values(),
        ]);
    }

    /** Questions for a whole board (two per category and level) plus the final question. */
    public function questions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cats' => ['required', 'array', 'min:2', 'max:4'],
            'cats.*' => ['integer'],
            'levels' => ['required', 'array', 'min:1', 'max:3'],
            'levels.*' => ['integer', Rule::in(Question::LEVELS)],
            'exclude' => ['nullable', 'array', 'max:2000'],
            'exclude.*' => ['integer'],
        ]);

        $exclude = $data['exclude'] ?? [];
        $cats = Category::whereIn('id', $data['cats'])->where('is_active', true)->get()->keyBy('id');
        abort_if($cats->count() !== count(array_unique($data['cats'])), 422, 'bad_category');

        $out = [];
        $chosen = [];
        foreach ($data['cats'] as $catId) {
            foreach (array_unique($data['levels']) as $level) {
                $picked = Question::pick($catId, $level, 2, $exclude)->load('category');
                abort_if($picked->count() < 2, 422, 'not_enough_questions');
                $out[$catId][$level] = $picked->map->toGame()->values();
                $chosen = array_merge($chosen, $picked->pluck('id')->all());
            }
        }

        $final = Question::pick(null, 600, 1, array_merge($exclude, $chosen), $data['cats'])->first()
            ?? Question::pick(null, 600, 1, array_merge($exclude, $chosen))->first();
        abort_unless($final, 422, 'not_enough_questions');
        $chosen[] = $final->id;

        Question::whereIn('id', $chosen)->increment('times_used');

        return response()->json(['questions' => $out, 'final' => $final->load('category')->toGame()]);
    }

    // ───────────────────────── rooms (multi-device) ─────────────────────────

    public function createRoom(): JsonResponse
    {
        Room::where('expires_at', '<', now())->get()->each(function (Room $r) {
            Intent::where('room_code', $r->code)->delete();
            $r->delete();
        });

        $token = Str::random(40);
        $room = Room::create([
            'code' => Room::newCode(),
            'host_token' => Room::hashToken($token),
            'expires_at' => now()->addHours(Room::HOURS),
        ]);

        return response()->json(['code' => $room->code, 'token' => $token]);
    }

    /** Host poll: its full saved state, who is connected, and the phones' pending intents. */
    public function hostState(Request $request, string $code): JsonResponse
    {
        $room = $this->room($code);
        $this->needHost($room, $request->query('token'));
        $after = (int) $request->query('after', 0);

        return response()->json([
            'version' => $room->version,
            'state' => $request->boolean('full') ? $room->state : null,
            'seats' => $room->seats(),
            'intents' => Intent::where('room_code', $room->code)->where('id', '>', $after)->orderBy('id')->limit(50)->get()
                ->map(fn (Intent $i) => ['id' => $i->id, 'team' => $i->team, 'intent' => $i->payload])->values(),
        ]);
    }

    /** Host publishes a new state (full + public) and acknowledges the intents it handled. */
    public function saveState(Request $request, string $code): JsonResponse
    {
        $room = $this->room($code);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'state' => ['required', 'array'],
            'public' => ['required', 'array'],
            'ack' => ['nullable', 'integer', 'min:0'],
            'finished' => ['nullable', 'boolean'],
        ]);
        $this->needHost($room, $data['token']);

        $state = json_encode($data['state'], JSON_UNESCAPED_UNICODE);
        $public = json_encode($data['public'], JSON_UNESCAPED_UNICODE);
        abort_if(strlen($state) + strlen($public) > self::MAX_STATE, 413, 'too_big');

        DB::connection('qaddaha')->transaction(function () use ($room, $data) {
            $room->state = $data['state'];
            $room->public = $data['public'];
            $room->version = $room->version + 1;
            $room->finished = (bool) ($data['finished'] ?? false);
            $room->expires_at = now()->addHours(Room::HOURS);
            $room->save();
            if (! empty($data['ack'])) {
                Intent::where('room_code', $room->code)->where('id', '<=', $data['ack'])->delete();
            }
        });

        return response()->json(['version' => $room->version]);
    }

    /** Host frees a team's seat (a leader changed phones). */
    public function freeSeat(Request $request, string $code, int $team): JsonResponse
    {
        $room = $this->room($code);
        $this->needHost($room, $request->input('token'));
        abort_unless(in_array($team, [0, 1], true), 422, 'bad_team');
        $room->update(["seat$team" => null]);

        return response()->json(['seats' => $room->fresh()->seats()]);
    }

    /** A leader's phone takes its team's seat (first device wins; the same device may come back). */
    public function joinRoom(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'team' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $team = DB::connection('qaddaha')->transaction(function () use ($code, $data) {
            $room = $this->room($code);
            $column = 'seat'.$data['team'];
            $current = $room->seatOf($data['device']);

            if ($current !== null && $current !== $data['team']) {
                abort(409, 'seat_taken'); // this phone already leads the other team
            }
            // atomic: only fills the seat if it is still empty (or already ours)
            $updated = Room::whereKey($room->code)
                ->where(fn ($q) => $q->whereNull($column)->orWhere($column, $data['device']))
                ->update([$column => $data['device']]);
            abort_if($updated === 0, 409, 'seat_taken');

            return $data['team'];
        });

        return response()->json(['team' => $team]);
    }

    /** Phone poll: the public state (or {same: true} when nothing changed). */
    public function publicState(Request $request, string $code): JsonResponse
    {
        $room = $this->room($code);
        $since = (int) $request->query('since', -1);
        $seat = $room->seatOf($request->query('device'));

        if ($since === $room->version) {
            return response()->json(['same' => true, 'version' => $room->version, 'team' => $seat, 'seats' => $room->seats()]);
        }

        return response()->json([
            'version' => $room->version,
            'public' => $room->public,
            'team' => $seat,
            'seats' => $room->seats(),
        ]);
    }

    /** A leader's phone queues an intent; the host decides whether it applies. */
    public function intent(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'intent' => ['required', 'array'],
            'intent.id' => ['required', 'string', 'max:64'],
            'intent.type' => ['required', 'string', 'max:30'],
            'intent.value' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'intent.cat' => ['nullable'],
            'intent.cell' => ['nullable', 'string', 'max:20'],
        ]);
        $room = $this->room($code);
        $team = $room->seatOf($data['device']);
        abort_if($team === null, 403, 'not_seated');

        $payload = array_intersect_key($data['intent'], array_flip(['id', 'type', 'value', 'cat', 'cell']));
        $payload['team'] = $team; // the seat decides the team, never the phone

        Intent::create(['room_code' => $room->code, 'team' => $team, 'payload' => $payload]);

        return response()->json(['ok' => true], 202);
    }

    // ───────────────────────── statistics ─────────────────────────

    public function play(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sid' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'event' => ['required', Rule::in(['start', 'round', 'end'])],
            'kind' => ['nullable', 'string', 'max:10'],
            'mode' => ['nullable', 'string', 'max:10'],
            'elapsed' => ['nullable', 'integer', 'min:0'],
            'names' => ['nullable', 'array', 'size:2'],
            'names.*' => ['nullable', 'string', 'max:40'],
            'scores' => ['nullable', 'array', 'size:2'],
            'scores.*' => ['integer'],
            'played' => ['nullable', 'integer', 'min:0', 'max:100'],
            'winner' => ['nullable', 'string', 'max:40'],
        ]);

        $log = PlayLog::firstOrNew(['game' => 'qaddaha', 'ref' => $data['sid']]);
        if ($log->finished) {
            return response()->json(['ok' => true]);
        }
        if (! $log->exists) {
            $log->started_at = now()->subSeconds(min((int) ($data['elapsed'] ?? 0), 6 * 3600));
            $log->players = 2;
        }

        $log->last_at = now();
        $log->rounds = $data['played'] ?? $log->rounds;
        $log->meta = [
            'kind' => $data['kind'] ?? null,
            'mode' => $data['mode'] ?? null,
            'names' => $data['names'] ?? [null, null],
            'scores' => $data['scores'] ?? [0, 0],
            'played' => $data['played'] ?? 0,
        ];
        if ($data['event'] === 'end') {
            $log->finished = true;
            $log->ended_at = now();
            $log->winner = $data['winner'] ?? null;
        }
        $log->save();

        return response()->json(['ok' => true]);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function room(string $code): Room
    {
        $room = Room::find(strtoupper($code));
        abort_if(! $room || $room->expires_at->isPast(), 404, 'no_room');

        return $room;
    }

    private function needHost(Room $room, mixed $token): void
    {
        abort_unless($room->isHost(is_string($token) ? $token : null), 403, 'host_only');
    }
}
