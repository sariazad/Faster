<?php

namespace App\Http\Controllers;

use App\Models\Akher\Player;
use App\Models\Akher\Room;
use App\Services\Akher\RoomEngine;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * آخر القائمة — Blade pages + a small JSON API. Devices identify themselves with a random
 * device id kept in localStorage (no accounts); every rule is enforced in RoomEngine.
 */
class AkherController extends Controller
{
    public const PAGES = ['home', 'how', 'about', 'faq'];

    public const ERRORS = [
        'bad_name' => 'اكتب اسمك أول.',
        'room_full' => 'الغرفة مليانة.',
        'host_only' => 'هالشي بس للمضيف.',
        'not_allowed' => 'ما بتقدر تعدّل على هاللاعب.',
        'host_stays' => 'المضيف ما بيطلع من غرفته.',
        'wrong_state' => 'ما بيصير هلق.',
        'empty_team' => 'كل فريق لازم يكون فيه لاعب واحد على الأقل.',
        'uneven_teams' => 'الفريقين لازم يكونوا بنفس عدد اللاعبين لحتى يكون الحساب عادل.',
        'need_two' => 'اللعب الفردي بدو لاعبين اتنين على الأقل.',
        'bad_list' => 'اختار قائمة.',
        'bad_text' => 'اكتب تخمينك.',
        'not_your_turn' => 'مش دورك.',
        'time_up' => 'خلص الوقت.',
    ];

    public function __construct(private RoomEngine $engine)
    {
    }

    public function page(string $page = 'home'): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view("akher.$page");
    }

    public function roomPage(string $code): View
    {
        abort_unless(preg_match('/^\d{4}$/', $code), 404);

        return view('akher.room', ['code' => $code]);
    }

    // ───────────────────────────── API ─────────────────────────────

    public function create(Request $request): JsonResponse
    {
        $data = $this->validated($request, ['name' => 'required|string|max:40']);

        return $this->run(function () use ($data) {
            [$room] = $this->engine->create($data['name'], $data['device']);

            return ['code' => $room->code];
        });
    }

    public function join(Request $request, string $code): JsonResponse
    {
        $data = $this->validated($request, ['name' => 'required|string|max:40']);

        return $this->run(fn () => ['player' => $this->engine->join($this->room($code), $data['name'], $data['device'])->id]);
    }

    public function state(Request $request, string $code): JsonResponse
    {
        $device = (string) $request->query('device', '');
        $room = $this->room($code);

        if ((int) $request->query('since') === $room->version) {
            return response()->json(['unchanged' => true, 'server_now' => (int) round(microtime(true) * 1000)]);
        }

        return response()->json($this->engine->state($room, $device));
    }

    public function addPlayer(Request $request, string $code): JsonResponse
    {
        $data = $this->validated($request, ['name' => 'required|string|max:40', 'team' => 'nullable|in:A,B']);

        return $this->run(fn () => ['player' => $this->engine->addOffline($this->room($code), $data['device'], $data['name'], $data['team'] ?? null)->id]);
    }

    public function switchTeam(Request $request, string $code, int $player): JsonResponse
    {
        $data = $this->validated($request);

        return $this->run(fn () => $this->engine->switchTeam($this->room($code), Player::findOrFail($player), $data['device']));
    }

    public function removePlayer(Request $request, string $code, int $player): JsonResponse
    {
        $data = $this->validated($request);

        return $this->run(fn () => $this->engine->remove($this->room($code), Player::findOrFail($player), $data['device']));
    }

    public function settings(Request $request, string $code): JsonResponse
    {
        $data = $this->validated($request, [
            'list_id' => 'nullable|string|max:40',
            'turns_per_player' => 'nullable|integer',
            'turn_seconds' => 'nullable|integer',
            'wrong_penalty' => 'nullable|boolean',
            'mode' => 'nullable|in:teams,solo',
            'host_plays' => 'nullable|boolean',
        ]);

        return $this->run(fn () => $this->engine->settings($this->room($code), $data['device'], $request->only(['list_id', 'turns_per_player', 'turn_seconds', 'wrong_penalty', 'mode', 'host_plays'])));
    }

    public function guess(Request $request, string $code): JsonResponse
    {
        $data = $this->validated($request, ['turn_idx' => 'required|integer|min:0', 'text' => 'required|string|max:200']);

        return $this->run(fn () => $this->engine->guess($this->room($code), $data['device'], (int) $data['turn_idx'], $data['text']));
    }

    public function expire(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['turn_idx' => 'required|integer|min:0']);

        return response()->json(['expired' => $this->engine->expire($this->room($code), (int) $data['turn_idx'])]);
    }

    public function hostAction(Request $request, string $code, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['start', 'skip', 'end', 'reset', 'leave'], true), 404);
        $data = $this->validated($request);

        return $this->run(fn () => $this->engine->{$action}($this->room($code), $data['device']));
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    private function validated(Request $request, array $rules = []): array
    {
        return $request->validate($rules + ['device' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/']]);
    }

    private function room(string $code): Room
    {
        $room = Room::find($code);

        if (! $room || $room->expires_at->isPast()) {
            abort(response()->json(['message' => 'no_room', 'error' => 'الغرفة مش موجودة أو انتهت.'], 404));
        }

        return $room;
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            $result = $fn();

            return response()->json(is_array($result) ? $result + ['ok' => true] : ['ok' => true]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => self::ERRORS[$e->getMessage()] ?? 'صار خطأ.'], 422);
        }
    }
}
