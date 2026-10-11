<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;

class SetBrowserLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en_US';
        foreach (AcceptHeader::fromString($request->header('Accept-Language', ''))->all() as $preference) {
            if ($preference->getQuality() <= 0) {
                continue;
            }
            $language = strtolower(explode('-', str_replace('_', '-', $preference->getValue()))[0]);
            if (in_array($language, ['nl', 'en'], true)) {
                $locale = $language === 'nl' ? 'nl' : 'en_US';
                break;
            }
        }
        App::setLocale($locale);
        $response = $next($request);
        $response->headers->set('Content-Language', str_replace('_', '-', $locale));
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
