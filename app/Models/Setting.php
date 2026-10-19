<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Jedno nastavenie z back-office – prekrytie hodnoty z configu.
 *
 * Číta sa pri každej požiadavke, preto ide celé cez cache a zmena
 * ju zahodí. Keď tabuľka ešte neexistuje (čerstvá inštalácia pred
 * migráciou), aplikácia jednoducho beží na hodnotách z .env.
 */
class Setting extends Model
{
    public const CACHE_KEY = 'settings.overrides';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Hodnoty z configu (.env), ako boli pred prekrytím – kvôli tlačidlu
     * „pôvodná hodnota“ v UI.
     *
     * @var array<string, mixed>
     */
    protected static array $configDefaults = [];

    /** @return array<string, mixed> */
    public static function overrides(): array
    {
        try {
            if (is_array($cached = Cache::get(self::CACHE_KEY))) {
                return $cached;
            }

            // Bez tabuľky nič necachujeme – po migrácii sa musí prejaviť hneď.
            if (! Schema::hasTable('settings')) {
                return [];
            }

            $values = static::query()->pluck('value', 'key')->all();
            Cache::forever(self::CACHE_KEY, $values);

            return $values;
        } catch (Throwable) {
            return [];
        }
    }

    /** Prekryje config uloženými hodnotami. */
    public static function applyToConfig(): void
    {
        // Nový štart aplikácie (napr. ďalší test) = nový config.
        static::$configDefaults = [];

        foreach (static::overrides() as $key => $value) {
            if (! array_key_exists($key, static::$configDefaults)) {
                static::$configDefaults[$key] = config($key);
            }

            config()->set($key, $value);
        }
    }

    /** Hodnota z configu bez prekrytia z back-office. */
    public static function original(string $key): mixed
    {
        return array_key_exists($key, static::$configDefaults) ? static::$configDefaults[$key] : config($key);
    }

    /**
     * Uloží hodnotu a hneď ju prepíše aj v bežiacom configu.
     */
    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        if (! array_key_exists($key, static::$configDefaults)) {
            static::$configDefaults[$key] = config($key);
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);

        config()->set($key, $value);
    }

    /** Vráti kľúč na hodnotu z .env. */
    public static function forget(string $key): void
    {
        static::query()->whereKey($key)->get()->each->delete();

        if (array_key_exists($key, static::$configDefaults)) {
            config()->set($key, static::$configDefaults[$key]);
            unset(static::$configDefaults[$key]);
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
