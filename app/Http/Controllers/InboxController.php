<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessagingException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spoločný inbox – konverzácie zo všetkých projektov na jednom mieste.
 *
 * Projekt sa o odpovedi operátora dozvie webhookom `message.sent`
 * (source = inbox), takže história u neho ostáva úplná.
 */
class InboxController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'product' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $conversations = Conversation::query()
            ->with(['product', 'organization'])
            ->when($filters['product'] ?? null, fn ($q, $key) => $q->whereHas('product', fn ($p) => $p->where('key', $key)))
            ->when($filters['status'] ?? null, function ($q, $status) {
                match ($status) {
                    'unread' => $q->where('unread_count', '>', 0),
                    'unassigned' => $q->unassigned(),
                    'active' => $q->where('status', '!=', ConversationStatus::Closed->value),
                    default => $q->where('status', $status),
                };
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($w) => $w->where('contact_email', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('ref_label', 'like', $like));
            })
            ->orderByRaw('case when unread_count > 0 then 0 else 1 end')
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Conversation $c) => $this->row($c));

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'filters' => $filters,
            'products' => Product::query()->orderBy('name')->get(['key', 'name']),
            'statuses' => $this->statusOptions(),
            'counts' => [
                'unread' => Conversation::where('unread_count', '>', 0)->count(),
                'unassigned' => Conversation::unassigned()->count(),
            ],
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load(['product', 'organization', 'mailbox', 'messages.attachments', 'messages.sender']);
        $this->conversations->markRead($conversation);

        // Tá istá adresa alebo firma inde – len na informáciu, nič sa nezlučuje.
        $related = Conversation::query()
            ->with('product')
            ->whereKeyNot($conversation->id)
            ->where(fn ($q) => $q->where('contact_email', $conversation->contact_email)
                ->when($conversation->organization_id, fn ($w, $id) => $w->orWhere('organization_id', $id)))
            ->orderByDesc('last_message_at')
            ->limit(15)
            ->get()
            ->map(fn (Conversation $c) => $this->row($c));

        // Kam sa dá nepriradená konverzácia zlúčiť
        $targets = $conversation->isAssigned() ? [] : Conversation::query()
            ->where('product_id', $conversation->product_id)
            ->whereNotNull('ref_type')
            ->orderByRaw('case when contact_email = ? then 0 else 1 end', [$conversation->contact_email])
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->uuid,
                'label' => trim(($c->ref_label ?? $c->ref_type.':'.$c->ref_id).' · '.$c->contact_email),
            ]);

        return Inertia::render('Inbox/Show', [
            'conversation' => [
                ...$this->row($conversation),
                'mailbox' => $conversation->mailbox?->from_address,
                'messages' => $conversation->messages->map(fn (ConversationMessage $m) => [
                    'id' => $m->uuid,
                    'direction' => $m->direction,
                    'source' => $m->source,
                    'kind' => $m->kind,
                    'status' => $m->status->value,
                    'status_label' => $m->status->label(),
                    'status_tone' => $m->status->color(),
                    'from' => $m->from_name ? "{$m->from_name} <{$m->from_email}>" : $m->from_email,
                    'to' => $m->to_email,
                    'subject' => $m->subject,
                    'reply_text' => $m->reply_text,
                    'text' => $m->body_text,
                    'error' => $m->error,
                    'sent_by' => $m->sender?->name,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'attachments' => $m->attachments->map(fn (ConversationAttachment $a) => [
                        'filename' => $a->filename,
                        'size' => $a->size,
                        'url' => $a->temporaryUrl(1),
                    ]),
                ]),
            ],
            'related' => $related,
            'targets' => $targets,
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->conversations->reply($conversation, $request->user(), $data['text'], $data['subject'] ?? null);
        } catch (MessagingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('messaging.inbox.reply_queued'));
    }

    public function status(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ConversationStatus::class)],
        ]);

        $this->conversations->changeStatus($conversation, ConversationStatus::from($data['status']));

        return back()->with('success', __('messaging.inbox.status_changed'));
    }

    public function merge(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_if($conversation->isAssigned(), 422);

        $data = $request->validate(['target' => ['required', 'uuid']]);

        $target = Conversation::query()
            ->where('uuid', $data['target'])
            ->where('product_id', $conversation->product_id)
            ->whereNotNull('ref_type')
            ->firstOrFail();

        $this->conversations->mergeInto($conversation, $target);

        return redirect()->route('inbox.show', $target)->with('success', __('messaging.inbox.merged'));
    }

    /** Príloha – podpísaný odkaz, aby ho mohol použiť aj projekt z webhooku. */
    public function attachment(ConversationAttachment $attachment): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($attachment->contents()),
            $attachment->filename,
            array_filter(['Content-Type' => $attachment->mime]),
        );
    }

    /** @return array<string, mixed> */
    private function row(Conversation $c): array
    {
        return [
            'id' => $c->uuid,
            'product' => $c->product?->name,
            'product_key' => $c->product?->key,
            'ref_type' => $c->ref_type,
            'ref_id' => $c->ref_id,
            'ref_label' => $c->ref_label,
            'ref_url' => $c->ref_url,
            'assigned' => $c->isAssigned(),
            'contact_email' => $c->contact_email,
            'contact_name' => $c->contact_name,
            'organization' => $c->organization?->name,
            'organization_id' => $c->organization?->uuid,
            'subject' => $c->subject,
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'status_tone' => $c->status->color(),
            'unread' => $c->unread_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return array_map(fn (ConversationStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ], ConversationStatus::cases());
    }
}
