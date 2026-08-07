<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            // trialing | active | past_due | suspended | cancelled
            $table->string('status', 20)->default('trialing');
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            // do kedy trva grace perioda po zlyhanej platbe
            $table->timestamp('grace_ends_at')->nullable();
            // do kedy je read-only, potom uzamknutie
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->string('external_id')->nullable()->index();          // stripe sub_...
            $table->string('external_customer_id')->nullable()->index(); // stripe cus_...
            $table->json('meta')->nullable();

            $table->timestamps();

            // jedna organizacia = jedno aktivne predplatne na produkt
            $table->unique(['organization_id', 'product_id']);
            $table->index(['status', 'current_period_end']);
        });

        // Auditna stopa vsetkych zmien stavu
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
