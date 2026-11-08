<?php

namespace App\Http\Controllers;

use App\Models\AlefYa\Account;
use App\Models\AlefYa\Contribution;
use App\Models\AlefYa\Note;
use App\Models\AlefYa\Notification;
use App\Models\AlefYa\Question;
use App\Models\AlefYa\Score;
use App\Support\AlefYaLetters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ألف ياء — the letters game. Pages are plain Blade + vanilla JS (state in localStorage);
 * this controller serves them and the small JSON API they call.
 */
class AlefYaController extends Controller
{
    public const SESSION_KEY = 'alefya_account';

    public const PAGES = ['home', 'customize', 'play', 'solo', 'leaderboard', 'how', 'about', 'faq', 'login', 'contribute'];

    public function page(Request $request, string $page = 'home'): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view("alefya.$page", ['account' => $this->account($request)]);
    }

    public function portal(Request $request): View|RedirectResponse
    {
        if (! $this->account($request)) {
            return redirect()->route('alefya.page', ['page' => 'login', 'next' => 'questions']);
        }

        return view('alefya.questions', ['account' => $this->account($request)]);
    }

    // ───────────────────────────── Questions ─────────────────────────────

    public function question(Request $request): JsonResponse
    {
        $data = $request->validate([
            'letter' => ['nullable', Rule::in(AlefYaLetters::ALL)],
            'exclude' => ['nullable', 'string', 'max:20000'],
        ]);

        $exclude = array_filter(array_map('intval', explode(',', $data['exclude'] ?? '')));
        [$question, $reset] = Question::pick($data['letter'] ?? null, $exclude);

        if (! $question) {
            return response()->json(['message' => 'no-questions'], 404);
        }

        return response()->json(['question' => $question->toGame(), 'reset' => $reset]);
    }

    /** Questions (with 4 options) for every cell of the single challenge board. */
    public function solo(): JsonResponse
    {
        $cells = config('alefya.solo.rows') * config('alefya.solo.cols');
        $letters = Question::lettersSummary()->filter(fn ($n) => $n > 0)->keys()->shuffle();

        $questions = [];
        $used = [];

        for ($i = 0; $i < $cells && $letters->isNotEmpty(); $i++) {
            $letter = $letters[$i % $letters->count()];
            [$q] = Question::pick($letter, $used);

            if ($q) {
                $used[] = $q->id;
                $questions[] = $q->toGame(true);
            }
        }

        return response()->json(['questions' => $questions, 'seconds' => config('alefya.solo.seconds'), 'bonus' => config('alefya.solo.bonus')]);
    }

    public function rate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'level' => ['required', Rule::in(['easy', 'medium', 'hard'])],
        ]);

        Question::whereKey($data['id'])->increment('rated_'.$data['level']);

        return response()->json(['ok' => true]);
    }

    public function note(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $question = Question::findOrFail($data['id']);
        Note::create(['question_id' => $question->id, 'body' => trim($data['body'])]);

        return response()->json(['ok' => true]);
    }

    public function contribute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:5'],
            'items.*.question' => ['required', 'string', 'min:4', 'max:500'],
            'items.*.answer' => ['required', 'string', 'max:120'],
        ]);

        foreach ($data['items'] as $i => $item) {
            if (! AlefYaLetters::of($item['answer'])) {
                return response()->json(['message' => 'invalid', 'errors' => ["items.$i.answer" => ['letter']]], 422);
            }
        }

        foreach ($data['items'] as $item) {
            Contribution::create([
                'name' => trim((string) ($data['name'] ?? '')) ?: null,
                'body' => trim($item['question']),
                'answer' => trim($item['answer']),
            ]);
        }

        return response()->json(['ok' => true, 'count' => count($data['items'])]);
    }

    public function notifications(): JsonResponse
    {
        return response()->json([
            'notifications' => Notification::latest('id')->limit(30)->get(['id', 'title', 'body', 'created_at'])
                ->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'date' => $n->created_at?->format('Y-m-d')]),
        ]);
    }

    // ───────────────────────────── Accounts ─────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:10'],
            'pin' => ['required', 'digits:4'],
        ]);

        $account = Account::where('code', $data['code'])->first();

        if (! $account) {
            return response()->json(['message' => 'unknown'], 422);
        }

        [$ok, $first] = $account->attempt($data['pin']);

        if (! $ok) {
            return response()->json(['message' => 'wrong'], 422);
        }

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $account->id);

        return response()->json(['ok' => true, 'code' => $account->code, 'first' => $first]);
    }

    public function demoCode(): JsonResponse
    {
        return response()->json(['code' => Account::generate()->code]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['code' => $this->account($request)?->code]);
    }

    // ───────────────────────────── Single challenge ─────────────────────────────

    public function storeScore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'won' => ['required', 'boolean'],
            'correct' => ['required', 'integer', 'min:0', 'max:100'],
            'wrong' => ['required', 'integer', 'min:0', 'max:100'],
            'time_left' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $account = $this->account($request);
        $score = Score::create($data + ['account_id' => $account?->id]);

        return response()->json(['ok' => true, 'id' => $score->id, 'guest' => ! $account]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $request->validate(['code' => ['nullable', 'digits:10']]);

        $top = Score::with('account')->ranked()->limit(20)->get()->values()->map(fn (Score $s, int $i) => [
            'rank' => $i + 1,
            'player' => $s->playerName(),
            'won' => $s->won,
            'correct' => $s->correct,
            'time_left' => $s->time_left,
            'date' => $s->created_at?->format('Y-m-d'),
        ]);

        $mine = null;

        if ($code = $request->input('code')) {
            $account = Account::where('code', $code)->first();
            $best = $account?->scores()->ranked()->first();

            if ($best) {
                $ids = Score::ranked()->pluck('id');
                $mine = ['rank' => $ids->search($best->id) + 1, 'total' => $ids->count()];
            }
        }

        return response()->json(['top' => $top, 'mine' => $mine]);
    }

    // ───────────────────────────── Optional sync (portal → board) ─────────────────────────────

    public function pushSync(Request $request): JsonResponse
    {
        $account = $this->account($request);
        abort_unless($account, 401);

        $data = $request->validate(['letter' => ['required', Rule::in(AlefYaLetters::ALL)]]);
        $payload = ['letter' => $data['letter'], 'at' => now()->getTimestampMs()];
        Cache::put("alefya-sync-{$account->id}", $payload, now()->addHours(6));

        return response()->json($payload);
    }

    public function pullSync(Request $request): JsonResponse
    {
        $account = $this->account($request);
        abort_unless($account, 401);

        return response()->json(Cache::get("alefya-sync-{$account->id}", ['letter' => null, 'at' => 0]));
    }

    private function account(Request $request): ?Account
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? Account::find($id) : null;
    }
}
