<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontaktné osoby vo firme.
     *
     * Nie sú to používatelia – tí zostávajú v projektoch. Toto je
     * telefónny zoznam: komu volať kvôli faktúre, komu kvôli technike.
     */
    public function up(): void
    {
        Schema::create('organization_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20)->default('general'); // general | billing | technical | statutory
            $table->string('name');
            $table->string('position', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_contacts');
    }
};
