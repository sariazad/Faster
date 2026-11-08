<?php

namespace Tests\Feature;

use App\Http\Controllers\AlefYaController;
use App\Models\AlefYa\Account;
use App\Models\AlefYa\Contribution;
use App\Models\AlefYa\Note;
use App\Models\AlefYa\Question;
use App\Models\AlefYa\Score;
use App\Models\User;
use App\Services\AlefYaBank;
use App\Support\AlefYaLetters;
use Database\Seeders\AlefYaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlefYaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AlefYaSeeder::class);
    }

    public function test_the_letters_bank_lives_in_its_own_database(): void
    {
        $this->assertSame('alefya', (new Question)->getConnectionName());
        $this->assertSame(Question::count(), DB::connection('alefya')->table('questions')->count());
        $this->assertSame(0, \App\Models\Question::count(), 'the main bank is untouched');

        // Seeding again adds nothing.
        $before = Question::count();
        $this->seed(AlefYaSeeder::class);
        $this->assertSame($before, Question::count());
    }

    public function test_every_letter_has_questions_with_four_options(): void
    {
        $summary = Question::lettersSummary();

        foreach (AlefYaLetters::ALL as $letter) {
            $this->assertGreaterThanOrEqual(5, $summary[$letter] ?? 0, "letter $letter");
        }

        Question::inRandomOrder()->limit(50)->get()->each(function (Question $q) {
            $this->assertCount(4, $q->options);
            $this->assertContains($q->answer, $q->options);
            $this->assertSame($q->letter, AlefYaLetters::of($q->answer) ?? $q->letter);
        });
    }

    public function test_letter_detection_ignores_al_and_normalises_alef(): void
    {
        $this->assertSame('أ', AlefYaLetters::of('الأسد'));
        $this->assertSame('أ', AlefYaLetters::of('إبراهيم'));
        $this->assertSame('ق', AlefYaLetters::of('القاهرة'));
        $this->assertSame('هـ', AlefYaLetters::of('هدهد'));
        $this->assertSame('ل', AlefYaLetters::of('لبنان'));
        $this->assertNull(AlefYaLetters::of('118'));
    }

    public function test_question_api_skips_seen_questions_then_starts_over(): void
    {
        $ids = Question::where('letter', 'ظ')->pluck('id');

        $seen = [];
        foreach ($ids as $_) {
            $res = $this->getJson('/alefya/api/question?letter=ظ&exclude='.implode(',', $seen))->assertOk();
            $this->assertSame('ظ', $res->json('question.letter'));
            $this->assertFalse($res->json('reset'));
            $this->assertNotContains($res->json('question.id'), $seen);
            $seen[] = $res->json('question.id');
        }

        $this->getJson('/alefya/api/question?letter=ظ&exclude='.implode(',', $seen))->assertOk()->assertJsonPath('reset', true);
        $this->getJson('/alefya/api/question?letter=x')->assertStatus(422);
    }

    public function test_solo_board_gets_fifteen_questions_with_options(): void
    {
        $res = $this->getJson('/alefya/api/solo')->assertOk();

        $this->assertCount(15, $res->json('questions'));
        $this->assertSame(30, $res->json('seconds'));
        foreach ($res->json('questions') as $q) {
            $this->assertCount(4, $q['options']);
            $this->assertContains($q['answer'], $q['options']);
        }
        $this->assertSame(15, collect($res->json('questions'))->pluck('id')->unique()->count());
    }

    public function test_login_sets_the_pin_on_first_use_then_checks_it(): void
    {
        $code = $this->postJson('/alefya/api/demo-code')->assertOk()->json('code');
        $this->assertMatchesRegularExpression('/^\d{10}$/', $code);

        $this->get('/alefya/questions')->assertRedirect();

        $this->postJson('/alefya/api/login', ['code' => $code, 'pin' => '1234'])->assertOk()->assertJsonPath('first', true);
        $this->getJson('/alefya/api/me')->assertJsonPath('code', $code);
        $this->get('/alefya/questions')->assertOk()->assertSee('بوابة الأسئلة');

        $this->postJson('/alefya/api/logout')->assertOk();
        $this->getJson('/alefya/api/me')->assertJsonPath('code', null);

        $this->postJson('/alefya/api/login', ['code' => $code, 'pin' => '9999'])->assertStatus(422)->assertJsonPath('message', 'wrong');
        $this->postJson('/alefya/api/login', ['code' => $code, 'pin' => '1234'])->assertOk()->assertJsonPath('first', false);
        $this->postJson('/alefya/api/login', ['code' => '0000000001', 'pin' => '1234'])->assertStatus(422)->assertJsonPath('message', 'unknown');
    }

    public function test_contributions_wait_for_review_and_join_the_bank_when_approved(): void
    {
        $this->postJson('/alefya/api/contribute', [
            'name' => 'ساري',
            'items' => [
                ['question' => 'عاصمة المملكة العربية السعودية؟', 'answer' => 'الرياض'],
                ['question' => 'طائر يرمز للحكمة؟', 'answer' => 'البومة'],
            ],
        ])->assertOk()->assertJsonPath('count', 2);

        $c = Contribution::where('answer', 'الرياض')->firstOrFail();
        $this->assertSame('ر', $c->letter);
        $this->assertSame(Contribution::PENDING, $c->status);

        $question = $c->approve();
        $this->assertSame('ساري', $question->contributor);
        $this->assertSame('ر', $question->letter);
        $this->assertCount(4, $question->options);
        $this->assertSame(Contribution::APPROVED, $c->fresh()->status);

        $this->postJson('/alefya/api/contribute', ['items' => [['question' => 'سؤال بلا حرف؟', 'answer' => '2026']]])->assertStatus(422);
        $this->postJson('/alefya/api/contribute', ['items' => array_fill(0, 6, ['question' => 'سؤال تجريبي؟', 'answer' => 'تجربة'])])->assertStatus(422);
    }

    public function test_ratings_and_notes_reach_the_question(): void
    {
        $q = Question::first();

        $this->postJson('/alefya/api/rate', ['id' => $q->id, 'level' => 'hard'])->assertOk();
        $this->postJson('/alefya/api/note', ['id' => $q->id, 'body' => 'الإجابة الأدق: كذا'])->assertOk();

        $this->assertSame(1, $q->fresh()->rated_hard);
        $this->assertSame(1, Note::where('question_id', $q->id)->count());
    }

    public function test_leaderboard_ranks_wins_then_answers_then_time(): void
    {
        $me = Account::generate();
        Score::create(['won' => false, 'correct' => 9, 'wrong' => 3, 'time_left' => 0]);
        Score::create(['won' => true, 'correct' => 5, 'wrong' => 0, 'time_left' => 4]);
        Score::create(['account_id' => $me->id, 'won' => true, 'correct' => 5, 'wrong' => 1, 'time_left' => 12]);

        $this->postJson('/alefya/api/scores', ['won' => true, 'correct' => 3, 'wrong' => 0, 'time_left' => 1])->assertOk()->assertJsonPath('guest', true);

        $res = $this->getJson('/alefya/api/leaderboard?code='.$me->code)->assertOk();
        $this->assertSame($me->masked(), $res->json('top.0.player'));
        $this->assertSame('زائر', $res->json('top.3.player'));
        $this->assertSame(['rank' => 1, 'total' => 4], $res->json('mine'));
    }

    public function test_sync_needs_a_login_and_passes_the_letter(): void
    {
        $this->postJson('/alefya/api/sync', ['letter' => 'ب'])->assertStatus(401);

        $account = Account::generate();
        $this->withSession([AlefYaController::SESSION_KEY => $account->id]);
        $this->postJson('/alefya/api/sync', ['letter' => 'ب'])->assertOk();
        $this->getJson('/alefya/api/sync')->assertOk()->assertJsonPath('letter', 'ب');
    }

    public function test_bank_export_and_import_round_trip(): void
    {
        $json = app(AlefYaBank::class)->export();
        $this->assertSame(['added' => 0, 'skipped' => Question::count()], app(AlefYaBank::class)->import($json));

        $result = app(AlefYaBank::class)->import(json_encode([['question' => 'حيوان يلقب بسفينة الصحراء؟', 'answer' => 'الجمل']]));
        $this->assertSame(1, $result['added']);
        $this->assertSame('ج', Question::latest('id')->first()->letter);
    }

    public function test_pages_load(): void
    {
        foreach (AlefYaController::PAGES as $page) {
            $url = $page === 'home' ? '/alefya' : "/alefya/$page";
            $this->get($url)->assertOk()->assertSee('ألف', false);
        }

        $this->get('/alefya/play')->assertSee('نمط الأسئلة');
        $this->get('/alefya/solo')->assertSee('التحدي الفردي');
        $this->get('/')->assertSee('ألف ياء', false);
        $this->get('/alefya/nope')->assertNotFound();
    }

    public function test_admin_screens_load(): void
    {
        $this->actingAs(User::factory()->create());
        Contribution::create(['body' => 'سؤال؟', 'answer' => 'سؤال']);
        Note::create(['question_id' => Question::first()->id, 'body' => 'ملاحظة']);

        foreach (['alefya-questions', 'alefya-contributions', 'alefya-notes', 'alefya-notifications', 'alefya-accounts'] as $slug) {
            $this->get("/admin/$slug")->assertOk();
        }
    }
}
