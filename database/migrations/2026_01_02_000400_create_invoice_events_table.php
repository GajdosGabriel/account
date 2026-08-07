<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * História dokladu – kto ho vystavil, kedy odišiel e-mail, kedy prišla platba.
 * Pri spore so zákazníkom je toto to jediné, čo sa ráta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // created|issued|sent|viewed|paid|partially_paid|reminded|cancelled|credited|exported
            $table->string('event', 30);
            $table->string('description')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
    }
};
