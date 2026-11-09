<?php

namespace App\Services\Akher;

/**
 * آخر القائمة — decides what a typed guess refers to.
 *
 * Results: match (one item), unclear (could be several), none (not on the list).
 * "dup" is decided by the caller (the item was already taken).
 * Numbers live in resources/akher/matcher.config.json.
 */
class Matcher
{
    public const MATCH = 'match';
    public const UNCLEAR = 'unclear';
    public const NONE = 'none';

    private array $config;

    /** @var array<int, array{rank:int, spaced:string[], compact:string[], words:string[]}> */
    private array $items = [];

    private array $groupOf = [];

    /**
     * @param  array<int, array{rank:int, name_ar:string, name_en?:?string, aliases?:?array}>  $items
     */
    public function __construct(array $items, ?array $config = null)
    {
        $this->config = $config ?? self::defaultConfig();

        foreach ($this->config['groups'] as $g => $letters) {
            foreach (mb_str_split($letters) as $ch) {
                $this->groupOf[$ch] = $g;
            }
        }

        foreach ($items as $item) {
            $forms = array_filter(array_merge([$item['name_ar'] ?? '', $item['name_en'] ?? ''], $item['aliases'] ?? []));
            $spaced = [];
            foreach ($forms as $form) {
                $n = self::norm((string) $form);
                if ($n !== '') {
                    $spaced[$n] = true;
                }
            }
            $spaced = array_keys($spaced);
            $words = [];
            foreach ($spaced as $s) {
                if (str_contains($s, ' ')) {
                    foreach (explode(' ', $s) as $w) {
                        $words[$w] = true;
                    }
                }
            }
            $this->items[] = [
                'rank' => (int) $item['rank'],
                'spaced' => $spaced,
                'compact' => array_values(array_unique(array_map(fn ($s) => str_replace(' ', '', $s), $spaced))),
                'words' => array_keys($words),
            ];
        }
    }

    public static function defaultConfig(): array
    {
        static $config = null;

        return $config ??= json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/akher/matcher.config.json'), true);
    }

