<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ReplyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Šablóny odpovedí pre inbox.
 *
 * Zdieľané šablóny môže upraviť ktokoľvek z tímu, súkromné len autor.
 * Skratka musí byť jedinečná medzi šablónami, ktoré operátor v danom
 * projekte vidí – inak by „/cena“ + Tab nevedelo, ktorú vložiť.
 */
class ReplyTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Inbox/Templates', [
            'templates' => ReplyTemplate::query()
                ->visibleTo($user)
                ->with(['product', 'author'])
                ->orderByRaw('case when product_id is null then 0 else 1 end')
                ->orderBy('product_id')
                ->orderBy('name')
                ->get()
                ->map(fn (ReplyTemplate $t) => self::row($t) + [
                    'author' => $t->author?->name,
                    'updated_at' => $t->updated_at?->toIso8601String(),
                    'last_used_at' => $t->last_used_at?->diffForHumans(),
                ]),
            'products' => Product::query()->orderBy('name')->get(['key', 'name']),
            'variables' => ReplyTemplate::VARIABLES,
            'sample' => self::sample($user->name),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = new ReplyTemplate;
        $template->created_by_user_id = $request->user()->id;
        $this->fill($template, $request)->save();

        return back()->with('success', __('messaging.templates.saved'));
    }

    public function update(Request $request, ReplyTemplate $template): RedirectResponse
    {
        abort_unless($template->canBeEditedBy($request->user()), 403);

        $this->fill($template, $request)->save();

        return back()->with('success', __('messaging.templates.saved'));
    }

    public function destroy(Request $request, ReplyTemplate $template): RedirectResponse
    {
        abort_unless($template->canBeEditedBy($request->user()), 403);

        $template->delete();

        return back()->with('success', __('messaging.templates.deleted'));
    }

    /** Údaje šablóny pre frontend – zoznam aj výber v odpovedi. */
    public static function row(ReplyTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'shortcut' => $t->shortcut,
            'subject' => $t->subject,
            'body' => $t->body,
            'product_key' => $t->product?->key,
            'product' => $t->product?->name,
            'private' => $t->isPrivate(),
            'uses' => $t->uses_count,
        ];
    }

    /** Ukážkové hodnoty premenných pre náhľad v editore. */
    public static function sample(string $agent): array
    {
        return [
            'first_name' => 'Jana',
            'name' => 'Jana Nováková',
            'email' => 'jana@firma.sk',
            'organization' => 'Firma s.r.o.',
            'product' => 'Projekt',
            'subject' => __('messaging.templates.sample_subject'),
            'ref' => __('messaging.templates.sample_ref'),
            'agent' => $agent,
            'agent_first_name' => strtok($agent, ' ') ?: $agent,
        ];
    }

    private function fill(ReplyTemplate $template, Request $request): ReplyTemplate
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Bez medzier – píše sa ako „/cena“; úvodnú lomku odstránime.
            'shortcut' => ['nullable', 'string', 'max:40', 'regex:/^\/?[\pL\pN_-]+$/u'],
            'product_key' => ['nullable', 'exists:products,key'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'private' => ['boolean'],
        ], [
            'shortcut.regex' => __('messaging.templates.shortcut_invalid'),
        ]);

        $productId = filled($data['product_key'] ?? null)
            ? Product::where('key', $data['product_key'])->value('id')
            : null;
        $shortcut = filled($data['shortcut'] ?? null) ? mb_strtolower(ltrim($data['shortcut'], '/')) : null;

        if ($shortcut && $this->shortcutTaken($shortcut, $productId, $template, $request)) {
            throw ValidationException::withMessages([
                'shortcut' => __('messaging.templates.shortcut_taken', ['shortcut' => $shortcut]),
            ]);
        }

        $template->fill([
            'name' => trim($data['name']),
            'shortcut' => $shortcut,
            'product_id' => $productId,
            'subject' => filled($data['subject'] ?? null) ? trim($data['subject']) : null,
            'body' => rtrim($data['body']),
            // Súkromnú upravuje len jej autor, zdieľanú si súkromnou urobí ten, kto ju práve upravuje.
            'user_id' => ($data['private'] ?? false) ? ($template->user_id ?? $user->id) : null,
        ]);

        return $template;
    }

    /**
     * Koliduje so skratkou, ktorú by operátor videl v rovnakom projekte:
     * spoločná šablóna koliduje so všetkými, projektová so spoločnými
     * a s tým istým projektom.
     */
    private function shortcutTaken(string $shortcut, ?int $productId, ReplyTemplate $template, Request $request): bool
    {
        return ReplyTemplate::query()
            ->visibleTo($request->user())
            ->when($template->exists, fn ($q) => $q->whereKeyNot($template->id))
            ->where('shortcut', $shortcut)
            ->when($productId, fn ($q) => $q->forProduct($productId))
            ->exists();
    }
}
