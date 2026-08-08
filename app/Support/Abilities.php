<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Prevod policy pravidiel na mapu pre frontend.
 *
 * Kontextové menu vo Vue nerozhoduje o tom, čo smie používateľ spraviť –
 * len vykreslí to, čo mu server v `can` pošle. Vďaka tomu sa nikdy
 * neukáže tlačidlo, ktoré by policy vzápätí odmietla, a zmena pravidla
 * v policy sa prejaví v UI bez zásahu do šablóny.
 *
 * Ak model nemá policy, Gate vráti false a položka sa jednoducho
 * nevykreslí – to je bezpečné zlyhanie, nie chyba.
 */
class Abilities
{
    /** Akcie, ktoré má každý spravovaný záznam: zobraziť, upraviť, vymazať, kôš. */
    public const STANDARD = ['view', 'update', 'delete', 'restore', 'forceDelete'];

    /**
     * @param  array<int, string>  $checks
     * @return array<string, bool>
     */
    public static function for(Model $model, array $checks = self::STANDARD): array
    {
        $map = [];

        foreach ($checks as $ability) {
            $map[$ability] = Gate::allows($ability, $model);
        }

        return $map;
    }

    /**
     * Údaje, ktoré potrebuje komponenta RowActions: povolené akcie
     * a informácia, či je záznam v koši.
     *
     * @param  array<int, string>  $checks
     * @return array<string, mixed>
     */
    public static function payload(Model $model, array $checks = self::STANDARD): array
    {
        return [
            'can' => static::for($model, $checks),
            'deleted_at' => method_exists($model, 'trashed') && $model->trashed()
                ? $model->deleted_at?->toIso8601String()
                : null,
        ];
    }
}
