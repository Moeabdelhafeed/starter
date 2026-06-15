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

        return Inertia::location($request->headers->get('referer'));
    }
}
