<?php

namespace Database\Seeders;

use App\Models\Akher\GameList;
use Illuminate\Database\Seeder;

/**
 * Loads the آخر القائمة lists (database/seeders/data/akher/*.json) into the separate "akher"
 * database. Lists that already exist are left alone, so edits made in the admin panel stay.
 */
class AkherSeeder extends Seeder
{
    public const ORDER = ['area', 'population', 'cities'];

    public function run(): void
    {
        $added = 0;

        foreach (glob(database_path('seeders/data/akher/*.json')) as $file) {
            $data = json_decode(file_get_contents($file), true);

            if (! $data || GameList::whereKey($data['id'])->exists()) {
                continue;
            }

            $list = GameList::fromFile($data);
            $sort = array_search($list->id, self::ORDER, true);
            $list->update(['sort' => $sort === false ? 50 : $sort]);
            $added++;
        }

        $this->command?->info("آخر القائمة: انضافت {$added} قائمة (المجموع ".GameList::count().').');
    }
}
