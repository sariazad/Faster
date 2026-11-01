<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Game;
use DomainException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('لعبة جديدة — مين الأسبق')]
class GameSetup extends Component
{
    public string $teamA = 'الفريق الأول';

    public string $teamB = 'الفريق الثاني';

    /** @var array<int> */
    public array $selectedA = [];

    /** @var array<int> */
    public array $selectedB = [];

    /** Which team is picking now: 'a' or 'b'. */
    public string $picking = 'a';

    public ?string $notice = null;

    #[Computed]
    public function categories(): Collection
    {
        return Category::playable();
    }

    public function pickFor(string $team): void
    {
        $this->picking = $team === 'b' ? 'b' : 'a';
    }

    public function toggle(int $categoryId): void
    {
        $this->notice = null;

        // Clicking a selected category removes it from its team.
        foreach (['a' => 'selectedA', 'b' => 'selectedB'] as $team => $prop) {
            if (in_array($categoryId, $this->{$prop}, true)) {
                $this->{$prop} = array_values(array_diff($this->{$prop}, [$categoryId]));
                $this->picking = $team;

                return;
            }
        }

        if (! $this->categories->contains('id', $categoryId)) {
            return;
        }

        $prop = $this->picking === 'a' ? 'selectedA' : 'selectedB';

        if (count($this->{$prop}) >= Game::CATEGORIES_PER_TEAM) {
            $this->notice = 'كل فريق يختار '.Game::CATEGORIES_PER_TEAM.' فئات بس.';

            return;
        }

        $this->{$prop}[] = $categoryId;

        // Move on to the other team once this one is full.
        if (count($this->{$prop}) === Game::CATEGORIES_PER_TEAM) {
            $other = Game::other($this->picking);
            $otherProp = $other === 'a' ? 'selectedA' : 'selectedB';
            if (count($this->{$otherProp}) < Game::CATEGORIES_PER_TEAM) {
                $this->picking = $other;
            }
        }
    }

    public function shuffle(): void
    {
        $ids = $this->categories->pluck('id')->shuffle()->take(Game::CATEGORIES_PER_TEAM * 2)->values();

        if ($ids->count() < Game::CATEGORIES_PER_TEAM * 2) {
            $this->notice = 'ما فيه فئات كافية.';

            return;
        }

        $this->selectedA = $ids->take(Game::CATEGORIES_PER_TEAM)->all();
        $this->selectedB = $ids->slice(Game::CATEGORIES_PER_TEAM)->values()->all();
        $this->notice = null;
    }

    public function start(): void
    {
        $this->validate([
            'teamA' => 'required|string|max:40',
            'teamB' => 'required|string|max:40|different:teamA',
        ], [
            'teamA.required' => 'اكتب اسم الفريق الأول.',
            'teamB.required' => 'اكتب اسم الفريق الثاني.',
            'teamB.different' => 'اسم الفريقين لازم يكون مختلف.',
            'teamA.max' => 'الاسم طويل كثير.',
            'teamB.max' => 'الاسم طويل كثير.',
        ]);

        try {
            $game = Game::start($this->teamA, $this->teamB, $this->selectedA, $this->selectedB);
        } catch (DomainException $e) {
            $this->notice = $e->getMessage();

            return;
        }

        $this->redirectRoute('game.play', $game);
    }

    public function render()
    {
        return view('livewire.game-setup', [
            'perTeam' => Game::CATEGORIES_PER_TEAM,
            'ready' => count($this->selectedA) === Game::CATEGORIES_PER_TEAM
                && count($this->selectedB) === Game::CATEGORIES_PER_TEAM,
        ]);
    }
}
