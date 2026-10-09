<?php

namespace App\Http\Middleware;

use App\Cms\Referrers;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts where visitors to the public site come from (see Referrers), for
 * pages they see: not the control panel, feeds or images.
 */
class CountReferrers
{
    public function __construct(private Referrers $referrers) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && ! $request->routeIs('cp.*')
            && $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
        ) {
            $this->referrers->record($request);
        }

        return $response;
    }
}
