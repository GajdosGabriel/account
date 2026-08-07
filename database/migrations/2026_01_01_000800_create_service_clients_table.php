<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Server-to-server tokeny, ktorymi sa pripojene projekty
        // autentifikuju voci /api/v1/*.
        Schema::create('service_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_prefix', 12)->index();
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable(); // ["organizations:read","entitlements:read"]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_clients');
    }
};
