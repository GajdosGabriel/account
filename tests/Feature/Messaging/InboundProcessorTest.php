<?php

namespace Tests\Feature\Messaging;

use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailSuppression;
use App\Models\Product;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\InboundProcessor;
use App\Services\Messaging\MailboxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Prichádzajúca pošta: párovanie odpovedí, bounce, automatické odpovede.
 */
class InboundProcessorTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Mailbox $mailbox;

    private WebhookEndpoint $endpoint;

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
            'imap_host' => 'imap.zastavy.test',
            'imap_username' => 'obchod@zastavy.test',
            'imap_password' => 'tajne',
        ]);
        $this->endpoint = WebhookEndpoint::create([
            'product_id' => $this->product->id,
            'url' => 'https://zastavy.test/webhooks/account',
        ]);
    }

    public function test_odpoved_s_in_reply_to_sa_sparuje_a_projekt_dostane_webhook(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');

        $result = $this->process($this->reply(
            from: 'Ján Novák <jan@firma.test>',
            inReplyTo: $sent->message_id,
            body: "Grafiku schvaľujem, ďakujem.\n\nDňa 15. 10. 2026 o 10:00 obchod@zastavy.test napísal:\n> Posielame grafiku na schválenie.",
        ));

        $this->assertSame(InboundProcessor::RESULT_STORED, $result);

        $conversation = $sent->conversation->fresh();
        $this->assertSame(ConversationStatus::Open, $conversation->status);
        $this->assertSame(1, $conversation->unread_count);

        $inbound = $conversation->messages()->where('direction', 'in')->sole();
        $this->assertSame('Grafiku schvaľujem, ďakujem.', $inbound->reply_text);
        $this->assertSame('Ján Novák', $inbound->from_name);

        $delivery = WebhookDelivery::where('event', 'message.received')->sole();
        $this->assertSame('order', $delivery->payload['data']['conversation']['ref']['type']);
        $this->assertSame('1234', $delivery->payload['data']['conversation']['ref']['id']);
        $this->assertSame('Grafiku schvaľujem, ďakujem.', $delivery->payload['data']['message']['reply_text']);
    }

    public function test_odpoved_na_stary_mail_projektu_sa_sparuje_podla_thread_id(): void
    {
        $sent = app(ConversationService::class)->sendFromProject($this->product, [
            'to' => 'jan@firma.test',
            'subject' => 'Objednávka 1234',
            'html' => '<p>Zmena objednávky</p>',
            'ref' => ['type' => 'order', 'id' => '1234', 'label' => 'Objednávka č. 1234'],
            'thread_id' => '<order-abc@zastavy.test>',
        ])->fresh();
        $this->sendOrderMessage('9999', 'jan@firma.test');

        // Nový mail drží zákazníkovi staré vlákno
        $this->assertSame('order-abc@zastavy.test', $sent->in_reply_to);

        // Odpoveď na potvrdenie, ktoré ešte poslali zastavy samy
        $result = $this->process($this->reply(from: 'jan@firma.test', inReplyTo: 'order-abc@zastavy.test', body: 'Stará odpoveď'));

        $this->assertSame(InboundProcessor::RESULT_STORED, $result);
        $this->assertSame(2, $sent->conversation->messages()->count());
    }

    public function test_odpoved_bez_hlaviciek_sa_sparuje_podla_jedinej_konverzacie_odosielatela(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');

        $result = $this->process($this->reply(from: 'jan@firma.test', body: 'Ozvem sa zajtra.'));

        $this->assertSame(InboundProcessor::RESULT_STORED, $result);
        $this->assertSame(2, $sent->conversation->messages()->count());
    }

    public function test_pri_viacerych_konverzaciach_bez_hlaviciek_je_sprava_nepriradena(): void
    {
        $this->sendOrderMessage('1', 'jan@firma.test');
        $this->sendOrderMessage('2', 'jan@firma.test');

        $result = $this->process($this->reply(from: 'jan@firma.test', body: 'Ktorá objednávka?'));

        $this->assertSame(InboundProcessor::RESULT_UNASSIGNED, $result);

        $unassigned = Conversation::unassigned()->sole();
        $this->assertSame('jan@firma.test', $unassigned->contact_email);
        $this->assertSame(0, WebhookDelivery::where('event', 'message.received')->count());
    }

    public function test_novy_mail_mimo_konverzacie_posle_projektu_message_unassigned(): void
    {
        $result = $this->process($this->reply(from: 'Obec Lehota <obec@lehota.test>', body: "Objednávame 2 ks vlajky SR.\nObec Lehota"));

        $this->assertSame(InboundProcessor::RESULT_UNASSIGNED, $result);

        $delivery = WebhookDelivery::where('event', 'message.unassigned')->sole();
        $this->assertNull($delivery->payload['data']['conversation']['ref']['type']);
        $this->assertSame('obec@lehota.test', $delivery->payload['data']['message']['from']);
        $this->assertStringContainsString('2 ks vlajky SR', $delivery->payload['data']['message']['text']);
        $this->assertSame(0, WebhookDelivery::where('event', 'message.received')->count());
    }

    public function test_odpoved_projektu_do_nepriradenej_konverzacie_ju_priradi(): void
    {
        $this->process($this->reply(from: 'obec@lehota.test', body: 'Objednávame 2 ks vlajky SR.'));
        $general = Conversation::unassigned()->sole();

        $sent = app(ConversationService::class)->sendFromProject($this->product, [
            'to' => 'obec@lehota.test',
            'subject' => 'Re: Objednávka',
            'html' => '<p>Potvrdenie</p>',
            'ref' => ['type' => 'order', 'id' => 'uuid-1', 'label' => 'Objednávka č. 1'],
            'conversation' => $general->uuid,
        ])->fresh();

        $this->assertSame($general->id, $sent->conversation_id);
        $this->assertSame('order', $general->fresh()->ref_type);
        $this->assertSame('uuid-1', $general->fresh()->ref_id);
        // Odpoveď ide do vlákna pôvodného mailu
        $this->assertNotNull($sent->in_reply_to);

        $this->process($this->reply(from: 'obec@lehota.test', body: 'Ďakujeme.', inReplyTo: $sent->message_id));
        $this->assertSame(1, WebhookDelivery::where('event', 'message.received')->count());
    }

    public function test_priradenie_nepriradenej_spravy_posle_webhook_dodatocne(): void
    {
        $this->sendOrderMessage('1', 'jan@firma.test');
        $target = $this->sendOrderMessage('2', 'jan@firma.test')->conversation;

        $this->process($this->reply(from: 'jan@firma.test', body: 'Ide o druhú.'));
        $source = Conversation::unassigned()->sole();

        app(ConversationService::class)->mergeInto($source, $target);

        $this->assertNull(Conversation::find($source->id));
        $this->assertSame(1, $target->fresh()->unread_count);
        $this->assertSame(1, WebhookDelivery::where('event', 'message.received')->count());
    }

    public function test_tvrdy_bounce_oznaci_spravu_a_zablokuje_adresu(): void
    {
        $sent = $this->sendOrderMessage('1234', 'neexistuje@firma.test');

        $result = $this->process($this->bounce($sent->message_id, 'neexistuje@firma.test', '5.1.1'));

        $this->assertSame(InboundProcessor::RESULT_BOUNCE, $result);

        $sent->refresh();
        $this->assertSame(MessageStatus::Bounced, $sent->status);
        $this->assertSame('hard', $sent->bounce_type);
        $this->assertTrue(MailSuppression::blocks($this->product, 'neexistuje@firma.test'));
        $this->assertSame(1, WebhookDelivery::where('event', 'message.bounced')->count());
    }

    public function test_docasny_bounce_adresu_neblokuje(): void
    {
        $sent = $this->sendOrderMessage('1234', 'plna@firma.test');

        $this->process($this->bounce($sent->message_id, 'plna@firma.test', '4.2.2'));

        $this->assertSame('soft', $sent->fresh()->bounce_type);
        $this->assertFalse(MailSuppression::blocks($this->product, 'plna@firma.test'));
    }

    public function test_automaticka_odpoved_nebudi_operatora(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');

        $result = $this->process($this->reply(
            from: 'jan@firma.test',
            inReplyTo: $sent->message_id,
            body: 'Som na dovolenke do 20. 10.',
            extraHeaders: "Auto-Submitted: auto-replied\r\n",
        ));

        $this->assertSame(InboundProcessor::RESULT_AUTO_REPLY, $result);
        $conversation = $sent->conversation->fresh();
        $this->assertSame(ConversationStatus::Waiting, $conversation->status);
        $this->assertSame(0, $conversation->unread_count);
        $this->assertSame(0, WebhookDelivery::where('event', 'message.received')->count());
    }

    public function test_ta_ista_sprava_sa_neulozi_dvakrat(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');
        $raw = $this->reply(from: 'jan@firma.test', inReplyTo: $sent->message_id, body: 'Ok');

        $this->process($raw, 10);

        $this->assertSame(InboundProcessor::RESULT_SKIPPED, $this->process($raw, 10));
        $this->assertSame(InboundProcessor::RESULT_SKIPPED, $this->process($raw, 11));
    }

    public function test_odpoved_z_ineho_projektu_sa_s_cudzou_konverzaciou_nesparuje(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');

        $other = Product::factory()->create(['key' => 'event']);
        $otherMailbox = Mailbox::create(['product_id' => $other->id, 'name' => 'x', 'from_address' => 'kontakt@event.test']);

        $result = app(InboundProcessor::class)->process(
            $otherMailbox,
            $this->reply(from: 'jan@firma.test', inReplyTo: $sent->message_id, body: 'Omyl'),
        );

        $this->assertSame(InboundProcessor::RESULT_UNASSIGNED, $result);
        $this->assertSame(1, $sent->conversation->messages()->count());
    }

    public function test_priloha_sa_ulozi(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');

        $boundary = 'b1';
        $raw = "From: jan@firma.test\r\nTo: obchod@zastavy.test\r\nSubject: Re: logo\r\n"
            ."Message-ID: <priloha@firma.test>\r\nIn-Reply-To: <{$sent->message_id}>\r\n"
            ."MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n"
            ."--{$boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nPosielam logo.\r\n"
            ."--{$boundary}\r\nContent-Type: image/png; name=\"logo.png\"\r\nContent-Disposition: attachment; filename=\"logo.png\"\r\n"
            ."Content-Transfer-Encoding: base64\r\n\r\n".base64_encode('PNGDATA')."\r\n--{$boundary}--\r\n";

        $this->process($raw);

        $attachment = ConversationMessage::where('direction', 'in')->sole()->attachments()->sole();
        $this->assertSame('logo.png', $attachment->filename);
        $this->assertSame('PNGDATA', $attachment->contents());
    }

    public function test_prikaz_fetch_pri_prvom_spusteni_preskoci_staru_postu(): void
    {
        $this->app->instance(MailboxReader::class, new class implements MailboxReader
        {
            public function fetch(Mailbox $mailbox, int $afterUid, int $limit): iterable
            {
                return $afterUid >= 40 ? [] : [['uid' => 1, 'raw' => 'x']];
            }

            public function latestUid(Mailbox $mailbox): int
            {
                return 40;
            }

            public function test(Mailbox $mailbox): void {}
        });

        $this->artisan('messaging:fetch')->assertSuccessful();

        $this->assertSame(40, (int) $this->mailbox->fresh()->imap_last_uid);
        $this->assertSame(0, ConversationMessage::count());
    }

    public function test_prikaz_fetch_spracuje_novu_postu_a_posunie_uid(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');
        $this->mailbox->forceFill(['imap_last_uid' => 40])->save();
        $raw = $this->reply(from: 'jan@firma.test', inReplyTo: $sent->message_id, body: 'Ďakujem');

        $this->app->instance(MailboxReader::class, new class($raw) implements MailboxReader
        {
            public function __construct(private string $raw) {}

            public function fetch(Mailbox $mailbox, int $afterUid, int $limit): iterable
            {
                return [['uid' => 41, 'raw' => $this->raw], ['uid' => 42, 'raw' => "nezmysel\r\n\r\n"]];
            }

            public function latestUid(Mailbox $mailbox): int
            {
                return 0;
            }

            public function test(Mailbox $mailbox): void {}
        });

        $this->artisan('messaging:fetch')->assertSuccessful();

        $this->assertSame(42, (int) $this->mailbox->fresh()->imap_last_uid);
        $this->assertSame(1, ConversationMessage::where('direction', 'in')->where('imap_uid', 41)->count());
    }

    public function test_odpoved_operatora_z_inboxu_ide_do_vlakna(): void
    {
        $sent = $this->sendOrderMessage('1234', 'jan@firma.test');
        $this->process($this->reply(from: 'jan@firma.test', inReplyTo: $sent->message_id, body: 'Kedy to bude?'));

        $operator = User::factory()->create();

        $this->actingAs($operator)
            ->post("/inbox/{$sent->conversation->uuid}/reply", ['text' => 'Zajtra.'])
            ->assertRedirect();

        $reply = ConversationMessage::where('source', 'inbox')->sole();
        $this->assertSame(MessageStatus::Sent, $reply->status);
        $this->assertSame('Re: Objednávka 1234', $reply->subject);
        $this->assertSame($operator->id, $reply->sent_by_user_id);
        $this->assertSame(ConversationStatus::Answered, $reply->conversation->status);
        $this->assertSame(0, $reply->conversation->unread_count);

        $delivery = WebhookDelivery::where('event', 'message.sent')->latest('id')->first();
        $this->assertSame('inbox', $delivery->payload['data']['message']['source']);
        $this->assertSame($operator->name, $delivery->payload['data']['message']['sent_by']);
    }

    public function test_na_vseobecnu_spravu_sa_da_odpovedat_a_neskor_priradit(): void
    {
        $this->assertSame(
            InboundProcessor::RESULT_UNASSIGNED,
            $this->process($this->reply(from: 'novy@klient.test', body: 'Objednávam zástavu SR.')),
        );

        $general = Conversation::unassigned()->sole();

        $this->actingAs(User::factory()->create())
            ->post("/inbox/{$general->uuid}/reply", ['text' => 'Ďakujeme, aký rozmer?'])
            ->assertRedirect();

        $reply = ConversationMessage::where('source', 'inbox')->sole();
        $this->assertSame($general->id, $reply->conversation_id);
        $this->assertSame(ConversationStatus::Answered, $general->fresh()->status);

        // Klient odpovie na našu odpoveď – ostane vo všeobecnej konverzácii.
        $this->assertSame(
            InboundProcessor::RESULT_UNASSIGNED,
            $this->process($this->reply(from: 'novy@klient.test', inReplyTo: $reply->message_id, body: '150 × 100 cm.')),
        );
        $this->assertSame(2, $general->messages()->where('direction', 'in')->count());
        // Projekt dostal len oznámenia o nepriradených mailoch, nie odpovede k veci.
        $this->assertSame(0, WebhookDelivery::where('event', '!=', 'message.unassigned')->count());
        $this->assertSame(2, WebhookDelivery::where('event', 'message.unassigned')->count());

        // Projekt neskôr založí objednávku – priradenie presunie celé vlákno.
        $order = $this->sendOrderMessage('5678', 'novy@klient.test')->conversation;

        $this->actingAs(User::factory()->create())
            ->post("/inbox/{$general->uuid}/merge", ['target' => $order->uuid])
            ->assertRedirect("/inbox/{$order->uuid}");

        $this->assertNull(Conversation::find($general->id));
        $this->assertSame(1, $order->messages()->where('source', 'inbox')->count());
        $this->assertSame(2, WebhookDelivery::where('event', 'message.received')->count());
    }

    /* ------------------------------------------------------------- */

    private function sendOrderMessage(string $order, string $email): ConversationMessage
    {
        return app(ConversationService::class)->sendFromProject($this->product, [
            'to' => $email,
            'subject' => "Objednávka {$order}",
            'html' => '<p>Posielame grafiku na schválenie.</p>',
            'ref' => ['type' => 'order', 'id' => $order, 'label' => "Objednávka č. {$order}"],
        ])->fresh();
    }

    private function process(string $raw, ?int $uid = null): string
    {
        return app(InboundProcessor::class)->process($this->mailbox, $raw, $uid);
    }

    private function reply(string $from, string $body, ?string $inReplyTo = null, string $extraHeaders = ''): string
    {
        static $n = 0;
        $n++;

        return "From: {$from}\r\nTo: obchod@zastavy.test\r\nSubject: Re: Objednávka\r\n"
            ."Message-ID: <odpoved-{$n}-".uniqid()."@firma.test>\r\n"
            .($inReplyTo ? "In-Reply-To: <{$inReplyTo}>\r\nReferences: <{$inReplyTo}>\r\n" : '')
            .$extraHeaders
            ."MIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            .str_replace("\n", "\r\n", $body)."\r\n";
    }

    private function bounce(string $messageId, string $recipient, string $status): string
    {
        return "From: Mail Delivery Subsystem <mailer-daemon@googlemail.com>\r\nTo: obchod@zastavy.test\r\n"
            ."Subject: Delivery Status Notification (Failure)\r\nMessage-ID: <bounce-".uniqid()."@google.com>\r\n"
            ."MIME-Version: 1.0\r\nContent-Type: multipart/report; report-type=delivery-status; boundary=\"r\"\r\n\r\n"
            ."--r\r\nContent-Type: text/plain\r\n\r\nAddress not found.\r\n"
            ."--r\r\nContent-Type: message/delivery-status\r\n\r\nReporting-MTA: dns; googlemail.com\r\n\r\n"
            ."Final-Recipient: rfc822; {$recipient}\r\nAction: failed\r\nStatus: {$status}\r\n"
            ."Diagnostic-Code: smtp; 550 {$status} The email account does not exist.\r\n"
            ."--r\r\nContent-Type: text/rfc822-headers\r\n\r\nFrom: obchod@zastavy.test\r\nTo: {$recipient}\r\n"
            ."Subject: Objednávka\r\nMessage-ID: <{$messageId}>\r\n--r--\r\n";
    }
}
