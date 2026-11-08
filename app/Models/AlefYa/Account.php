<?php

namespace App\Models\AlefYa;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A login ID (10 digits) with a 4-digit PIN that is set on the first sign-in.
 * Demo IDs for now; a payment step can create them later.
 */
class Account extends Model
{
    protected $hidden = ['pin'];

    protected $casts = ['last_login_at' => 'datetime'];

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public static function generate(): self
    {
        do {
            $code = (string) random_int(1, 9).str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return static::create(['code' => $code]);
    }

    /**
     * @return array{0: bool, 1: bool} [ok, firstTime]
     */
    public function attempt(string $pin): array
    {
        if ($this->pin === null) {
            $this->forceFill(['pin' => Hash::make($pin), 'last_login_at' => now()])->save();

            return [true, true];
        }

        if (! Hash::check($pin, $this->pin)) {
            return [false, false];
        }

        $this->forceFill(['last_login_at' => now()])->save();

        return [true, false];
    }

    /** 123****890 */
    public function masked(): string
    {
        return substr($this->code, 0, 3).'****'.substr($this->code, -3);
    }
}
