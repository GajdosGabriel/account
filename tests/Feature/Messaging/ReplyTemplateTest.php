<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\Mailbox;
use App\Models\Product;
use App\Models\ReplyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Šablóny odpovedí: správa, viditeľnosť (projekt, súkromné), skratky
 * a štatistika použitia pri odpovedi.
 */
class ReplyTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Product $other;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->product = Product::factory()->create(['key' => 'zastavy', 'name' => 'Zástavy']);
        $this->other = Product::factory()->create(['key' => 'tixio', 'name' => 'Tixio']);
        $this->user = User::factory()->create(['name' => 'Gabo Gajdoš']);
    }

    public function test_vytvori_sablonu_so_skratkou_bez_lomky_a_malymi_pismenami(): void
    {
        $this->actingAs($this->user)->post('/inbox/templates', [
            'name' => 'Cenová ponuka',
            'shortcut' => '/Cena',
            'product_key' => 'zastavy',
            'subject' => 'Ponuka',
            'body' => "Dobrý deň {first_name|pani/pán},\n\nposielame ponuku.\n\n{agent}",
            'private' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $template = ReplyTemplate::sole();
        $this->assertSame('cena', $template->shortcut);
        $this->assertSame($this->product->id, $template->product_id);
        $this->assertNull($template->user_id);
        $this->assertSame($this->user->id, $template->created_by_user_id);
    }

    public function test_skratka_nesmie_kolidovat_v_tom_istom_projekte(): void
    {
        ReplyTemplate::create(['name' => 'Spoločná', 'shortcut' => 'dakujem', 'body' => 'Ďakujeme']);
        ReplyTemplate::create(['name' => 'Tixio', 'shortcut' => 'cena', 'product_id' => $this->other->id, 'body' => 'x']);

        // Spoločná skratka platí aj v projekte.
        $this->actingAs($this->user)->post('/inbox/templates', [
            'name' => 'Iná', 'shortcut' => 'dakujem', 'product_key' => 'zastavy', 'body' => 'y',
        ])->assertSessionHasErrors('shortcut');

        // Rovnaká skratka v inom projekte nevadí.
        $this->actingAs($this->user)->post('/inbox/templates', [
            'name' => 'Zástavy', 'shortcut' => 'cena', 'product_key' => 'zastavy', 'body' => 'z',
        ])->assertSessionHasNoErrors();

        // Spoločná by kolidovala s projektovými.
        $this->actingAs($this->user)->post('/inbox/templates', [
            'name' => 'Spoločná cena', 'shortcut' => 'cena', 'body' => 'w',
        ])->assertSessionHasErrors('shortcut');

        $this->actingAs($this->user)->post('/inbox/templates', [
            'name' => 'Medzera', 'shortcut' => 'dve slova', 'body' => 'w',
        ])->assertSessionHasErrors('shortcut');
    }

    public function test_sukromnu_sablonu_vidi_a_upravuje_len_autor(): void
    {
        $colleague = User::factory()->create();
        $private = ReplyTemplate::create(['name' => 'Moja', 'body' => 'x', 'user_id' => $colleague->id]);
        ReplyTemplate::create(['name' => 'Tímová', 'body' => 'y']);

        $this->actingAs($this->user)->get('/inbox/templates')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Inbox/Templates')
                ->has('templates', 1)
                ->where('templates.0.name', 'Tímová'));

        $this->actingAs($this->user)->patch("/inbox/templates/{$private->id}", ['name' => 'Ukradnutá', 'body' => 'z'])
            ->assertForbidden();
        $this->actingAs($this->user)->delete("/inbox/templates/{$private->id}")->assertForbidden();
        $this->assertSame('Moja', $private->fresh()->name);
    }

    public function test_detail_konverzacie_ponuka_sablony_projektu_a_spolocne_s_hodnotami_premennych(): void
    {
        $conversation = $this->conversation();
        ReplyTemplate::create(['name' => 'Spoločná', 'body' => 'a']);
        ReplyTemplate::forceCreate(['name' => 'Zástavy', 'body' => 'b', 'product_id' => $this->product->id, 'uses_count' => 3]);
        ReplyTemplate::create(['name' => 'Tixio', 'body' => 'c', 'product_id' => $this->other->id]);

        $this->actingAs($this->user)->get("/inbox/{$conversation->uuid}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Inbox/Show')
                ->has('templates', 2)
                ->where('templates.0.name', 'Zástavy')
                ->where('templates.1.name', 'Spoločná')
                ->where('templateContext.first_name', 'Jana')
                ->where('templateContext.name', 'Jana Nováková')
                ->where('templateContext.agent_first_name', 'Gabo')
                ->where('templateContext.product', 'Zástavy'));
    }

    public function test_odpoved_zo_sablony_zvysi_pocet_pouziti(): void
    {
        $conversation = $this->conversation();
        $template = ReplyTemplate::create(['name' => 'Ďakujeme', 'body' => 'Ďakujeme']);
        $foreign = ReplyTemplate::create(['name' => 'Cudzia', 'body' => 'x', 'user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)->post("/inbox/{$conversation->uuid}/reply", [
            'text' => 'Ďakujeme, Jana.',
            'template_ids' => [$template->id, $template->id, $foreign->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $template->fresh()->uses_count);
        $this->assertNotNull($template->fresh()->last_used_at);
        $this->assertSame(0, $foreign->fresh()->uses_count);
    }

    private function conversation(): Conversation
    {
        $mailbox = Mailbox::create([
            'product_id' => $this->product->id,
            'name' => 'Obchod',
            'from_address' => 'obchod@zastavy.test',
            'is_default' => true,
        ]);

        return Conversation::forceCreate([
            'product_id' => $this->product->id,
            'mailbox_id' => $mailbox->id,
            'contact_email' => 'jana@firma.test',
            'contact_name' => 'Jana Nováková',
            'subject' => 'Otázka',
            'last_message_at' => now(),
        ]);
    }
}
