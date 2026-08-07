<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rucne vynimky nad ramec planu (napr. docasne zvysenie limitu,
        // beta feature pre konkretneho zakaznika).
        Schema::create('entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 80);
            $table->json('value');
            $table->timestamp('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'feature'], 'entitlement_overrides_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_overrides');
    }
};
