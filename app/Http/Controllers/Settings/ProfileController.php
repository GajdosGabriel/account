<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'operators' => \App\Models\User::orderBy('name')->get(['name', 'email'])->map(fn ($u) => [
                'name' => $u->name,
                'email' => $u->email,
                'is_me' => $u->email === $user->email,
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

        return back()->with('success', 'Údaje boli uložené.');
    }

    /** Pozvanie ďalšieho operátora. */
    public function storeOperator(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = \App\Models\User::create($data);

        AuditLog::record('operator.created', $user, ['email' => $user->email]);

        return back()->with('success', "Operátor {$user->email} bol vytvorený.");
    }
}
