<?php

namespace Tests\Feature\Messaging;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\InboundBlock;
use App\Models\Mailbox;
use App\Models\Product;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\InboundProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spam v inboxe: označenie, blokovanie odosielateľa, návrat, mazanie.
 */
class InboxSpamTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Mailbox $mailbox;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake();

        $this->product = Product::factory()->create(['key' => 'zastavy']);
        $this->mailbox = Mailbox::create([
            'product_id' => $this->product->id,
            'name' => 'Obchod',
            'from_address' => 'obchod@zastavy.test',
            'is_default' => true,
        ]);
        WebhookEndpoint::create([
            'product_id' => $this->product->id,
            'url' => 'https://zastavy.test/webhooks/account',
        ]);
        $this->user = User::factory()->create();
    }

    public function test_oznacenie_spamu_zablokuje_odosielatela_a_skryje_konverzaciu(): void
    {
        $this->process($this->mail('spam@lacne-lieky.test', 'Kúpte lieky'));
        $conversation = Conversation::sole();

        $this->actingAs($this->user)
            ->post("/inbox/{$conversation->uuid}/spam")
            ->assertRedirect('/inbox');

        $conversation->refresh();
        $this->assertSame(ConversationStatus::Spam, $conversation->status);
        $this->assertSame(0, $conversation->unread_count);
        $this->assertTrue(InboundBlock::blocks($this->product->id, 'spam@lacne-lieky.test'));

        $this->actingAs($this->user)->get('/inbox')
            ->assertInertia(fn ($page) => $page
                ->has('conversations.data', 0)
                ->where('counts.unassigned', 0)
                ->where('counts.spam', 1)
                ->where('inbox_unread', 0));

        $this->actingAs($this->user)->get('/inbox?status=spam')
            ->assertInertia(fn ($page) => $page->has('conversations.data', 1));
    }

    public function test_dalsi_mail_od_blokovaneho_odosielatela_ide_rovno_do_spamu(): void
    {
        InboundBlock::create(['product_id' => $this->product->id, 'email' => 'spam@lacne-lieky.test']);

        $result = $this->process($this->mail('Lieky <SPAM@lacne-lieky.test>', 'Akcia'));

        $this->assertSame(InboundProcessor::RESULT_SPAM, $result);
        $conversation = Conversation::sole();
        $this->assertSame(ConversationStatus::Spam, $conversation->status);
        $this->assertSame(0, $conversation->unread_count);
        $this->assertSame(0, WebhookDelivery::count());

        // Aj ďalší mail do tej istej konverzácie ostane v Spame
        $this->assertSame(InboundProcessor::RESULT_SPAM, $this->process($this->mail('spam@lacne-lieky.test', 'Akcia 2')));
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(ConversationStatus::Spam, $conversation->fresh()->status);
    }

    public function test_blok_neovplyvni_odpoved_vo_vlakne_priradenej_konverzacie(): void
    {
        $sent = app(ConversationService::class)->sendFromProject($this->product, [
            'to' => 'jan@firma.test',
            'subject' => 'Objednávka 1',
            'html' => '<p>Grafika</p>',
            'ref' => ['type' => 'order', 'id' => '1'],
        ])->fresh();
        InboundBlock::create(['product_id' => $this->product->id, 'email' => 'jan@firma.test']);

        $result = $this->process($this->mail('jan@firma.test', 'Schvaľujem', inReplyTo: $sent->message_id));

        $this->assertSame(InboundProcessor::RESULT_STORED, $result);
        $this->assertSame(ConversationStatus::Open, $sent->conversation->fresh()->status);
    }

    public function test_hromadne_oznacenie_preskoci_priradene_konverzacie(): void
    {
        $this->process($this->mail('a@spam.test', 'A'));
        $this->process($this->mail('b@spam.test', 'B'));
        $assigned = app(ConversationService::class)->sendFromProject($this->product, [
            'to' => 'jan@firma.test', 'subject' => 'X', 'html' => '<p>X</p>', 'ref' => ['type' => 'order', 'id' => '1'],
        ])->conversation;

        $this->actingAs($this->user)
            ->post('/inbox/spam', ['ids' => [...Conversation::unassigned()->pluck('uuid'), $assigned->uuid]])
            ->assertRedirect();

        $this->assertSame(2, Conversation::where('status', ConversationStatus::Spam)->count());
        $this->assertNotSame(ConversationStatus::Spam, $assigned->fresh()->status);
    }

    public function test_nie_je_spam_zrusi_blok_a_posle_zadrzane_spravy_projektu(): void
    {
        InboundBlock::create(['product_id' => $this->product->id, 'email' => 'obec@lehota.test']);
        $this->process($this->mail('obec@lehota.test', 'Objednávame vlajky'));
        $conversation = Conversation::sole();

        $this->actingAs($this->user)->post("/inbox/{$conversation->uuid}/not-spam")->assertRedirect();

        $this->assertSame(ConversationStatus::Open, $conversation->fresh()->status);
        $this->assertFalse(InboundBlock::blocks($this->product->id, 'obec@lehota.test'));
        $this->assertSame(1, WebhookDelivery::where('event', 'message.unassigned')->count());
        $this->assertNull(ConversationMessage::sole()->meta['spam'] ?? null);
    }

    public function test_zmazanie_spamu_zmaze_aj_prilohy(): void
    {
        $this->process($this->mail('spam@lacne-lieky.test', 'Akcia'));
        $conversation = Conversation::sole();
        $attachment = app(ConversationService::class)->storeAttachment($conversation->messages()->sole(), 'virus.zip', null, 'DATA');
        Storage::disk('local')->assertExists($attachment->path);

        app(ConversationService::class)->markSpam($conversation);
        $this->actingAs($this->user)->delete("/inbox/{$conversation->uuid}")
            ->assertRedirect('/inbox?status=spam');

        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, ConversationAttachment::count());
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_nepriradena_sprava_sa_da_zmazat_bez_blokovania_odosielatela(): void
    {
        $this->process($this->mail('niekto@nahodny.test', 'Ahoj'));
        $conversation = Conversation::sole();

        $this->actingAs($this->user)->delete("/inbox/{$conversation->uuid}")->assertRedirect('/inbox');

        $this->assertSame(0, Conversation::count());
        $this->assertFalse(InboundBlock::blocks($this->product->id, 'niekto@nahodny.test'));
    }

    public function test_priradena_konverzacia_sa_zmazat_neda(): void
    {
        $this->process($this->mail('obec@lehota.test', 'Objednávka'));
        $conversation = Conversation::sole();
        $conversation->forceFill(['ref_type' => 'order', 'ref_id' => '1'])->save();

        $this->actingAs($this->user)->delete("/inbox/{$conversation->uuid}")->assertStatus(422);

        $this->assertSame(1, Conversation::count());
    }

    public function test_vyprazdnenie_spamu(): void
    {
        $this->process($this->mail('a@spam.test', 'A'));
        $this->process($this->mail('obec@lehota.test', 'Objednávka'));
        app(ConversationService::class)->markSpam(Conversation::where('contact_email', 'a@spam.test')->sole());

        $this->actingAs($this->user)->delete('/inbox/spam')->assertRedirect();

        $this->assertSame(['obec@lehota.test'], Conversation::pluck('contact_email')->all());
    }

    public function test_status_spam_sa_neda_nastavit_cez_vyber_stavu(): void
    {
        $this->process($this->mail('a@spam.test', 'A'));
        $conversation = Conversation::sole();

        $this->actingAs($this->user)
            ->post("/inbox/{$conversation->uuid}/status", ['status' => 'spam'])
            ->assertSessionHasErrors('status');
    }

    public function test_prikaz_zmaze_stary_spam(): void
    {
        $this->process($this->mail('stary@spam.test', 'Starý'));
        $this->process($this->mail('novy@spam.test', 'Nový'));
        Conversation::query()->each(fn (Conversation $c) => app(ConversationService::class)->markSpam($c));
        Conversation::where('contact_email', 'stary@spam.test')->update(['last_message_at' => now()->subDays(31)]);

        $this->artisan('messaging:purge-spam')->assertSuccessful();

        $this->assertSame(['novy@spam.test'], Conversation::pluck('contact_email')->all());
    }

    /* ------------------------------------------------------------- */

    private function process(string $raw): string
    {
        return app(InboundProcessor::class)->process($this->mailbox, $raw);
    }

    private function mail(string $from, string $body, ?string $inReplyTo = null): string
    {
        return "From: {$from}\r\nTo: obchod@zastavy.test\r\nSubject: {$body}\r\n"
            .'Message-ID: <'.uniqid('spam-', true)."@test>\r\n"
            .($inReplyTo ? "In-Reply-To: <{$inReplyTo}>\r\n" : '')
            ."MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n{$body}\r\n";
    }
}
