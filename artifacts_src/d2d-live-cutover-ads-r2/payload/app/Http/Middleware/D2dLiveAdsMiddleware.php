<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class D2dLiveAdsMiddleware
{
    private const PUBLISHER_ID = 'ca-pub-5807824613154542';
    private const SCRIPT_HOST = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';

    private const APPROVED = [
        '#^blog/[^/]+$#',
        '#^universities/[^/]+$#',
        '#^scholarships/[^/]+$#',
        '#^opportunities/[^/]+$#',
        '#^jobs/[^/]+$#',
    ];

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
        if (!is_string($html) || $html === '') {
            return $response;
        }

        $approved = $this->isApprovedPath($request->path());
        $liveHost = in_array(strtolower($request->getHost()), ['dares2dream.com', 'www.dares2dream.com'], true);

        // Hard safety gate: strip this publisher's AdSense bootstrap from every non-approved
        // page and from preview/staging. This keeps home/listings/resources/tools ad-free.
        if (!$approved || !$liveHost) {
            $html = $this->stripPublisherScript($html);
            $response->setContent($html);
            return $response;
        }

        if (!str_contains($html, self::PUBLISHER_ID)) {
            $script = '<script async src="'.self::SCRIPT_HOST.'?client='.self::PUBLISHER_ID.'" crossorigin="anonymous" data-d2d-live-ads="1"></script>';
            if (stripos($html, '</head>') !== false) {
                $html = preg_replace('#</head>#i', $script.'</head>', $html, 1) ?? $html;
            }
        }

        $response->setContent($html);
        return $response;
    }

    private function isApprovedPath(string $path): bool
    {
        $path = trim($path, '/');
        foreach (self::APPROVED as $regex) {
            if (preg_match($regex, $path) === 1) return true;
        }
        return false;
    }

    private function stripPublisherScript(string $html): string
    {
        $publisher = preg_quote(self::PUBLISHER_ID, '#');
        $pattern = '#<script\b[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js\?client='.$publisher.'[^"\']*["\'][^>]*>\s*</script>#i';
        return preg_replace($pattern, '', $html) ?? $html;
    }
}
