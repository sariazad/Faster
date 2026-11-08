<?php

namespace App\Support;

/** The 28 letters of the ألف ياء board, and which one an answer starts with. */
class AlefYaLetters
{
    public const ALL = ['أ', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص',
        'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'هـ', 'و', 'ي'];

    /** First letter of the answer, ignoring "ال" and tashkeel. Null when it isn't an Arabic letter. */
    public static function of(?string $answer): ?string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}«»"\'()]/u', '', trim((string) $answer)) ?? '';
        $word = preg_split('/\s+/u', $text, 2)[0] ?? '';

        if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
            $word = mb_substr($word, 2);
        }

        $first = mb_substr($word, 0, 1);
        $first = ['ا' => 'أ', 'إ' => 'أ', 'آ' => 'أ', 'ٱ' => 'أ', 'ه' => 'هـ', 'ة' => 'هـ', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'][$first] ?? $first;

        return in_array($first, self::ALL, true) ? $first : null;
    }
}
