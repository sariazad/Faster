<?php

namespace App\Support;

/**
 * Builds a comparison key for Arabic/English text so that near-identical
 * questions or answers ("لماح" / "لمّاح" / "«لماح»") are treated as the same.
 */
class TextKey
{
    public static function make(?string $text): string
    {
        $text = (string) $text;

        // Drop anything in parentheses: "2000م (1421هـ)" → "2000م".
        $text = preg_replace('/\(.*?\)/u', ' ', $text) ?? $text;

        // Remove tashkeel and tatweel.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $text = mb_strtolower($text);

        // Keep letters and digits only.
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        // Leading "ال" makes "الرياض" and "رياض" differ; strip it per word.
        $words = array_map(
            fn ($w) => mb_strlen($w) > 3 && str_starts_with($w, 'ال') ? mb_substr($w, 2) : $w,
            preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []
        );

        return implode(' ', $words);
    }
}
