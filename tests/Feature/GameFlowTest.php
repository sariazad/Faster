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
        $this->get('/play/new')->assertOk()->assertSee('ابحث عن فئة');

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

    /* ---------------------------------------------------------------
     | Duplicate clean-up
     * -------------------------------------------------------------- */

    public function test_starter_bank_has_no_duplicate_question_text(): void
    {
        $keys = \App\Models\Question::pluck('body')->map(fn ($b) => \App\Support\TextKey::make($b));

        $this->assertSame($keys->count(), $keys->unique()->count());
    }

    public function test_dedupe_removes_copies_in_the_same_category_and_keeps_game_history(): void
    {
        $category = $this->fixtureCategory('تنظيف');
        $original = $category->questions()->where('points', 200)->first();
        $copy = $category->questions()->create([
            'points' => 200,
            'body' => '  '.$original->body.'؟؟ ',
            'answer' => $original->answer,
            'is_active' => true,
            'times_used' => 3,
        ]);

        $ids = collect(['س', 'ش', 'ص', 'ض', 'ع'])->map(fn ($n) => $this->fixtureCategory("فئة {$n}")->id)->all();
        $game = Game::start('أ', 'ب', [$category->id, $ids[0], $ids[1]], [$ids[2], $ids[3], $ids[4]]);
        $game->gameQuestions()->where('category_id', $category->id)->where('points', 200)->first()
            ->update(['question_id' => $copy->id]);

        $before = $game->gameQuestions()->count();
        $report = app(\App\Services\QuestionDeduplicator::class)->run();

        $this->assertSame(1, $report['removed']);
        $this->assertNull(\App\Models\Question::find($copy->id));
        $this->assertNotNull(\App\Models\Question::find($original->id));
        $this->assertSame($before, $game->gameQuestions()->count());
        $this->assertTrue($game->gameQuestions()->where('question_id', $original->id)->exists());
    }

    public function test_dedupe_dry_run_changes_nothing(): void
    {
        $category = $this->fixtureCategory('معاينة');
        $original = $category->questions()->first();
        $category->questions()->create(['points' => $original->points, 'body' => $original->body, 'answer' => 'x', 'is_active' => true]);

        $count = \App\Models\Question::count();
        $report = app(\App\Services\QuestionDeduplicator::class)->run(dryRun: true);

        $this->assertSame(1, $report['removed']);
        $this->assertSame($count, \App\Models\Question::count());
    }

    public function test_dedupe_keeps_a_cross_category_copy_when_its_category_would_become_too_small(): void
    {
        $big = $this->fixtureCategory('كبيرة');
        $small = $this->fixtureCategory('صغيرة');
        $small->questions()->where('points', 200)->orderByDesc('id')->limit(1)->get()->each->delete(); // 3 left at 200

        $shared = $big->questions()->where('points', 200)->first();
        $small->questions()->where('points', 200)->first()->update(['body' => $shared->body]);

        $report = app(\App\Services\QuestionDeduplicator::class)->run();

        $this->assertSame(0, $report['removed']);
        $this->assertSame(1, $report['kept_for_playability']);
        $this->assertSame(3, $small->questions()->where('points', 200)->count());
    }

    public function test_retired_bank_questions_are_pruned(): void
    {
        $category = Category::firstOrCreate(['name' => 'عايض']);
        $retired = $category->questions()->create([
            'points' => 200,
            'body' => 'ما أغنية عايض التي اسمها صفة تعني الشخص الذكي سريع الفهم؟',
            'answer' => 'لماح',
            'is_active' => true,
        ]);

        $pruned = app(\App\Services\QuestionDeduplicator::class)
            ->pruneRetiredBankQuestions(database_path('seeders/data/removed-questions.csv'));

        $this->assertSame(1, $pruned);
        $this->assertNull(\App\Models\Question::find($retired->id));
    }

    /* ---------------------------------------------------------------
     | Timers
     * -------------------------------------------------------------- */

    public function test_game_keeps_the_timers_chosen_on_setup(): void
    {
        $ids = Category::orderBy('id')->pluck('id');

        $component = Livewire::test(GameSetup::class)
            ->set('teamA', 'الصقور')
            ->set('teamB', 'النمور')
            ->set('answerSeconds', 30)
            ->set('stealSeconds', 15);

        foreach ($ids->take(6) as $id) {
            $component->call('toggle', $id);
        }

        $component->call('start')->assertHasNoErrors();

        $game = Game::first();
        $this->assertSame(30, $game->answer_seconds);
        $this->assertSame(15, $game->steal_seconds);

        $cell = $game->gameQuestions()->first();
        Livewire::test(GamePlay::class, ['game' => $game])
            ->call('open', $cell->id)
            ->assertSee('questionTimer(30, 15', false);
    }

    public function test_timers_default_to_sixty_and_thirty(): void
    {
        $game = $this->newGame();

        $this->assertSame(Game::MAIN_SECONDS, $game->fresh()->answer_seconds);
        $this->assertSame(Game::STEAL_SECONDS, $game->fresh()->steal_seconds);
    }

    public function test_timer_outside_the_options_is_rejected(): void
    {
        Livewire::test(GameSetup::class)
            ->set('answerSeconds', 7)
            ->call('start')
            ->assertHasErrors(['answerSeconds']);

        $ids = Category::orderBy('id')->pluck('id');
        $this->expectException(DomainException::class);
        Game::start('أ', 'ب', $ids->slice(0, 3)->all(), $ids->slice(3, 3)->all(), 7, 30);
    }

    /* ---------------------------------------------------------------
     | Picture / sound rounds with the same question text
     * -------------------------------------------------------------- */

    private function pictureCategory(string $name): Category
    {
        $category = Category::create(['name' => $name, 'is_active' => true]);

        foreach (Game::LEVELS as $points) {
            for ($i = 1; $i <= 2; $i++) {
                $category->questions()->create([
                    'points' => $points,
                    'body' => 'خمن اسم الأكلة',
                    'image' => "questions/{$name}-{$points}-{$i}.jpg",
                    'answer' => "أكلة {$points} {$i}",
                    'is_active' => true,
                ]);
            }
        }

        return $category;
    }

    public function test_picture_questions_with_the_same_text_fill_the_board(): void
    {
        $food = $this->pictureCategory('خمن الأكلة');
        $ids = Category::where('id', '!=', $food->id)->orderBy('id')->limit(5)->pluck('id')->all();

        $game = Game::start('أ', 'ب', [$food->id, $ids[0], $ids[1]], [$ids[2], $ids[3], $ids[4]]);

        $this->assertSame(36, $game->gameQuestions()->count());
        $this->assertSame(6, $game->gameQuestions()->where('category_id', $food->id)->count());
    }

    public function test_dedupe_keeps_picture_questions_that_share_text(): void
    {
        $food = $this->pictureCategory('خمن الأكلة 2');

        $report = app(\App\Services\QuestionDeduplicator::class)->run();

        $this->assertSame(0, $report['removed']);
        $this->assertSame(6, $food->questions()->count());
    }

    public function test_board_is_full_even_when_a_category_only_has_identical_texts(): void
    {
        $same = Category::create(['name' => 'نص واحد', 'is_active' => true]);
        foreach (Game::LEVELS as $points) {
            foreach ([1, 2] as $i) {
                $same->questions()->create(['points' => $points, 'body' => 'سؤال مكرر', 'answer' => "جواب {$points}{$i}", 'is_active' => true]);
            }
        }
        $ids = Category::where('id', '!=', $same->id)->orderBy('id')->limit(5)->pluck('id')->all();

        $game = Game::start('أ', 'ب', [$same->id, $ids[0], $ids[1]], [$ids[2], $ids[3], $ids[4]]);

        $this->assertSame(36, $game->gameQuestions()->count());
    }

    /* ---------------------------------------------------------------
     | Backup / restore
     * -------------------------------------------------------------- */

    public function test_backup_and_restore_round_trip_with_media(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('questions/kabsa.jpg', 'fake-image-bytes');
        \Illuminate\Support\Facades\Storage::disk('public')->put('questions/audio/song.mp3', 'fake-audio-bytes');

        $food = Category::create(['name' => 'خمن الأكلة', 'icon' => '🍛', 'is_active' => true]);
        $food->questions()->create(['points' => 200, 'body' => 'خمن اسم الأكلة', 'image' => 'questions/kabsa.jpg', 'answer' => 'كبسة', 'is_active' => true]);
        $food->questions()->create(['points' => 400, 'body' => 'مين صاحب الصوت؟', 'audio' => 'questions/audio/song.mp3', 'answer' => 'محمد عبده', 'is_active' => true]);

        $categories = Category::count();
        $questions = \App\Models\Question::count();

        $dir = sys_get_temp_dir().'/backup-test-'.uniqid();
        $zipPath = app(\App\Services\QuestionBankBackup::class)->export($dir);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame($questions, $manifest['questions']);
        $this->assertSame($categories, $manifest['categories']);
        $this->assertSame('fake-image-bytes', $zip->getFromName('media/questions/kabsa.jpg'));
        $this->assertNotFalse($zip->getFromName('questions.csv'));
        $zip->close();

        // Lose everything, then restore.
        Category::query()->delete();
        \Illuminate\Support\Facades\Storage::disk('public')->delete(['questions/kabsa.jpg', 'questions/audio/song.mp3']);
        $this->assertSame(0, \App\Models\Question::count());

        $result = app(\App\Services\QuestionBankBackup::class)->restore($zipPath);

        $this->assertSame($questions, $result['questions']);
        $this->assertSame($questions, \App\Models\Question::count());
        $this->assertSame($categories, Category::count());
        $this->assertSame('🍛', Category::where('name', 'خمن الأكلة')->value('icon'));
        $this->assertSame('fake-image-bytes', \Illuminate\Support\Facades\Storage::disk('public')->get('questions/kabsa.jpg'));

        // Restoring the same backup again adds nothing.
        $again = app(\App\Services\QuestionBankBackup::class)->restore($zipPath);
        $this->assertSame(0, $again['questions']);
        $this->assertSame($questions, \App\Models\Question::count());

        @unlink($zipPath);
        @rmdir($dir);
    }

    public function test_restore_rejects_a_zip_that_is_not_a_backup(): void
    {
        $path = sys_get_temp_dir().'/not-a-backup-'.uniqid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'hi');
        $zip->close();

        $this->expectException(\RuntimeException::class);

        try {
            app(\App\Services\QuestionBankBackup::class)->restore($path);
        } finally {
            @unlink($path);
        }
    }
}
