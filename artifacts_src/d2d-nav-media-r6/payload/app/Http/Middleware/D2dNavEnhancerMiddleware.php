<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class D2dNavEnhancerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || $html === '' || stripos($html, '</body>') === false) {
            return $response;
        }

        if (str_contains($html, 'data-d2d-nav-r6="1"')) {
            return $response;
        }

        $script = '<script src="/d2d-nav-r6.js?v=20260908-r6" defer data-d2d-nav-r6="1"></script>';
        $html = preg_replace('#</body>#i', $script.'</body>', $html, 1) ?? $html;
        $response->setContent($html);

        return $response;
    }
}
