<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'last_login_at' => $user->last_login_at?->diffForHumans(),
            ],
            'operators' => User::orderBy('name')->get(['id', 'name', 'email', 'last_login_at'])->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'last_login_at' => $u->last_login_at?->diffForHumans(),
                'is_me' => $u->is($user),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]));

        AuditLog::record('profile.updated', $user);

        return back()->with('success', __('flash.saved'));
    }

    /**
     * Pozvanie ďalšieho operátora.
     *
     * Heslo mu nevymýšľa kolega – dostane e-mail s odkazom na nastavenie
     * vlastného. Kým ho nenastaví, náhodné heslo v databáze nepozná nikto.
     */
    public function storeOperator(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
        ]);

        // Odobratý operátor sa vracia pod pôvodným záznamom – jeho staré
        // správy a záznamy v audite tak zostanú pri ňom.
        $user = User::withTrashed()->firstOrNew(['email' => $data['email']]);
        $user->forceFill([
            'name' => $data['name'],
            'password' => Str::password(40),
            'remember_token' => Str::random(60),
            'deleted_at' => null,
        ])->save();

        $status = Password::sendResetLink(['email' => $user->email]);

        AuditLog::record('operator.created', $user, ['email' => $user->email]);

        return $status === Password::ResetLinkSent
            ? back()->with('success', "Operátor {$user->email} bol vytvorený, odkaz na nastavenie hesla dostal e-mailom.")
            : back()->with('error', "Operátor {$user->email} bol vytvorený, ale e-mail s odkazom sa nepodarilo odoslať. Nech si heslo obnoví cez „Zabudnuté heslo“.");
    }

    /** Odobratie prístupu. Záznam zostáva (mäkké zmazanie) kvôli auditu. */
    public function destroyOperator(Request $request, User $operator): RedirectResponse
    {
        if ($operator->is($request->user())) {
            return back()->with('error', __('flash.auth.cannot_remove_self'));
        }

        if (User::count() <= 1) {
            return back()->with('error', __('flash.auth.cannot_remove_last'));
        }

        // Odhlásiť ho všade: „zapamätať si ma“ aj otvorené relácie.
        $operator->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $operator->id)->delete();
        }

        $operator->delete();

        AuditLog::record('operator.deleted', $operator, ['email' => $operator->email]);

        return back()->with('success', "Operátor {$operator->email} už nemá prístup.");
    }
}
