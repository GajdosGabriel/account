<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // `current_password` overí, že zadané heslo naozaj patrí prihlásenému
            // používateľovi – bez toho by na zmenu stačil ukradnutý session cookie.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Pozor: logoutOtherDevices() heslo aj prepisuje, preto mu treba
        // odovzdať NOVÉ heslo, nie staré.
        Auth::logoutOtherDevices($data['password']);

        $request->user()->update(['password' => $data['password']]);

        AuditLog::record('password.changed', $request->user());

        return back()->with('success', 'Heslo bolo zmenené. Ostatné zariadenia sme odhlásili.');
    }
}
