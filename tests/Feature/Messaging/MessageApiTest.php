<?php

namespace Tests\Feature\Messaging;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailSuppression;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ServiceClient;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Odosielanie správ z projektu cez API.
 *
 * Fronta je v testoch synchrónna a mailer `array`, takže mail naozaj
 * „odíde“ a dajú sa skontrolovať jeho hlavičky.
 */
class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake();

        $this->product = Product::factory()->create(['key' => 'event']);
        [, $this->token] = ServiceClient::issue($this->product, 'test');

        Mailbox::create([
            'product_id' => $this->product->id,
            'name' => 'Kontakt',
            'from_address' => 'kontakt@event.test',
            'from_name' => 'Event',
            'is_default' => true,
        ]);
    }

    public function test_sprava_zalozi_konverzaciu_a_odide_s_hlavickami_vlakna(): void
    {
        $response = $this->send(['subject' => 'Overenie kontaktu']);

        $response->assertStatus(202)
            ->assertJsonPath('data.conversation.ref.type', 'canal')
            ->assertJsonPath('data.conversation.ref.id', '123')
            ->assertJsonPath('data.conversation.ref.label', 'Kanál: KC Senica')
            ->assertJsonPath('data.conversation.status', 'waiting')
            ->assertJsonPath('data.message.status', 'sent');

        $message = ConversationMessage::sole();
        $sent = $this->sentEmails();

        $this->assertCount(1, $sent);
        $this->assertSame('info@kcsenica.test', $sent[0]->getTo()[0]->getAddress());
        $this->assertSame('kontakt@event.test', $sent[0]->getFrom()[0]->getAddress());
        $this->assertSame('kontakt@event.test', $sent[0]->getReplyTo()[0]->getAddress());
        $this->assertSame('<'.$message->message_id.'>', $sent[0]->getHeaders()->get('Message-ID')->getBodyAsString());
        $this->assertSame($message->conversation->uuid, $sent[0]->getHeaders()->get('X-Conversation')->getBodyAsString());
    }

    public function test_druha_sprava_k_tej_istej_veci_pokracuje_vo_vlakne(): void
    {
        $this->send(['subject' => 'Prvá'])->assertStatus(202);
        $this->send(['subject' => 'Druhá'])->assertStatus(202);

        $this->assertSame(1, Conversation::count());

        [$first, $second] = ConversationMessage::orderBy('id')->get();
        $this->assertSame($first->message_id, $second->in_reply_to);

        $headers = $this->sentEmails()[1]->getHeaders();
        $this->assertStringContainsString($first->message_id, $headers->get('In-Reply-To')->getBodyAsString());
        $this->assertStringContainsString($first->message_id, $headers->get('References')->getBodyAsString());
    }

    public function test_ina_adresa_k_tej_istej_veci_je_ina_konverzacia(): void
    {
        $this->send(['to' => 'a@kcsenica.test'])->assertStatus(202);
        $this->send(['to' => 'b@kcsenica.test'])->assertStatus(202);

        $this->assertSame(2, Conversation::count());
    }

    public function test_blokovana_adresa_vrati_422_s_dovodom(): void
    {
        MailSuppression::create([
            'product_id' => $this->product->id,
            'email' => 'info@kcsenica.test',
            'reason' => MailSuppression::HARD_BOUNCE,
        ]);

        $this->send()->assertStatus(422)->assertJsonPath('reason', 'suppressed');

        $this->assertSame(0, ConversationMessage::count());
    }

    public function test_projekt_bez_schranky_dostane_no_mailbox(): void
    {
        Mailbox::query()->delete();

        $this->send()->assertStatus(422)->assertJsonPath('reason', 'no_mailbox');
    }

    public function test_ref_label_je_povinny(): void
    {
        $this->send(['ref' => ['type' => 'canal', 'id' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ref.label');
    }

    public function test_token_bez_opravnenia_neprejde(): void
    {
        [, $token] = ServiceClient::issue($this->product, 'bez sprav', ['organizations:read']);

        $this->withToken($token)->postJson('/api/v1/messages', $this->payload())->assertForbidden();
    }

    public function test_firma_musi_byt_naviazana_na_projekt(): void
    {
        $organization = Organization::factory()->create();

        $this->send(['organization_id' => $organization->uuid])->assertStatus(422);

        $organization->linkTo($this->product);

        $this->send(['organization_id' => $organization->uuid])
            ->assertStatus(202)
            ->assertJsonPath('data.conversation.organization_id', $organization->uuid);
    }

    public function test_priloha_sa_prilozi_k_mailu(): void
    {
        $this->send([
            'attachments' => [['filename' => 'zmluva.pdf', 'mime' => 'application/pdf', 'content' => base64_encode('%PDF-test')]],
        ])->assertStatus(202)->assertJsonPath('data.message.attachments.0.filename', 'zmluva.pdf');

        $this->assertCount(1, $this->sentEmails()[0]->getAttachments());
    }

    public function test_povolene_hlavicky_sa_pridaju_ostatne_nie(): void
    {
        $this->send(['headers' => [
            'List-Unsubscribe' => '<https://event.test/odhlasit>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'Bcc' => 'spy@example.test',
        ]])->assertStatus(202);

        $headers = $this->sentEmails()[0]->getHeaders();
        $this->assertSame('<https://event.test/odhlasit>', $headers->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')->getBodyAsString());
        $this->assertFalse($headers->has('Bcc'));
    }

    public function test_odoslanie_vyvola_webhook_message_sent(): void
    {
        $endpoint = WebhookEndpoint::create(['product_id' => $this->product->id, 'url' => 'https://event.test/hook', 'events' => ['message.sent']]);

        $this->send()->assertStatus(202);

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->sole();
        $this->assertSame('message.sent', $delivery->event);
        $this->assertSame('project', $delivery->payload['data']['message']['source']);
    }

    public function test_projekt_vidi_len_svoje_konverzacie(): void
    {
        $this->send()->assertStatus(202);
        $uuid = Conversation::sole()->uuid;

        $this->withToken($this->token)->getJson('/api/v1/conversations?ref_type=canal&ref_id=123')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)->getJson("/api/v1/conversations/{$uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages');

        $other = Product::factory()->create(['key' => 'zastavy']);
        [, $otherToken] = ServiceClient::issue($other, 'iny');

        $this->withToken($otherToken)->getJson("/api/v1/conversations/{$uuid}")->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/v1/conversations?ref_type=canal')->assertJsonCount(0, 'data');
    }

    public function test_projekt_vie_adresu_zablokovat_a_odblokovat(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/suppressions', ['email' => 'Info@KCSenica.test'])
            ->assertCreated()
            ->assertJsonPath('data.suppressed', true)
            ->assertJsonPath('data.reason', 'unsubscribed');

        $this->send()->assertStatus(422);

        $this->withToken($this->token)->deleteJson('/api/v1/suppressions/info@kcsenica.test')->assertNoContent();
        $this->withToken($this->token)->getJson('/api/v1/suppressions/info@kcsenica.test')
            ->assertJsonPath('data.suppressed', false);
    }

    public function test_mimo_produkcie_ide_mail_cez_predvoleny_mailer_nie_smtp_schranky(): void
    {
        config(['accounts.messaging.use_mailbox_smtp' => false]);
        Mailbox::query()->update(['smtp_host' => '127.0.0.1', 'smtp_port' => 1]);

        $this->send()->assertStatus(202)->assertJsonPath('data.message.status', 'sent');

        $this->assertCount(1, $this->sentEmails());
    }

    public function test_chyba_smtp_necha_spravu_vo_fronte_na_dalsi_pokus(): void
    {
        config(['accounts.messaging.use_mailbox_smtp' => true]);
        Mailbox::query()->update(['smtp_host' => '127.0.0.1', 'smtp_port' => 1]);

        $this->send()->assertStatus(202)->assertJsonPath('data.message.status', 'queued');

        $message = ConversationMessage::sole();
        $this->assertSame(MessageStatus::Queued, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->error);
        $this->assertTrue($message->next_attempt_at->isFuture());
        $this->assertSame(ConversationStatus::Waiting, $message->conversation->status);
    }

    /* ------------------------------------------------------------- */

    /** @param array<string, mixed> $overrides */
    private function send(array $overrides = []): TestResponse
    {
        return $this->withToken($this->token)->postJson('/api/v1/messages', $this->payload($overrides));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'to' => 'info@kcsenica.test',
            'to_name' => 'KC Senica',
            'subject' => 'Overenie kontaktu',
            'html' => '<p>Dobrý deň, potvrďte prosím kontakt.</p>',
            'ref' => ['type' => 'canal', 'id' => 123, 'label' => 'Kanál: KC Senica', 'url' => 'https://event.test/admin/canals/123'],
            'kind' => 'canal.contact_verify',
        ], $overrides);
    }

    /** @return array<int, Email> */
    private function sentEmails(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->values()
            ->all();
    }
}
