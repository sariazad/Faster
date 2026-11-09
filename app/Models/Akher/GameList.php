<?php

namespace App\Models\Akher;

use App\Services\Akher\Matcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameList extends Model
{
    protected $table = 'lists';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = ['is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id')->orderBy('rank');
    }

    public function scopePlayable(Builder $query): Builder
    {
        return $query->where('is_active', true)->has('items', '>=', 10)->withCount('items')->orderBy('sort')->orderBy('title_ar');
    }

    public function matcher(): Matcher
    {
        return new Matcher($this->items->map(fn (ListItem $i) => $i->only(['rank', 'name_ar', 'name_en', 'aliases']))->all());
    }

    /** The JSON file format: { id, title_ar, extra_label, items: [...] } */
    public function toFile(): array
    {
        return [
            'id' => $this->id,
            'title_ar' => $this->title_ar,
            'extra_label' => $this->extra_label,
            'unit_hint' => $this->unit_hint,
            'source' => $this->source,
            'updated' => $this->updated,
            'items' => $this->items->map(fn (ListItem $i) => [
                'rank' => $i->rank,
                'name_ar' => $i->name_ar,
                'name_en' => $i->name_en,
                'aliases' => $i->aliases ?? [],
                'extra' => $i->extra,
            ])->values()->all(),
        ];
    }

    /** Creates or replaces a list (and all its items) from the JSON file format. */
    public static function fromFile(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        if (! preg_match('/^[a-z0-9_-]{2,40}$/', $id) || empty($data['title_ar']) || ! is_array($data['items'] ?? null)) {
            throw new \InvalidArgumentException('الملف لازم يكون فيه id (أحرف إنجليزية صغيرة) وtitle_ar وitems.');
        }

        return static::query()->getConnection()->transaction(function () use ($data, $id) {
            $list = static::updateOrCreate(['id' => $id], [
                'title_ar' => $data['title_ar'],
                'extra_label' => $data['extra_label'] ?? null,
                'unit_hint' => $data['unit_hint'] ?? null,
                'source' => $data['source'] ?? null,
                'updated' => $data['updated'] ?? null,
            ]);
            $list->items()->delete();
            foreach ($data['items'] as $item) {
                ListItem::create([
                    'list_id' => $id,
                    'rank' => (int) $item['rank'],
                    'name_ar' => trim($item['name_ar']),
                    'name_en' => $item['name_en'] ?? null,
                    'aliases' => array_values(array_filter($item['aliases'] ?? [])),
                    'extra' => $item['extra'] ?? null,
                ]);
            }

            return $list;
        });
    }

    /**
     * Problems that make a list unfair to play: missing ranks, and names/aliases shared by two
     * items (they'd always be "unclear").
     *
     * @return string[]
     */
    public static function problems(array $data): array
    {
        $problems = [];
        $ranks = array_map(fn ($i) => (int) $i['rank'], $data['items'] ?? []);
        sort($ranks);
        $count = count($ranks);
        if ($ranks !== range(1, max(1, $count)) || $count === 0) {
            $problems[] = 'الترتيب لازم يكون من 1 لـ'.$count.' من غير فراغات أو تكرار.';
        }

        $owners = [];
        foreach ($data['items'] ?? [] as $item) {
            foreach (array_merge([$item['name_ar'] ?? '', $item['name_en'] ?? ''], $item['aliases'] ?? []) as $form) {
                $key = str_replace(' ', '', Matcher::norm((string) $form));
                if ($key === '') {
                    continue;
                }
                $owners[$key][$item['rank']] = $form;
            }
        }
        foreach ($owners as $forms) {
            if (count($forms) > 1) {
                $parts = [];
                foreach ($forms as $rank => $form) {
                    $parts[] = "«{$form}» (#{$rank})";
                }
                $problems[] = 'اسم مشترك بين أكتر من عنصر: '.implode(' و', $parts);
            }
        }

        return $problems;
    }
}
