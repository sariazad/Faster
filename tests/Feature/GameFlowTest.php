<?php

namespace Tests\Feature;

use App\Livewire\GamePlay;
use App\Livewire\GameSetup;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameQuestion;
use Database\Seeders\QuestionBankSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionBankSeeder::class);
    }

    private function newGame(): Game
    {
        $ids = Category::orderBy('id')->pluck('id');

        return Game::start('الصقور', 'النمور', $ids->slice(0, 3)->all(), $ids->slice(3, 3)->all());
    }

    public function test_question_bank_is_complete_and_reseeding_adds_nothing(): void
    {
        $dir = database_path('seeders/data');
        $categories = count(json_decode(file_get_contents("{$dir}/categories.json"), true));
        $questions = count(file("{$dir}/question-bank.csv", FILE_SKIP_EMPTY_LINES)) - 1;

        $this->assertSame($categories, Category::playable()->count());
        $this->assertSame($questions, \App\Models\Question::count());

        $this->seed(QuestionBankSeeder::class);
        $this->assertSame($questions, \App\Models\Question::count());
    }

    public function test_pages_load(): void
    {
        $this->get('/')->assertOk()->assertSee('مين');
        $this->get('/play/new')->assertOk()->assertSee('الفئات');

        $game = $this->newGame();
        $this->get(route('game.play', $game))->assertOk()->assertSee('الصقور');
    }

    public function test_starting_a_game_builds_a_full_board(): void
    {
        $game = $this->newGame();

        $this->assertSame(6, $game->categories()->count());
        $this->assertSame(36, $game->gameQuestions()->count());
        $this->assertSame('a', $game->current_team);

        foreach (Game::LEVELS as $points) {
            $this->assertSame(12, $game->gameQuestions()->where('points', $points)->count());
        }
    }

    public function test_teams_cannot_share_a_category(): void
    {
        $ids = Category::orderBy('id')->pluck('id')->all();

        $this->expectException(DomainException::class);
        Game::start('أ', 'ب', [$ids[0], $ids[1], $ids[2]], [$ids[2], $ids[3], $ids[4]]);
    }

    public function test_correct_answer_scores_and_passes_turn(): void
    {
        $game = $this->newGame();
        $cell = $game->gameQuestions()->where('points', 400)->first();

        $game->open($cell);
        $points = $game->award('a');

        $game->refresh();
        $this->assertSame(400, $points);
        $this->assertSame(400, $game->team_a_score);
        $this->assertSame('b', $game->current_team);
        $this->assertNull($game->current_game_question_id);
        $this->assertSame(GameQuestion::ANSWERED, $cell->fresh()->status);
    }

    public function test_steal_gives_points_to_other_team_and_turn_still_passes(): void
    {
        $game = $this->newGame();
        $game->open($game->gameQuestions()->where('points', 600)->first());
        $game->award('b');

        $game->refresh();
        $this->assertSame(600, $game->team_b_score);
        $this->assertSame('b', $game->current_team);
    }

    public function test_double_only_applies_to_the_team_that_used_it(): void
    {
        $game = $this->newGame();
        $game->open($game->gameQuestions()->where('points', 200)->first());
        $game->useHelper('double');
        $this->assertSame(400, $game->award('a'));

        // Team B's turn: B uses double, A steals → no double for A.
        $game->refresh();
        $game->open($game->gameQuestions()->where('status', 'pending')->where('points', 200)->first());
        $game->useHelper('double');
        $this->assertSame(200, $game->award('a'));
    }

    public function test_helper_can_only_be_used_once_per_team(): void
    {
        $game = $this->newGame();
        $game->open($game->gameQuestions()->first());
        $game->useHelper('extra_time');
        $game->award('n');

        $game->refresh();
        $game->open($game->gameQuestions()->where('status', 'pending')->first()); // team B
        $game->useHelper('extra_time');                                           // fine for B
        $game->award('n');

        $game->refresh();
        $game->open($game->gameQuestions()->where('status', 'pending')->first()); // team A again
        $this->expectException(DomainException::class);
        $game->useHelper('extra_time');
    }

    public function test_locked_question_cannot_be_stolen(): void
    {
        $game = $this->newGame();
        $game->open($game->gameQuestions()->first());
        $game->useHelper('no_steal');

        $this->expectException(DomainException::class);
        $game->award('b');
    }

    public function test_game_finishes_when_board_is_cleared(): void
    {
        $game = $this->newGame();

        foreach ($game->gameQuestions()->get() as $cell) {
            $game->refresh();
            $game->open($cell);
            $game->award($game->current_team);
        }

        $game->refresh();
        $this->assertTrue($game->isFinished());
        // 6 categories × 2 questions × (200 + 400 + 600)
        $this->assertSame(14400, $game->team_a_score + $game->team_b_score);
    }

    public function test_setup_component_creates_game_and_redirects(): void
    {
        $ids = Category::orderBy('id')->pluck('id');

        $component = Livewire::test(GameSetup::class)
            ->set('teamA', 'الصقور')
            ->set('teamB', 'النمور');

        foreach ($ids->take(6) as $id) {
            $component->call('toggle', $id);
        }

        $component->call('start')->assertHasNoErrors();

        $game = Game::first();
        $this->assertNotNull($game);
        $component->assertRedirect(route('game.play', $game));
    }

    public function test_play_component_runs_a_turn(): void
    {
        $game = $this->newGame();
        $cell = $game->gameQuestions()->where('points', 200)->first();

        Livewire::test(GamePlay::class, ['game' => $game])
            ->call('open', $cell->id)
            ->assertSee($cell->question->body)
            ->call('reveal')
            ->assertSee($cell->question->answer)
            ->call('award', 'a')
            ->assertSet('revealed', false);

        $this->assertSame(200, $game->fresh()->team_a_score);
    }

    public function test_question_and_answer_media_are_shown(): void
    {
        $game = $this->newGame();
        $cell = $game->gameQuestions()->where('points', 200)->first();
        $cell->question->update([
            'image' => 'questions/q.jpg',
            'audio' => 'questions/audio/q.mp3',
            'answer' => null,
            'answer_image' => 'questions/a.jpg',
            'answer_audio' => 'questions/audio/a.mp3',
        ]);

        Livewire::test(GamePlay::class, ['game' => $game])
            ->call('open', $cell->id)
            ->assertSee('questions/q.jpg')
            ->assertSee('questions/audio/q.mp3')
            ->assertDontSee('questions/a.jpg')
            ->call('reveal')
            ->assertSee('questions/a.jpg')
            ->assertSee('questions/audio/a.mp3');
    }

    public function test_host_can_switch_turn_only_between_questions(): void
    {
        $game = $this->newGame();
        $game->switchTurn();
        $this->assertSame('b', $game->fresh()->current_team);

        $game->refresh();
        $game->open($game->gameQuestions()->first());
        $this->expectException(DomainException::class);
        $game->switchTurn();
    }

    /* ---------------------------------------------------------------
     | Question selection
     * -------------------------------------------------------------- */

    /**
     * A playable category with 4 questions per level.
     *
     * @param  array<int, array<int, string>>  $answers  optional answers per level
     */
    private function fixtureCategory(string $name, array $answers = [], array $bodies = []): Category
    {
        $category = Category::create(['name' => $name, 'is_active' => true]);

        foreach (Game::LEVELS as $points) {
            for ($i = 1; $i <= 4; $i++) {
                $category->questions()->create([
                    'points' => $points,
                    'body' => $bodies[$points][$i - 1] ?? "{$name} سؤال {$points} رقم {$i}",
                    'answer' => $answers[$points][$i - 1] ?? "{$name} جواب {$points} رقم {$i}",
                    'is_active' => true,
                ]);
            }
        }

        return $category;
    }

    /** @return array<int> */
    private function fixtureColumns(): array
    {
        return collect(['ألف', 'باء', 'جيم', 'دال', 'هاء', 'واو'])
            ->map(fn ($n) => $this->fixtureCategory("فئة {$n}")->id)
            ->all();
    }

    public function test_questions_rotate_and_do_not_return_before_the_rest_are_used(): void
    {
        $ids = $this->fixtureColumns();

        $first = Game::start('أ', 'ب', array_slice($ids, 0, 3), array_slice($ids, 3, 3));
        $second = Game::start('أ', 'ب', array_slice($ids, 0, 3), array_slice($ids, 3, 3));

        $firstIds = $first->gameQuestions()->pluck('question_id')->all();
        $secondIds = $second->gameQuestions()->pluck('question_id')->all();

        $this->assertCount(36, $firstIds);
        $this->assertCount(36, $secondIds);
        $this->assertEmpty(array_intersect($firstIds, $secondIds), 'Second game reused a question before the bank was exhausted.');

        // 4 per level, 2 per game: after two games every fixture question was used exactly once.
        $usage = \App\Models\Question::whereIn('category_id', $ids)->pluck('times_used')->unique()->all();
        $this->assertSame([1], array_values($usage));
    }

    public function test_least_used_questions_are_preferred(): void
    {
        $ids = $this->fixtureColumns();
        \App\Models\Question::whereIn('category_id', $ids)->update(['times_used' => 5]);

        $fresh = \App\Models\Question::whereIn('category_id', $ids)
            ->get()
            ->groupBy(fn ($q) => $q->category_id.'-'.$q->points)
            ->map(fn ($group) => $group->take(2)->pluck('id'))
            ->flatten();
        \App\Models\Question::whereIn('id', $fresh)->update(['times_used' => 0]);

        $game = Game::start('أ', 'ب', array_slice($ids, 0, 3), array_slice($ids, 3, 3));

        $this->assertEqualsCanonicalizing($fresh->all(), $game->gameQuestions()->pluck('question_id')->all());
    }

    public function test_same_text_in_two_categories_is_not_asked_twice_in_one_game(): void
    {
        $same = 'ما عاصمة اليابان؟';
        $a = $this->fixtureCategory('مكرر أ', bodies: [200 => [$same]]);
        $b = $this->fixtureCategory('مكرر ب', bodies: [200 => ["  ما عاصمة   اليابان ؟ "]]);
        $others = collect(['ز', 'ح', 'ط', 'ي'])->map(fn ($n) => $this->fixtureCategory("فئة {$n}")->id)->all();

        for ($run = 0; $run < 5; $run++) {
            \App\Models\Question::query()->update(['times_used' => 0]);
            $game = Game::start('أ', 'ب', [$a->id, $others[0], $others[1]], [$b->id, $others[2], $others[3]]);

            $bodies = $game->gameQuestions()->with('question')->get()
                ->map(fn ($gq) => \App\Support\TextKey::make($gq->question->body));

            $this->assertSame($bodies->count(), $bodies->unique()->count());
        }
    }

    public function test_two_questions_with_the_same_answer_are_avoided_when_possible(): void
    {
        $a = $this->fixtureCategory('جواب أ', answers: [600 => ['لمّاح']]);
        $b = $this->fixtureCategory('جواب ب', answers: [400 => ['«لماح»']]);
        $others = collect(['ك', 'ل', 'م', 'ن'])->map(fn ($n) => $this->fixtureCategory("فئة {$n}")->id)->all();

        for ($run = 0; $run < 5; $run++) {
            \App\Models\Question::query()->update(['times_used' => 0]);
            $game = Game::start('أ', 'ب', [$a->id, $others[0], $others[1]], [$b->id, $others[2], $others[3]]);

            $answers = $game->gameQuestions()->with('question')->get()
                ->map(fn ($gq) => \App\Support\TextKey::make($gq->question->answer));

            $this->assertSame($answers->count(), $answers->unique()->count());
        }
    }

    public function test_text_key_ignores_harakat_punctuation_and_alef_forms(): void
    {
        $this->assertSame(\App\Support\TextKey::make('لمّاح'), \App\Support\TextKey::make('«لماح»'));
        $this->assertSame(\App\Support\TextKey::make('أبها'), \App\Support\TextKey::make('ابها'));
        $this->assertSame(\App\Support\TextKey::make('2000م (1421هـ)'), \App\Support\TextKey::make('٢٠٠٠م'));
        $this->assertNotSame(\App\Support\TextKey::make('طلال مداح'), \App\Support\TextKey::make('محمد عبده'));
    }
}
