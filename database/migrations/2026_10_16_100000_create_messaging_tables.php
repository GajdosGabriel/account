<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komunikácia s klientmi projektov.
 *
 * Account posiela maily v mene projektu, číta odpovede z IMAP schránok
 * a páruje ich do konverzácií. Čo je „vec“, o ktorej sa píše (kanál,
 * objednávka…), vie len projekt – tu je z nej iba `ref_type` + `ref_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('from_address');
            $table->string('from_name')->nullable();

            // Odchádzajúca pošta. Prázdny host = predvolený mailer Accountu.
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 10)->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();

            // Prichádzajúca pošta. Bez hosta sa schránka nečíta.
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption', 10)->nullable();
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->unsignedBigInteger('imap_last_uid')->nullable();
            $table->timestamp('imap_checked_at')->nullable();
            $table->text('imap_error')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // Vec v projekte. Pri nepriradenej prichádzajúcej správe je prázdna.
            $table->string('ref_type', 60)->nullable();
            $table->string('ref_id', 120)->nullable();
            $table->string('ref_label')->nullable();
            $table->string('ref_url', 500)->nullable();

            $table->string('contact_email');
            $table->string('contact_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('waiting');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['product_id', 'ref_type', 'ref_id', 'contact_email'], 'conversations_ref_unique');
            $table->index(['product_id', 'contact_email']);
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3);           // out | in
            $table->string('source', 10);             // project | inbox | imap
            $table->string('kind', 60)->nullable();   // napr. canal.outreach, order.message
            $table->string('status', 12);             // queued | sent | failed | bounced | received | auto_reply
            $table->string('message_id')->nullable()->index();
            $table->text('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('reply_text')->nullable();
            $table->text('error')->nullable();
            $table->string('bounce_type', 10)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->unique(['mailbox_id', 'imap_uid']);
        });

        Schema::create('conversation_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 20);
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('mail_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 20);             // hard_bounce | unsubscribed | manual
            $table->unsignedSmallInteger('bounce_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_suppressions');
        Schema::dropIfExists('conversation_attachments');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('mailboxes');
    }
};
