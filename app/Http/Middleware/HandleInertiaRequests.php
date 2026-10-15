<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $messages = json_decode(file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);

        return [
            ...parent::share($request),
            'i18n' => Inertia::always(['locale' => $locale, 'messages' => $messages]),
            'navigation' => [
                'home' => route('home', absolute: false),
                'privacy' => route('privacy', absolute: false),
            ],
        ];
    }
}
