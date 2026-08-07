<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Laravel zámerne vracia rovnakú odpoveď aj pri neexistujúcom e-maile,
        // aby sa nedalo zisťovať, kto má u nás účet.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Ak účet s týmto e-mailom existuje, poslali sme naň odkaz na obnovu hesla.');
    }
}
