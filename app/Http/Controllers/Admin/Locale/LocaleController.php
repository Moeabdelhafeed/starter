<?php

namespace App\Http\Controllers\Admin\Locale;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    public function setLocale(Request $request): Response
    {
        $request->validate([
            'locale' => 'required|string|in:ar,en',
        ]);
        session()->put('locale', $request->locale);

        // The Referer is attacker-controllable, so it is only followed when it
        // points back at this app; anything else lands on the dashboard.
        $referer = (string) $request->headers->get('referer');
        $isSameHost = $referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost();

        return Inertia::location($isSameHost ? $referer : route('dashboard'));
    }
}
