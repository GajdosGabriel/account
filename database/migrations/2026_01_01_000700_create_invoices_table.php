<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number')->unique();
            $table->string('status', 20)->default('draft'); // draft|open|paid|void|uncollectible
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('vat_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->boolean('reverse_charge')->default(false);
            $table->char('currency', 3)->default('EUR');

            // Kopia fakturacnych udajov v case vystavenia (nemenna)
            $table->json('billing_snapshot')->nullable();

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('external_id')->nullable()->index();
            $table->string('pdf_url')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
