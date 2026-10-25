<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odosielatelia označení v inboxe ako spam. Ich ďalšie maily idú rovno
 * do Spamu – bez webhooku projektu a bez počítadla neprečítaných.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_blocks');
    }
};
