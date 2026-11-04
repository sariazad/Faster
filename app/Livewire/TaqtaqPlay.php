<?php

namespace App\Livewire;

use App\Models\CellQuestion;
use App\Models\Game;
use App\Services\Taqtaq;
use DomainException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('طق طق — مين الأسبق')]
class TaqtaqPlay extends Component
{
    public Game $game;

    /** Answer shown to the room. */
    public bool $revealed = false;

    public ?string $flash = null;

    /** A helper waiting for the host to pick a cell: erase | protect. */
    public ?string $targeting = null;

    public function mount(Game $game): void
    {
        if (! $game->isTaqtaq()) {
            $this->redirect($game->playUrl());
        }

        $this->game = $game;
    }

    protected function engine(): Taqtaq
    {
        return app(Taqtaq::class);
    }

    public function clickCell(int $cellId): void
    {
        $this->run(function () use ($cellId) {
            $cell = $this->game->cells()->findOrFail($cellId);

            if ($this->targeting === 'erase') {
                $this->engine()->erase($this->game, $cell);
                $this->targeting = null;
                $this->flash = '🧽 انمسحت الخلية';

                return;
            }

            if ($this->targeting === 'protect') {
                $this->engine()->protect($this->game, $cell);
                $this->targeting = null;
                $this->flash = '🛡️ الخلية صارت محمية';

                return;
            }

            $this->engine()->open($this->game, $cell);
            $this->revealed = false;
        });
    }

    public function target(string $helper): void
    {
        $this->flash = null;

        if (! in_array($helper, ['erase', 'protect'], true)) {
            return;
        }

        if ($this->game->current_cell_question_id) {
            $this->flash = 'خلّصوا السؤال المفتوح أول.';

            return;
        }

        if (! $this->engine()->helperAvailable($this->game, $this->game->current_team, $helper)) {
            $this->flash = 'هالوسيلة مستخدمة قبل.';

            return;
        }

        $this->targeting = $this->targeting === $helper ? null : $helper;
    }

    public function change(): void
    {
        $this->run(function () {
            $this->engine()->change($this->game);
            $this->revealed = false;
            $this->flash = '🔄 تبدّل السؤال';
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
            $path = $this->engine()->award($this->game, $team);
            $this->revealed = false;

            $this->flash = match (true) {
                (bool) $path => '🎉 من طق طق… للسلام عليكم!',
                $team === 'n' => 'ولا أحد أخذ الخلية',
                default => 'الخلية لـ '.$this->game->teamName($team),
            };
        });
    }

    public function cancel(): void
    {
        $this->flash = null;
        $this->engine()->cancel($this->game);
        $this->revealed = false;
        $this->dispatch('timer-stop');
    }

    public function switchTurn(): void
    {
        $this->targeting = null;
        $this->run(fn () => $this->engine()->switchTurn($this->game));
    }

    public function finish(): void
    {
        $this->targeting = null;
        $this->run(fn () => $this->engine()->finish($this->game));
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
        $engine = $this->engine();

        $cells = $game->cells()->with('category:id,name,icon')->get();

        $current = $game->current_cell_question_id
            ? CellQuestion::with(['question', 'cell.category'])->find($game->current_cell_question_id)
            : null;

        return view('livewire.taqtaq-play', [
            'cells' => $cells,
            'size' => (int) $game->board_size,
            'current' => $current,
            'legend' => $game->categories()->get(),
            'helpers' => Taqtaq::HELPERS,
            'counts' => ['a' => $cells->where('owner', 'a')->count(), 'b' => $cells->where('owner', 'b')->count()],
            'available' => fn (string $team, string $helper) => $engine->helperAvailable($game, $team, $helper),
            'path' => array_flip($game->winning_path ?? []),
        ]);
    }
}
