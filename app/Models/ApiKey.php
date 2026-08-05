<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'key'])]
class ApiKey extends Model
{
    /**
     * Lunghezza delle chiavi generate.
     */
    public const LENGTH = 48;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => 'encrypted',
        ];
    }

    public static function generateSecret(): string
    {
        return Str::random(self::LENGTH);
    }

    /**
     * Crea una nuova chiave con un segreto casuale.
     */
    public static function generate(string $name): self
    {
        return static::create([
            'name' => $name,
            'key' => static::generateSecret(),
        ]);
    }

    /**
     * Sostituisce il segreto: quello precedente smette subito di funzionare.
     */
    public function regenerate(): string
    {
        $secret = static::generateSecret();

        $this->key = $secret;
        $this->save();

        return $secret;
    }

    /**
     * Segreto in chiaro, oppure null se la riga è stata cifrata con un'altra
     * APP_KEY (in quel caso la chiave è irrecuperabile: va rigenerata).
     */
    public function plainKey(): ?string
    {
        $stored = $this->getRawOriginal('key');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Chiave corrispondente al segreto fornito, se esiste.
     *
     * I segreti sono cifrati a riposo (servono in chiaro nel pannello), quindi
     * il confronto non può avvenire in SQL: si decifra riga per riga.
     */
    public static function findBySecret(string $secret): ?self
    {
        if ($secret === '') {
            return null;
        }

        foreach (static::query()->orderBy('id')->cursor() as $apiKey) {
            $stored = $apiKey->plainKey();

            if ($stored !== null && hash_equals($stored, $secret)) {
                return $apiKey;
            }
        }

        return null;
    }
}
