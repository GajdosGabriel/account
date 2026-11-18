<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesMailingData;
use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingLayout;
use App\Models\MailingTemplate;
use App\Models\Product;
use App\Services\Mailing\MailingRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Šablóny kampaní a HTML layouty, do ktorých sa e-mail obalí. */
class MailingTemplateController extends Controller
{
    use SharesMailingData;

    public function index(Request $request): Response
    {
        $product = $request->filled('product') ? Product::where('key', $request->input('product'))->first() : null;

        return Inertia::render('Mailing/Templates', [
            'templates' => MailingTemplate::query()->with(['product', 'layout', 'mailbox'])
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderBy('name')->get()
                ->map(fn (MailingTemplate $t) => [
                    ...$t->only(['id', 'name', 'subject', 'heading', 'body', 'button_label', 'button_url', 'layout_id', 'mailbox_id']),
                    'product_key' => $t->product?->key,
                    'product' => $t->product?->name,
                    'layout' => $t->layout?->name,
                    'mailbox' => $t->mailbox?->from_address,
                ]),
            // Aj vypnuté – šablóna môže čakať na schránku, ktorej správca ešte nedoplnil heslo.
            'mailboxes' => Mailbox::query()->with('product:id,key')
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (Mailbox $m) => [
                    'id' => $m->id,
                    'product_key' => $m->product?->key,
                    'label' => ($m->from_name ? "{$m->from_name} <{$m->from_address}>" : $m->from_address).($m->is_active ? '' : ' – '.__('messaging.mailboxes.inactive_badge')),
                ]),
            'layouts' => MailingLayout::query()->with('product')
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->orderBy('name')->get()
                ->map(fn (MailingLayout $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'html' => $l->html,
                    'product_key' => $l->product?->key,
                    'product' => $l->product?->name,
                ]),
            'default_layout' => MailingRenderer::defaultLayout(),
            'filters' => ['product' => $product?->key],
            'products' => $this->products(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MailingTemplate::create($this->validatedTemplate($request));

        return back()->with('success', __('mailing.templates.saved'));
    }

    public function update(Request $request, MailingTemplate $template): RedirectResponse
    {
        $template->update($this->validatedTemplate($request));

        return back()->with('success', __('mailing.templates.saved'));
    }

    public function destroy(MailingTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('mailing.templates.deleted'));
    }

    public function storeLayout(Request $request): RedirectResponse
    {
        MailingLayout::create($this->validatedLayout($request));

        return back()->with('success', __('mailing.layouts.saved'));
    }

    public function updateLayout(Request $request, MailingLayout $layout): RedirectResponse
    {
        $layout->update($this->validatedLayout($request));

        return back()->with('success', __('mailing.layouts.saved'));
    }

    public function destroyLayout(MailingLayout $layout): RedirectResponse
    {
        if (MailingCampaign::where('layout_id', $layout->id)->where('status', MailingCampaign::QUEUED)->exists()) {
            return back()->with('error', __('mailing.layouts.in_use'));
        }

        $layout->delete();

        return back()->with('success', __('mailing.layouts.deleted'));
    }

    /** @return array<string, mixed> */
    private function validatedTemplate(Request $request): array
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'layout_id' => ['nullable', 'integer', 'exists:mailing_layouts,id'],
            'mailbox_id' => ['nullable', 'integer', 'exists:mailboxes,id'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'heading' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();
        abort_if(isset($data['layout_id']) && ! MailingLayout::whereKey($data['layout_id'])->where('product_id', $product->id)->exists(), 422);
        abort_if(isset($data['mailbox_id']) && ! Mailbox::whereKey($data['mailbox_id'])->where('product_id', $product->id)->exists(), 422);
        unset($data['product_key']);

        return [...$data, 'product_id' => $product->id];
    }

    /** @return array<string, mixed> */
    private function validatedLayout(Request $request): array
    {
        $data = $request->validate([
            'product_key' => ['required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:120'],
            'html' => ['required', 'string', 'max:200000'],
        ]);

        $product = Product::where('key', $data['product_key'])->firstOrFail();

        return ['product_id' => $product->id, 'name' => $data['name'], 'html' => $data['html']];
    }
}
