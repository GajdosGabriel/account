<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\Product;
use App\Services\Messaging\MailboxReader;
use App\Services\Messaging\OutboundMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Schránky projektov: odkiaľ Account posiela a odkiaľ číta odpovede.
 *
 * Heslá sa do formulára nikdy neposielajú späť. Prázdne pole pri úprave
 * znamená „nechať pôvodné“.
 */
class MailboxController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inbox/Mailboxes', [
            'mailboxes' => Mailbox::query()
                ->with('product')
                ->orderBy('product_id')
                ->orderByDesc('is_default')
                ->get()
                ->map(fn (Mailbox $m) => [
                    'id' => $m->id,
                    'product_key' => $m->product?->key,
                    'product' => $m->product?->name,
                    'name' => $m->name,
                    'from_address' => $m->from_address,
                    'from_name' => $m->from_name,
                    'smtp_host' => $m->smtp_host,
                    'smtp_port' => $m->smtp_port,
                    'smtp_encryption' => $m->smtp_encryption,
                    'smtp_username' => $m->smtp_username,
                    'has_smtp_password' => filled($m->getRawOriginal('smtp_password')),
                    'imap_host' => $m->imap_host,
                    'imap_port' => $m->imap_port,
                    'imap_encryption' => $m->imap_encryption,
                    'imap_username' => $m->imap_username,
                    'has_imap_password' => filled($m->getRawOriginal('imap_password')),
                    'imap_folder' => $m->imap_folder,
                    'imap_checked_at' => $m->imap_checked_at?->diffForHumans(),
                    'imap_error' => $m->imap_error,
                    'is_default' => $m->is_default,
                    'is_active' => $m->is_active,
                ]),
            'products' => Product::query()->orderBy('name')->get(['key', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $product = Product::where('key', $data['product_key'])->firstOrFail();

        $mailbox = new Mailbox(['product_id' => $product->id]);
        $this->fill($mailbox, $data)->save();
        $this->keepSingleDefault($mailbox);

        return back()->with('success', __('messaging.mailboxes.saved'));
    }

    public function update(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $data = $this->validated($request, $mailbox);

        $this->fill($mailbox, $data)->save();
        $this->keepSingleDefault($mailbox);

        return back()->with('success', __('messaging.mailboxes.saved'));
    }

    public function destroy(Mailbox $mailbox): RedirectResponse
    {
        $mailbox->delete();

        return back()->with('success', __('messaging.mailboxes.deleted'));
    }

    /** Skúšobné prihlásenie na IMAP a odoslanie testovacieho mailu operátorovi. */
    public function test(Request $request, Mailbox $mailbox, MailboxReader $reader, OutboundMailer $mailer): RedirectResponse
    {
        $results = [];

        if ($mailbox->readsInbound()) {
            try {
                $reader->test($mailbox);
                $results[] = __('messaging.mailboxes.test_imap_ok');
            } catch (\Throwable $e) {
                return back()->with('error', __('messaging.mailboxes.test_imap_failed', ['error' => $e->getMessage()]));
            }
        }

        try {
            $mailer->mailer($mailbox)->raw(
                __('messaging.mailboxes.test_body', ['mailbox' => $mailbox->from_address]),
                fn ($m) => $m->from($mailbox->from_address, $mailbox->from_name)
                    ->to($request->user()->email)
                    ->subject(__('messaging.mailboxes.test_subject')),
            );
            $results[] = __('messaging.mailboxes.test_smtp_ok', ['email' => $request->user()->email]);
        } catch (\Throwable $e) {
            return back()->with('error', __('messaging.mailboxes.test_smtp_failed', ['error' => $e->getMessage()]));
        }

        return back()->with('success', implode(' ', $results));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Mailbox $mailbox = null): array
    {
        return $request->validate([
            'product_key' => [$mailbox ? 'nullable' : 'required', 'exists:products,key'],
            'name' => ['required', 'string', 'max:120'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'starttls', 'none'])],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:500'],
            'imap_folder' => ['nullable', 'string', 'max:120'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fill(Mailbox $mailbox, array $data): Mailbox
    {
        $passwords = ['smtp_password', 'imap_password'];

        $mailbox->fill(collect($data)->except(['product_key', ...$passwords])->all());
        $mailbox->from_address = strtolower(trim($data['from_address']));
        $mailbox->imap_folder = ($data['imap_folder'] ?? null) ?: 'INBOX';

        foreach ($passwords as $field) {
            if (filled($data[$field] ?? null)) {
                $mailbox->{$field} = $data[$field];
            }
        }

        // Zmena IMAP účtu alebo priečinka = iné UID, začíname odznova.
        if ($mailbox->isDirty(['imap_host', 'imap_username', 'imap_folder'])) {
            $mailbox->imap_last_uid = null;
        }

        return $mailbox;
    }

    private function keepSingleDefault(Mailbox $mailbox): void
    {
        if ($mailbox->is_default) {
            Mailbox::query()
                ->where('product_id', $mailbox->product_id)
                ->whereKeyNot($mailbox->id)
                ->update(['is_default' => false]);
        }
    }
}
