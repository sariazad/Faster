<?php

namespace Tests\Feature;

use App\Services\Akher\Matcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Acceptance table from the spec, on the "largest 100 countries by area" list. */
class AkherMatcherTest extends TestCase
{
    private static ?Matcher $matcher = null;

    private static array $names = [];

    private static function list(string $id): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2)."/database/seeders/data/akher/$id.json"), true);
    }

    private static function matcher(): Matcher
    {
        if (! self::$matcher) {
            $items = self::list('area')['items'];
            self::$matcher = new Matcher($items);
            self::$names = array_column($items, 'name_ar', 'rank');
        }

        return self::$matcher;
    }

    public static function acceptance(): array
    {
        return [
            ['بولنده', 'match', 'بولندا'],
            ['kenya', 'match', 'كينيا'],
            ['اوروغواي', 'match', 'الأوروغواي'],
            ['سوريه', 'match', 'سوريا'],
            ['قيرقيزستان', 'match', 'قيرغيزستان'],
            ['بنغلادش', 'match', 'بنغلاديش'],
            ['بريطانيا', 'match', 'المملكة المتحدة'],
            ['غينيا', 'match', 'غينيا'],
            ['جنوب', 'unclear', null],
            ['الكونغو', 'unclear', null],
            ['مالطا', 'none', null],
            ['النمسا', 'none', null],
        ];
    }

    #[DataProvider('acceptance')]
    public function test_acceptance(string $guess, string $expected, ?string $name): void
    {
        $r = self::matcher()->match($guess);

        $this->assertSame($expected, $r['result'], $guess);
        if ($name) {
            $this->assertSame($name, self::$names[$r['rank']], $guess);
        }
    }

    public function test_normalisation(): void
    {
        $this->assertSame('ارجنتين', Matcher::norm('الأَرْجَنْتِين'));
        $this->assertSame('مملكه متحده', Matcher::norm('المملكة  المتحدة!'));
        $this->assertSame('united states', Matcher::norm('The United-States'));
    }

    public function test_every_name_and_alias_finds_its_own_item_in_every_list(): void
    {
        foreach (['area', 'population', 'cities'] as $id) {
            $list = self::list($id);
            $this->assertCount(100, $list['items'], $id);
            $matcher = new Matcher($list['items']);

            foreach ($list['items'] as $item) {
                foreach (array_merge([$item['name_ar'], $item['name_en']], $item['aliases']) as $form) {
                    $r = $matcher->match($form);
                    $this->assertSame(['match', $item['rank']], [$r['result'], $r['rank']], "$id: $form");
                }
            }
        }
    }

    public function test_short_unknown_guesses_are_rejected(): void
    {
        $this->assertSame('none', self::matcher()->match('بلا')['result']);
        $this->assertSame('none', self::matcher()->match('   ')['result']);
    }
}
