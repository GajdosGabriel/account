<?php

namespace App\Http\Controllers;

use App\Events\AccountMessageEvent;
use App\Services\AccountClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Príjem udalostí z Accountu.
 *
 * Route (bez CSRF):
 *   Route::post('/webhooks/account', AccountWebhookController::class)
 *       ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
 */
class AccountWebhookController extends Controller
{
    public function __construct(private readonly AccountClient $account) {}

    public function __invoke(Request $request): JsonResponse
    {
        $timestamp = (int) $request->header('X-Accounts-Timestamp');
        $signature = (string) $request->header('X-Accounts-Signature');

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), config('account.webhook_secret'));

        abort_unless($signature !== '' && hash_equals($expected, $signature), 400, 'Neplatný podpis.');

        // Ochrana proti replay útoku
        abort_if(abs(time() - $timestamp) > 300, 400, 'Zastaraná požiadavka.');

        $event = (string) $request->input('event');

        // Správy – projekt si ich spracuje vlastným listenerom.
        // 200 aj vtedy, keď vec v projekte už neexistuje, inak by Account
        // doručenie zbytočne opakoval celé hodiny.
        if (str_starts_with($event, 'message.')) {
            AccountMessageEvent::dispatch(
                $event,
                (array) $request->input('data.conversation', []),
                (array) $request->input('data.message', []),
            );

            return response()->json(['ok' => true]);
        }

        $organizationId = $request->input('data.organization_id')
            ?? $request->input('data.organization.id');

        if ($organizationId) {
            $this->account->forget($organizationId);
        }

        return response()->json(['ok' => true]);
    }
}
