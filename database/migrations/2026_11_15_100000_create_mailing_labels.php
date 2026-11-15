<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ručné štítky kontaktov ako samostatné záznamy – s názvom (aj s diakritikou)
 * a farbou, aby sa dali premenovať, zafarbiť a zmazať naraz pri všetkých
 * kontaktoch. Kontakt si naďalej drží len kľúče v `manual_tags`.
 *
 * Štítky z projektu sú v `mailing_tags` a spravuje ich projekt cez API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 60);
            $table->string('color', 20)->default('indigo');
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });

        // Štítky, ktoré už kontakty majú, dostanú záznam s kľúčom ako názvom.
        $now = now();
        DB::table('mailing_contacts')->whereNotNull('manual_tags')->select(['product_id', 'manual_tags'])->orderBy('id')
            ->get()
            ->flatMap(fn ($row) => collect(json_decode((string) $row->manual_tags, true) ?: [])
                ->map(fn ($key) => ['product_id' => $row->product_id, 'key' => $key]))
            ->unique(fn ($row) => $row['product_id'].':'.$row['key'])
            ->each(fn ($row) => DB::table('mailing_labels')->insert([
                ...$row,
                'name' => Str::limit(Str::ucfirst(str_replace('-', ' ', $row['key'])), 60, ''),
                'color' => 'indigo',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_labels');
    }
};
