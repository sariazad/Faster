<?php

namespace App\Support;

use Illuminate\Support\Arr;

/** All آخر القائمة interface text lives in resources/akher/strings.ar.json so it is easy to edit. */
class AkherStrings
{
    private static ?array $strings = null;

    public static function all(): array
    {
        return self::$strings ??= json_decode(file_get_contents(resource_path('akher/strings.ar.json')), true) ?: [];
    }

    public static function get(string $key, array $replace = []): mixed
    {
        $value = Arr::get(self::all(), $key, $key);

        if (is_string($value)) {
            foreach ($replace as $k => $v) {
                $value = str_replace(':'.$k, (string) $v, $value);
            }
        }

        return $value;
    }
}
