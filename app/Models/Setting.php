<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Chiavi il cui valore viene cifrato a riposo con la APP_KEY.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = ['smtp_password'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<string, string|null> */
    protected static array $resolved = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, static::$resolved)) {
            $value = static::find($key)?->value;

            if ($value !== null && in_array($key, self::SECRET_KEYS, true)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $value = null;
                }
            }

            static::$resolved[$key] = $value;
        }

        return static::$resolved[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        $stored = $value;

        if ($stored !== null && in_array($key, self::SECRET_KEYS, true)) {
            $stored = Crypt::encryptString($stored);
        }

        static::updateOrCreate(['key' => $key], ['value' => $stored]);

        static::$resolved[$key] = $value;
    }

    public static function flushResolved(): void
    {
        static::$resolved = [];
    }
}
