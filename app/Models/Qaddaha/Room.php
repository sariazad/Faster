<?php

namespace App\Models\Qaddaha;

class Room extends Model
{
    /** Room codes avoid look-alikes (O/0, I/1). */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const HOURS = 24;

    protected $table = 'rooms';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = ['state' => 'array', 'public' => 'array', 'version' => 'integer', 'finished' => 'boolean', 'expires_at' => 'datetime'];

    protected $hidden = ['host_token', 'state'];

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::whereKey($code)->exists());

        return $code;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isHost(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->host_token, self::hashToken($token));
    }

    /** The team (0/1) this device sits on, or null. */
    public function seatOf(?string $device): ?int
    {
        if (! $device) {
            return null;
        }
        if ($this->seat0 === $device) {
            return 0;
        }
        if ($this->seat1 === $device) {
            return 1;
        }

        return null;
    }

    public function seats(): array
    {
        return [$this->seat0 !== null, $this->seat1 !== null];
    }
}
