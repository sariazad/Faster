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

    /** 2 or 3 categories per team. */
    public int $categoriesPerTeam = Taqtaq::CATEGORIES_PER_TEAM;

    /** Changing the count trims selections that no longer fit. */
    public function updatedCategoriesPerTeam(): void
    {
        if (! in_array($this->categoriesPerTeam, Taqtaq::CATEGORY_OPTIONS, true)) {
            $this->categoriesPerTeam = Taqtaq::CATEGORIES_PER_TEAM;
        }

        $this->selectedA = array_slice($this->selectedA, 0, $this->categoriesPerTeam);
        $this->selectedB = array_slice($this->selectedB, 0, $this->categoriesPerTeam);
    }

    protected function loadCategories(): Collection
    {
        return Taqtaq::playableCategories();
    }

    protected function perTeam(): int
    {
        return in_array($this->categoriesPerTeam, Taqtaq::CATEGORY_OPTIONS, true)
            ? $this->categoriesPerTeam
            : Taqtaq::CATEGORIES_PER_TEAM;
    }

    protected function modeRules(): array
    {
        return [
            'boardSize' => ['required', 'integer', 'in:'.implode(',', Taqtaq::SIZES)],
            'categoriesPerTeam' => ['required', 'integer', 'in:'.implode(',', Taqtaq::CATEGORY_OPTIONS)],
        ];
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
        return [
            'sizeOptions' => Taqtaq::SIZES,
            'boardSize' => $this->boardSize,
            'categoryOptions' => Taqtaq::CATEGORY_OPTIONS,
            'categoriesPerTeam' => $this->perTeam(),
        ];
    }
}
