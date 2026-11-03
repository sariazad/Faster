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

    public string $search = '';

    /** Seconds the picking team gets to answer. */
    public int $answerSeconds = Game::MAIN_SECONDS;

    /** Seconds the other team gets to steal. */
    public int $stealSeconds = Game::STEAL_SECONDS;

    public ?string $notice = null;

    /** All categories that can be played (enough questions in every level). */
    #[Computed]
    public function categories(): Collection
    {
        return Category::playable();
    }

    /** Categories shown in the grid after the search filter. */
    #[Computed]
    public function visibleCategories(): Collection
    {
        $term = trim($this->search);

        if ($term === '') {
            return $this->categories;
        }

        return $this->categories
            ->filter(fn (Category $c) => mb_stripos($c->name, $term) !== false)
            ->values();
    }

    public function pickFor(string $team): void
    {
        $this->picking = $team === 'b' ? 'b' : 'a';
    }

    public function toggle(int $categoryId): void
    {
        $this->notice = null;

        // Clicking a selected category removes it from its team.
        foreach (['a', 'b'] as $team) {
            if (in_array($categoryId, $this->selection($team), true)) {
                $this->remove($team, $categoryId);

                return;
            }
        }

        if (! $this->categories->contains('id', $categoryId)) {
            return;
        }

        $team = $this->picking;

        if (count($this->selection($team)) >= Game::CATEGORIES_PER_TEAM) {
            $other = Game::other($team);

            if (count($this->selection($other)) >= Game::CATEGORIES_PER_TEAM) {
                $this->notice = 'الفريقين اختاروا فئاتهم. شيل فئة إذا بدك تغيّر.';

                return;
            }

            $team = $this->picking = $other;
        }

        $this->setSelection($team, [...$this->selection($team), $categoryId]);

        // Move on to the other team once this one is full.
        if (count($this->selection($team)) === Game::CATEGORIES_PER_TEAM
            && count($this->selection(Game::other($team))) < Game::CATEGORIES_PER_TEAM) {
            $this->picking = Game::other($team);
        }
    }

    public function remove(string $team, int $categoryId): void
    {
        $team = $team === 'b' ? 'b' : 'a';
        $this->setSelection($team, array_values(array_diff($this->selection($team), [$categoryId])));
        $this->picking = $team;
        $this->notice = null;
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
            'answerSeconds' => ['required', 'integer', 'in:'.implode(',', Game::ANSWER_OPTIONS)],
            'stealSeconds' => ['required', 'integer', 'in:'.implode(',', Game::STEAL_OPTIONS)],
        ], [
            'answerSeconds.in' => 'اختر مدة إجابة من الخيارات.',
            'stealSeconds.in' => 'اختر مدة سرقة من الخيارات.',
            'teamA.required' => 'اكتب اسم الفريق الأول.',
            'teamB.required' => 'اكتب اسم الفريق الثاني.',
            'teamB.different' => 'اسم الفريقين لازم يكون مختلف.',
            'teamA.max' => 'الاسم طويل كثير.',
            'teamB.max' => 'الاسم طويل كثير.',
        ]);

        try {
            $game = Game::start(
                $this->teamA,
                $this->teamB,
                $this->selectedA,
                $this->selectedB,
                $this->answerSeconds,
                $this->stealSeconds,
            );
        } catch (DomainException $e) {
            $this->notice = $e->getMessage();

            return;
        }

        $this->redirectRoute('game.play', $game);
    }

    /** @return array<int> */
    private function selection(string $team): array
    {
        return $team === 'a' ? $this->selectedA : $this->selectedB;
    }

    /** @param array<int> $ids */
    private function setSelection(string $team, array $ids): void
    {
        if ($team === 'a') {
            $this->selectedA = $ids;
        } else {
            $this->selectedB = $ids;
        }
    }

    public function render()
    {
        $byId = $this->categories->keyBy('id');

        return view('livewire.game-setup', [
            'perTeam' => Game::CATEGORIES_PER_TEAM,
            'answerOptions' => Game::ANSWER_OPTIONS,
            'stealOptions' => Game::STEAL_OPTIONS,
            'ready' => count($this->selectedA) === Game::CATEGORIES_PER_TEAM
                && count($this->selectedB) === Game::CATEGORIES_PER_TEAM,
            'slots' => [
                'a' => collect($this->selectedA)->map(fn ($id) => $byId->get($id))->filter()->values(),
                'b' => collect($this->selectedB)->map(fn ($id) => $byId->get($id))->filter()->values(),
            ],
        ]);
    }
}
