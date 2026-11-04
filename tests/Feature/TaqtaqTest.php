<?php

namespace Tests\Feature;

use App\Livewire\TaqtaqPlay;
use App\Livewire\TaqtaqSetup;
use App\Models\BoardCell;
use App\Models\Category;
use App\Models\Game;
use App\Services\Taqtaq;
use Database\Seeders\QuestionBankSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaqtaqTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QuestionBankSeeder::class);
    }

    private function engine(): Taqtaq
    {
        return app(Taqtaq::class);
    }

    private function newGame(int $size = 5): Game
    {
        $ids = Taqtaq::playableCategories()->pluck('id');

        return $this->engine()->start('الصقور', 'النمور', $ids->slice(0, 2)->all(), $ids->slice(2, 2)->all(), $size);
    }

    private function cell(Game $game, int $row, int $col): BoardCell
    {
        return $game->cells()->where('row', $row)->where('col', $col)->firstOrFail();
    }

    /** Opens a cell for whoever's turn it is and gives it to $team. */
    private function take(Game $game, int $row, int $col, string $team): ?array
    {
        $game->refresh();
        $this->engine()->open($game, $this->cell($game, $row, $col));

        return $this->engine()->award($game->refresh(), $team);
    }

    public function test_board_is_built_with_four_categories_spread_evenly(): void
    {
        $game = $this->newGame(5);

        $this->assertTrue($game->isTaqtaq());
        $this->assertSame(25, $game->cells()->count());

        $perCategory = $game->cells()->get()->countBy('category_id');
        $this->assertCount(4, $perCategory);
        $this->assertLessThanOrEqual(1, $perCategory->max() - $perCategory->min());
    }

    public function test_right_answer_takes_the_cell_and_passes_the_turn(): void
    {
        $game = $this->newGame();

        $this->take($game, 2, 2, 'a');

        $game->refresh();
        $this->assertSame('a', $this->cell($game, 2, 2)->owner);
        $this->assertSame('b', $game->current_team);
        $this->assertNull($game->current_cell_question_id);
    }

    public function test_the_other_team_can_steal_and_nobody_leaves_the_cell_free(): void
    {
        $game = $this->newGame();

        $this->take($game, 0, 0, 'b');   // A's turn, B steals
        $this->assertSame('b', $this->cell($game, 0, 0)->owner);

        $this->take($game, 1, 1, 'n');   // nobody
        $this->assertNull($this->cell($game, 1, 1)->owner);
    }

    public function test_team_a_wins_by_connecting_top_to_bottom(): void
    {
        $game = $this->newGame(4);

        $this->assertNull($this->take($game, 0, 0, 'a'));
        $this->assertNull($this->take($game, 1, 0, 'a'));
        $this->assertNull($this->take($game, 2, 0, 'a'));
        $path = $this->take($game, 3, 0, 'a');

        $game->refresh();
        $this->assertNotNull($path);
        $this->assertCount(4, $path);
        $this->assertTrue($game->isFinished());
        $this->assertSame('a', $game->winner());
    }

    public function test_team_b_wins_by_connecting_left_to_right(): void
    {
        $game = $this->newGame(4);

        foreach ([0, 1, 2] as $col) {
            $this->assertNull($this->take($game, 1, $col, 'b'));
        }
        $this->assertNotNull($this->take($game, 1, 3, 'b'));
        $this->assertSame('b', $game->refresh()->winner());
    }

    public function test_odd_row_neighbours_follow_the_shifted_layout(): void
    {
        // Row 1 is shifted right: (1,0) touches (0,0) and (0,1), (2,0) and (2,1).
        $n = Taqtaq::neighbours(1, 0, 5);
        $this->assertContains([0, 0], $n);
        $this->assertContains([0, 1], $n);
        $this->assertContains([2, 1], $n);

        // Row 2 is not shifted: (2,1) touches (1,0) and (1,1).
        $n = Taqtaq::neighbours(2, 1, 5);
        $this->assertContains([1, 0], $n);
        $this->assertContains([1, 1], $n);
        $this->assertNotContains([1, 2], $n);
    }

    public function test_a_game_never_repeats_a_question(): void
    {
        $game = $this->newGame(4);

        for ($row = 0; $row < 4; $row++) {
            for ($col = 0; $col < 4; $col++) {
                if ($game->refresh()->isFinished()) {
                    break 2;
                }
                // Alternate owners so nobody connects too early.
                $this->take($game, $row, $col, ($row + $col) % 2 ? 'a' : 'n');
            }
        }

        $ids = $game->cellQuestions()->pluck('question_id');
        $this->assertSame($ids->count(), $ids->unique()->count());
    }

    public function test_change_helper_swaps_the_question_once_per_team(): void
    {
        $game = $this->newGame();
        $this->engine()->open($game, $this->cell($game, 2, 2));
        $before = $game->refresh()->currentCellQuestion->question_id;

        $this->engine()->change($game->refresh()); // used by team B (not answering)
        $after = $game->refresh()->currentCellQuestion->question_id;

        $this->assertNotSame($before, $after);
        $this->expectException(DomainException::class);
        $this->engine()->change($game->refresh());
    }

    public function test_erase_frees_an_opponent_cell_unless_it_is_protected(): void
    {
        $game = $this->newGame();
        $this->take($game, 0, 0, 'a');       // A's cell, now B's turn
        $this->take($game, 4, 4, 'b');       // B's cell, now A's turn

        $this->engine()->protect($game->refresh(), $this->cell($game, 0, 0));   // A protects its own cell
        $this->assertTrue($this->cell($game, 0, 0)->protected);

        $this->engine()->switchTurn($game->refresh());                          // B's turn
        try {
            $this->engine()->erase($game->refresh(), $this->cell($game, 0, 0)); // protected → refused
            $this->fail('Erasing a protected cell should fail.');
        } catch (DomainException) {
            $this->assertSame('a', $this->cell($game, 0, 0)->owner);
        }

        $this->engine()->switchTurn($game->refresh());                          // A's turn
        $this->engine()->erase($game->refresh(), $this->cell($game, 4, 4));     // A erases B's cell
        $this->assertNull($this->cell($game, 4, 4)->owner);
    }

    public function test_setup_page_creates_a_taqtaq_game(): void
    {
        $ids = Taqtaq::playableCategories()->pluck('id');

        $component = Livewire::test(TaqtaqSetup::class)
            ->set('teamA', 'الصقور')
            ->set('teamB', 'النمور')
            ->set('boardSize', 6)
            ->set('answerSeconds', 30);

        foreach ($ids->take(4) as $id) {
            $component->call('toggle', $id);
        }

        $component->call('start')->assertHasNoErrors();

        $game = Game::where('mode', Game::TAQTAQ)->firstOrFail();
        $this->assertSame(6, $game->board_size);
        $this->assertSame(36, $game->cells()->count());
        $this->assertSame(30, $game->answer_seconds);
        $component->assertRedirect(route('taqtaq.play', $game));
    }

    public function test_play_page_runs_a_turn(): void
    {
        $game = $this->newGame();
        $cell = $this->cell($game, 2, 2);

        Livewire::test(TaqtaqPlay::class, ['game' => $game])
            ->assertSee('دور الصقور')
            ->call('clickCell', $cell->id)
            ->assertSet('revealed', false)
            ->call('reveal')
            ->assertSee('مين جاوب؟')
            ->call('award', 'a');

        $this->assertSame('a', $cell->fresh()->owner);
    }

    public function test_pages_load(): void
    {
        $this->get('/')->assertOk()->assertSee('طق طق');
        $this->get('/taqtaq/new')->assertOk()->assertSee('حجم اللوحة');

        $game = $this->newGame();
        $this->get(route('taqtaq.play', $game))->assertOk()->assertSee('لوحة طق طق', false);

        // The classic URL sends a طق طق game to its own screen.
        Livewire::test(\App\Livewire\GamePlay::class, ['game' => $game])->assertRedirect(route('taqtaq.play', $game));
    }

    public function test_three_categories_per_team_are_spread_over_the_board(): void
    {
        $ids = Taqtaq::playableCategories()->pluck('id');

        $component = Livewire::test(TaqtaqSetup::class)
            ->set('teamA', 'الصقور')
            ->set('teamB', 'النمور')
            ->set('categoriesPerTeam', 3);

        foreach ($ids->take(6) as $id) {
            $component->call('toggle', $id);
        }

        $component->call('start')->assertHasNoErrors();

        $game = Game::where('mode', Game::TAQTAQ)->firstOrFail();
        $this->assertCount(6, $game->cells()->get()->countBy('category_id'));
    }

    public function test_uneven_category_counts_are_rejected(): void
    {
        $ids = Taqtaq::playableCategories()->pluck('id');

        $this->expectException(DomainException::class);
        $this->engine()->start('أ', 'ب', $ids->slice(0, 3)->all(), $ids->slice(3, 2)->all());
    }
}
