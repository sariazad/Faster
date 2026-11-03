<?php

namespace App\Livewire;

use App\Models\Game;
use App\Models\GameQuestion;
use DomainException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('مين الأسبق')]
class GamePlay extends Component
{
    public Game $game;

    /** Answer shown to the room (host pressed "إظهار الإجابة"). */
    public bool $revealed = false;

    public ?string $flash = null;

    public function mount(Game $game): void
    {
        $this->game = $game;
    }

    public function open(int $gameQuestionId): void
    {
        $this->run(function () use ($gameQuestionId) {
            $gameQuestion = $this->game->gameQuestions()->findOrFail($gameQuestionId);
            $this->game->open($gameQuestion);
            $this->revealed = false;
            $this->dispatch('timer-start', main: $this->game->answer_seconds, steal: $this->game->steal_seconds);
        });
    }

    public function useHelper(string $helper): void
    {
        $this->run(function () use ($helper) {
            $this->game->useHelper($helper);

            if ($helper === 'extra_time') {
                $this->dispatch('timer-add', seconds: Game::EXTRA_SECONDS);
            }

            if ($helper === 'no_steal') {
                $this->dispatch('timer-lock');
            }
        });
    }

    public function reveal(): void
    {
        $this->flash = null;
        $this->revealed = true;
        $this->dispatch('timer-stop');
    }

    public function award(string $team): void
    {
        $this->run(function () use ($team) {
            $points = $this->game->award($team);
            $this->revealed = false;

            $this->flash = $team === 'n'
                ? 'ولا أحد أخذ النقاط'
                : "+{$points} لـ ".$this->game->teamName($team);
        });
    }

    public function cancel(): void
    {
        $this->flash = null;
        $this->game->cancelCurrent();
        $this->revealed = false;
        $this->dispatch('timer-stop');
    }

    public function switchTurn(): void
    {
        $this->run(fn () => $this->game->switchTurn());
    }

    public function adjust(string $team, int $delta): void
    {
        $delta = max(-600, min(600, $delta));
        $this->run(fn () => $this->game->adjustScore($team, $delta));
    }

    public function finish(): void
    {
        $this->run(fn () => $this->game->finish());
    }

    protected function run(callable $action): void
    {
        $this->flash = null;

        try {
            $action();
        } catch (DomainException $e) {
            $this->flash = $e->getMessage();
        }

        $this->game->refresh();
    }

    public function render()
    {
        $game = $this->game;
        $game->loadMissing('categories');

        $cells = $game->gameQuestions()
            ->get(['id', 'category_id', 'points', 'slot', 'status', 'answered_by'])
            ->groupBy('category_id');

        $current = $game->current_game_question_id
            ? GameQuestion::with(['question', 'category'])->find($game->current_game_question_id)
            : null;

        return view('livewire.game-play', [
            'columns' => $game->categories,
            'cells' => $cells,
            'current' => $current,
            'helpers' => Game::HELPERS,
            'levels' => Game::LEVELS,
            'perLevel' => Game::QUESTIONS_PER_LEVEL,
        ]);
    }
}
