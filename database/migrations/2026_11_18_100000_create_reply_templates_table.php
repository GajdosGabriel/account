<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Šablóny odpovedí v inboxe (saved replies / canned responses).
 *
 * Bez projektu platí šablóna pre všetky projekty. S `user_id` ju vidí len
 * jej autor, inak celý tím. Text môže obsahovať premenné ({first_name},
 * {agent}…), dosadzujú sa pri vložení do odpovede.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reply_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('shortcut', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reply_templates');
    }
};
