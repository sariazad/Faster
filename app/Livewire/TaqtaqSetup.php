<?php

namespace App\Livewire;

use App\Models\Game;
use App\Services\Taqtaq;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * Setup page for «طق طق»: same team names, timers and category picking as the
 * classic game, with two categories per team and a board size.
 */
#[Title('طق طق — مين الأسبق')]
class TaqtaqSetup extends GameSetup
{
    #[Locked]
    public string $mode = Game::TAQTAQ;

    public int $boardSize = Taqtaq::DEFAULT_SIZE;

    protected function loadCategories(): Collection
    {
        return Taqtaq::playableCategories();
    }

    protected function perTeam(): int
    {
        return Taqtaq::CATEGORIES_PER_TEAM;
    }

    protected function modeRules(): array
    {
        return ['boardSize' => ['required', 'integer', 'in:'.implode(',', Taqtaq::SIZES)]];
    }

    protected function createGame(): Game
    {
        return app(Taqtaq::class)->start(
            $this->teamA,
            $this->teamB,
            $this->selectedA,
            $this->selectedB,
            $this->boardSize,
            $this->answerSeconds,
            $this->stealSeconds,
        );
    }

    protected function modeViewData(): array
    {
        return ['sizeOptions' => Taqtaq::SIZES, 'boardSize' => $this->boardSize];
    }
}