    /** Normal form: lower case, no tashkeel, unified letters, no punctuation, no "ال"/"the". */
    public static function norm(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'ء' => '']);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        $words = [];
        foreach (preg_split('/\s+/u', trim($text)) as $w) {
            if ($w === '' || $w === 'the') {
                continue;
            }
            if (mb_strlen($w) > 3 && str_starts_with($w, 'ال')) {
                $w = mb_substr($w, 2);
            }
            $words[] = $w;
        }

        return implode(' ', $words);
    }

    /**
     * @return array{result: string, rank: ?int, candidates: int[]}
     */
    public function match(string $guess): array
    {
        $spaced = self::norm($guess);
        $compact = str_replace(' ', '', $spaced);

        if ($compact === '') {
            return $this->result(self::NONE);
        }

        // 1) Exact (ignoring spaces)
        $exact = [];
        foreach ($this->items as $item) {
            if (in_array($compact, $item['compact'], true)) {
                $exact[] = $item['rank'];
            }
        }
        if (count($exact) === 1) {
            return $this->result(self::MATCH, $exact[0]);
        }
        if (count($exact) > 1) {
            return $this->result(self::UNCLEAR, null, $exact);
        }

        // 2) One whole word of a compound name ("جنوب", "كونغو")
        if (! str_contains($spaced, ' ') && mb_strlen($spaced) >= $this->config['single_word_min']) {
            $hits = [];
            foreach ($this->items as $item) {
                if (in_array($spaced, $item['words'], true)) {
                    $hits[] = $item['rank'];
                }
            }
            $hits = array_values(array_unique($hits));
            if (count($hits) > 1) {
                return $this->result(self::UNCLEAR, null, $hits);
            }
            if (count($hits) === 1 && mb_strlen($spaced) >= $this->config['single_word_match_min']) {
                return $this->result(self::MATCH, $hits[0]);
            }
        }

        if (mb_strlen($compact) < $this->config['min_fuzzy_length']) {
            return $this->result(self::NONE);
        }

        // 3) Fuzzy: weighted Damerau-Levenshtein
        $scores = [];
        foreach ($this->items as $item) {
            $best = 0.0;
            foreach ($item['spaced'] as $form) {
                $best = max($best, $this->similarity($spaced, $form), $this->similarity($compact, str_replace(' ', '', $form)));
            }
            $scores[$item['rank']] = $best;
        }
        arsort($scores);
        $ranks = array_keys($scores);
        $first = $scores[$ranks[0]] ?? 0;
        $second = isset($ranks[1]) ? $scores[$ranks[1]] : 0;

        if ($first >= $this->config['threshold']) {
            if ($second >= $first - $this->config['ambiguity_gap']) {
                return $this->result(self::UNCLEAR, null, array_slice($ranks, 0, 2));
            }

            return $this->result(self::MATCH, $ranks[0]);
        }

        return $this->result(self::NONE);
    }

    public function similarity(string $a, string $b): float
    {
        $len = max(mb_strlen($a), mb_strlen($b));

        return $len === 0 ? 0.0 : 1 - $this->distance($a, $b) / $len;
    }

    /** Weighted optimal-string-alignment distance (Damerau-Levenshtein with adjacent swaps). */
    public function distance(string $a, string $b): float
    {
        $s = mb_str_split($a);
        $t = mb_str_split($b);
        $n = count($s);
        $m = count($t);
        $c = $this->config['costs'];
        $weak = mb_str_split($this->config['weak_letters']);
        $indel = fn (string $ch) => in_array($ch, $weak, true) ? $c['weak_insert_delete'] : $c['insert_delete'];

        $d = [];
        $d[0][0] = 0.0;
        for ($i = 1; $i <= $n; $i++) {
            $d[$i][0] = $d[$i - 1][0] + $indel($s[$i - 1]);
        }
        for ($j = 1; $j <= $m; $j++) {
            $d[0][$j] = $d[0][$j - 1] + $indel($t[$j - 1]);
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $x = $s[$i - 1];
                $y = $t[$j - 1];
                $sub = $x === $y ? 0.0 : $this->substitution($x, $y, $s, $t, $i - 1, $j - 1);
                $v = min(
                    $d[$i - 1][$j] + $indel($x),
                    $d[$i][$j - 1] + $indel($y),
                    $d[$i - 1][$j - 1] + $sub,
                );
                if ($i > 1 && $j > 1 && $x === $t[$j - 2] && $s[$i - 2] === $y && $x !== $y) {
                    $v = min($v, $d[$i - 2][$j - 2] + $c['transpose']);
                }
                $d[$i][$j] = $v;
            }
        }

        return $d[$n][$m];
    }

    private function substitution(string $x, string $y, array $s, array $t, int $i, int $j): float
    {
        $c = $this->config['costs'];
        $endOfWord = fn (array $w, int $k) => ! isset($w[$k + 1]) || $w[$k + 1] === ' ';

        if ((($x === 'ه' && $y === 'ا') || ($x === 'ا' && $y === 'ه')) && $endOfWord($s, $i) && $endOfWord($t, $j)) {
            $cost = $c['final_ha_alef'];
        } elseif (isset($this->groupOf[$x], $this->groupOf[$y]) && $this->groupOf[$x] === $this->groupOf[$y]) {
            $cost = $c['same_group'];
        } else {
            $cost = $c['substitute'];
        }

        $firstLetter = fn (array $w, int $k) => $k === 0 || $w[$k - 1] === ' ';
        if ($firstLetter($s, $i) || $firstLetter($t, $j)) {
            $cost *= $c['first_letter_multiplier'];
        }

        return $cost;
    }

    private function result(string $result, ?int $rank = null, array $candidates = []): array
    {
        return ['result' => $result, 'rank' => $rank, 'candidates' => $candidates];
    }
}
