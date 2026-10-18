<?php

namespace App\Http\Controllers\Api;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MailSuppression;
use App\Models\Organization;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingWebhooks;
use App\Services\Messaging\OutboundMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Konverzačné maily projektov.
 *
 * Projekt pošle hotový obsah (šablóny a preklady ostávajú u neho) a
 * referenciu na vec, o ktorej sa píše. Account mail odošle zo schránky
 * projektu, odpoveď spáruje a pošle späť ako webhook `message.received`.
 */
class MessageApiController extends ApiController
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function store(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'to' => ['required', 'email:rfc', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string', 'required_without:text'],
            'text' => ['nullable', 'string'],
            'ref' => ['required', 'array'],
            'ref.type' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.\-]+$/i'],
            'ref.id' => ['required', 'max:120'],
            'ref.label' => ['required', 'string', 'max:255'],
            'ref.url' => ['nullable', 'url', 'max:500'],
            'kind' => ['nullable', 'string', 'max:60'],
            'organization_id' => ['nullable', 'uuid'],
            'mailbox' => ['nullable', 'email', 'max:255'],
            'conversation' => ['nullable', 'uuid'],
            // Koreň vlákna, ak ho projekt doteraz určoval sám (Message-ID bez <>).
            'thread_id' => ['nullable', 'string', 'max:255', 'regex:/^<?[^\s<>@]+@[^\s<>@]+>?$/'],
            'meta' => ['nullable', 'array'],
            // Len hlavičky, ktoré projekt naozaj potrebuje (odhlásenie jedným klikom).
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.filename' => ['required', 'string', 'max:200'],
            'attachments.*.mime' => ['nullable', 'string', 'max:120'],
            'attachments.*.content' => ['required', 'string'],
        ]);

        $organization = null;

        if (! empty($data['organization_id'])) {
            $organization = Organization::where('uuid', $data['organization_id'])->first();
            abort_unless($organization?->isLinkedTo($product), 422, __('messages.organization.not_linked'));
        }

        $attachments = [];

        foreach ($data['attachments'] ?? [] as $i => $file) {
            $content = base64_decode($file['content'], true);
            abort_if($content === false, 422, __('messaging.errors.attachment_encoding', ['index' => $i]));
            $attachments[] = ['filename' => $file['filename'], 'mime' => $file['mime'] ?? null, 'content' => $content];
        }

        $headers = array_intersect_key($data['headers'] ?? [], array_flip(OutboundMailer::ALLOWED_HEADERS));

        try {
            $message = $this->conversations->sendFromProject($product, [
                ...$data,
                'ref' => [...$data['ref'], 'id' => (string) $data['ref']['id']],
                'organization' => $organization,
                'attachments' => $attachments,
                'meta' => $headers === [] ? ($data['meta'] ?? null) : [...($data['meta'] ?? []), 'headers' => $headers],
            ]);
        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
        }

        $message->refresh()->load(['conversation.mailbox', 'conversation.organization', 'attachments']);

        return response()->json([
            'data' => MessagingWebhooks::payload($message->conversation, $message),
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'ref_type' => ['nullable', 'string', 'max:60'],
            'ref_id' => ['nullable', 'max:120'],
            'contact_email' => ['nullable', 'email'],
            'organization_id' => ['nullable', 'uuid'],
        ]);

        $conversations = Conversation::query()
            ->where('product_id', $product->id)
            ->whereNotNull('ref_type')
            ->when($data['ref_type'] ?? null, fn ($q, $v) => $q->where('ref_type', $v))
            ->when(isset($data['ref_id']), fn ($q) => $q->where('ref_id', (string) $data['ref_id']))
            ->when($data['contact_email'] ?? null, fn ($q, $v) => $q->where('contact_email', strtolower($v)))
            ->when($data['organization_id'] ?? null, fn ($q, $v) => $q->whereHas('organization', fn ($o) => $o->where('uuid', $v)))
            ->with(['mailbox', 'organization'])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (Conversation $c) => MessagingWebhooks::conversation($c))->values(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->product_id === $this->product($request)->id, 404);

        $conversation->load(['mailbox', 'organization', 'messages.attachments', 'messages.sender']);

        return response()->json([
            'data' => [
                ...MessagingWebhooks::conversation($conversation),
                'messages' => $conversation->messages
                    ->map(fn (ConversationMessage $m) => MessagingWebhooks::message($m))
                    ->values(),
            ],
        ]);
    }

    /* ---------------------------------------------------------------
     | Blokované adresy
     |---------------------------------------------------------------*/

    public function suppression(Request $request, string $email): JsonResponse
    {
        $suppression = MailSuppression::query()
            ->where('product_id', $this->product($request)->id)
            ->where('email', strtolower(trim($email)))
            ->first();

        return response()->json(['data' => [
            'email' => strtolower(trim($email)),
            'suppressed' => $suppression !== null,
            'reason' => $suppression?->reason,
            'note' => $suppression?->note,
            'since' => $suppression?->created_at?->toIso8601String(),
        ]]);
    }

    /** Projekt hlási odhlásenie alebo ručné zablokovanie. */
    public function suppress(Request $request): JsonResponse
    {
        $product = $this->product($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'in:'.MailSuppression::UNSUBSCRIBED.','.MailSuppression::MANUAL],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        MailSuppression::updateOrCreate(
            ['product_id' => $product->id, 'email' => strtolower(trim($data['email']))],
            ['reason' => $data['reason'] ?? MailSuppression::UNSUBSCRIBED, 'note' => $data['note'] ?? null],
        );

        return $this->suppression($request, $data['email'])->setStatusCode(201);
    }

    /** Adresa je znova v poriadku (klient ju opravil, schránka znova žije). */
    public function unsuppress(Request $request, string $email): JsonResponse
    {
        MailSuppression::query()
            ->where('product_id', $this->product($request)->id)
            ->where('email', strtolower(trim($email)))
            ->delete();

        return response()->json(null, 204);
    }
}
