<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hromadný emailing (ponuky a automatizácie) pre všetky projekty.
 *
 * Account drží kontakty, kampane, šablóny, odosielanie a meranie. Projekt
 * len hlási udalosti („objednávka vytvorená pre X“) s premennými do textu –
 * čo je objednávka, vie iba on.
 *
 * Marketingové odhlásenie je pri kontakte, nie v `mail_suppressions`:
 * odhlásenie z ponúk nesmie zastaviť potvrdenia objednávok. Trvalý bounce
 * sa naopak zapisuje do `mail_suppressions` – nedoručiteľná adresa je
 * nedoručiteľná pre všetky maily projektu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // HTML so značkami {{heading}}, {{{content}}}, {{unsubscribe_url}}…
            $table->longText('html');
            $table->timestamps();
        });

        Schema::create('mailing_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('layout_id')->nullable()->constrained('mailing_layouts')->nullOnDelete();
            $table->string('name');
            $table->string('subject', 200)->nullable();
            $table->string('heading', 200)->nullable();
            $table->text('body');
            $table->string('button_label', 80)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->timestamps();
        });

        // Udalosti, ktoré projekt ponúka ako spúšťač automatizácie.
        Schema::create('mailing_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label');
            $table->string('short_label', 80)->nullable();
            // Premenné, ktoré projekt pri udalosti posiela (nápoveda v editore).
            $table->json('variables')->nullable();
            // `kind` systémového mailu tejto udalosti – odpoveď vo vlákne ho cituje.
            $table->string('quote_kind', 60)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });

        Schema::create('mailing_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            // Odkiaľ je súhlas so zasielaním (GDPR) a odkedy.
            $table->string('permission_note')->nullable();
            $table->timestamp('permission_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('unsubscribed_at')->nullable();
            // Vylúčenie z ponúk po opakovanom dočasnom zlyhaní; trvalý bounce je v mail_suppressions.
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->unsignedSmallInteger('soft_bounces')->default(0);
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'email']);
        });

        Schema::create('mailing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('layout_id')->nullable()->constrained('mailing_layouts')->nullOnDelete();
            $table->string('name');
            $table->string('subject', 200)->nullable();
            $table->string('heading', 200)->nullable();
            $table->text('body');
            $table->string('button_label', 80)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->string('coupon_code', 60)->nullable();
            $table->date('coupon_valid_to')->nullable();
            $table->string('status', 12)->default('draft');   // draft | queued | completed | cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->string('trigger_event', 60)->nullable();
            $table->unsignedInteger('delay_hours')->default(24);
            $table->boolean('track_clicks')->default(false);
            $table->boolean('track_opens')->default(false);
            // Servisná odpoveď vo vlákne veci (objednávky) – ide cez konverzáciu, je vidieť v inboxe.
            $table->boolean('reply_in_thread')->default(false);
            $table->boolean('quote_event')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'trigger_event', 'status']);
        });

        Schema::create('mailing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('mailing_contacts')->nullOnDelete();
            $table->string('email');
            $table->string('status', 10)->default('pending');  // pending | sending | sent | failed | skipped | bounced
            $table->string('ref_type', 60)->nullable();
            $table->string('ref_id', 120)->nullable();
            // Jedna udalosť jednej veci = jedno doručenie (napr. order:12:shipping:3); '' pri hromadnej kampani.
            $table->string('dedupe_key', 191)->default('');
            $table->json('payload')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->foreignId('conversation_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error')->nullable();
            $table->string('open_token', 64)->nullable()->unique();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'email', 'dedupe_key']);
            $table->index(['status', 'due_at']);
            $table->index(['ref_type', 'ref_id']);
            $table->index('attempted_at');
        });

        Schema::create('mailing_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('mailing_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('token', 64)->unique();
            $table->string('url', 2048);
            $table->string('label')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('clicked_at')->nullable();

            $table->unique(['delivery_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_links');
        Schema::dropIfExists('mailing_deliveries');
        Schema::dropIfExists('mailing_campaigns');
        Schema::dropIfExists('mailing_contacts');
        Schema::dropIfExists('mailing_triggers');
        Schema::dropIfExists('mailing_templates');
        Schema::dropIfExists('mailing_layouts');
    }
};
