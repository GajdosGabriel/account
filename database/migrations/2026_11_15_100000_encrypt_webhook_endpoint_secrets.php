<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tajomstvo webhooku je podpisový kľúč – kto ho má, vie projektu podstrčiť
 * falošnú udalosť. Odteraz je v databáze šifrované ako heslá schránok.
 * Šifrovaná hodnota sa do 64 znakov nezmestí, preto stĺpec rastie na text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('secret')->change();
        });

        DB::table('webhook_endpoints')->orderBy('id')->each(function (object $row) {
            if ($this->isEncrypted($row->secret)) {
                return;
            }

            DB::table('webhook_endpoints')->where('id', $row->id)
                ->update(['secret' => Crypt::encryptString($row->secret)]);
        });
    }

    public function down(): void
    {
        DB::table('webhook_endpoints')->orderBy('id')->each(function (object $row) {
            if (! $this->isEncrypted($row->secret)) {
                return;
            }

            DB::table('webhook_endpoints')->where('id', $row->id)
                ->update(['secret' => Crypt::decryptString($row->secret)]);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('secret', 64)->change();
        });
    }

    private function isEncrypted(?string $value): bool
    {
        if (blank($value)) {
            return true;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
