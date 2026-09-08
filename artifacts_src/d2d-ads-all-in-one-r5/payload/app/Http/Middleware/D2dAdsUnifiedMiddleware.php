<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class D2dAdsUnifiedMiddleware
{
    private const PUBLISHER_ID = 'ca-pub-5807824613154542';
    private const MID_SLOT = '9432468543';
    private const END_SLOT = '8039495827';
    private const SCRIPT = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';

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

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($type !== '' && !str_contains($type, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || $html === '') {
            return $response;
        }

        // Always clean older D2D AdSense injections first. This prevents R2/R3/R4
        // middleware or cached markup from creating navbar/footer/random ads.
        $html = $this->stripLegacyAds($html);

        $approved = $this->approved(trim($request->path(), '/'));
        $liveHost = in_array(strtolower($request->getHost()), ['dares2dream.com', 'www.dares2dream.com'], true);

        if (!$approved || !$liveHost) {
            $response->setContent($html);
            return $response;
        }

        $runtime = json_encode([
            'publisher' => self::PUBLISHER_ID,
            'mid' => self::MID_SLOT,
            'end' => self::END_SLOT,
            'path' => trim($request->path(), '/'),
        ], JSON_UNESCAPED_SLASHES);

        $head = '<link rel="stylesheet" href="/d2d-ads-r5.css?v=20260908r5">'
              . '<script async src="'.self::SCRIPT.'?client='.self::PUBLISHER_ID.'" crossorigin="anonymous" data-d2d-r5-adsense="1"></script>';

        $body = '<script data-d2d-r5-config="1">window.D2D_ADS_R5='.$runtime.';</script>'
              . '<script src="/d2d-ads-r5.js?v=20260908r5" defer data-d2d-r5-script="1"></script>';

        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('#</head>#i', $head.'</head>', $html, 1) ?? $html;
        }
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('#</body>#i', $body.'</body>', $html, 1) ?? $html;
        }

        $response->setContent($html);
        return $response;
    }

    private function approved(string $path): bool
    {
        foreach (self::APPROVED as $regex) {
            if (preg_match($regex, $path) === 1) return true;
        }
        return false;
    }

    private function stripLegacyAds(string $html): string
    {
        $publisher = preg_quote(self::PUBLISHER_ID, '#');

        // Remove all prior AdSense bootstrap tags for this publisher.
        $html = preg_replace(
            '#<script\b[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js[^"\']*'.$publisher.'[^"\']*["\'][^>]*>\s*</script>#i',
            '',
            $html
        ) ?? $html;

        // Remove prior manual units for this publisher so only R5 placements remain.
        $html = preg_replace(
            '#<ins\b(?=[^>]*class=["\'][^"\']*adsbygoogle[^"\']*["\'])(?=[^>]*data-ad-client=["\']'.$publisher.'["\'])[^>]*>.*?</ins>#is',
            '',
            $html
        ) ?? $html;

        // Remove old push scripts left beside removed units.
        $html = preg_replace(
            '#<script\b[^>]*>\s*\(?\s*adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\]\s*\)?\.push\s*\(\s*\{\s*\}\s*\)\s*;?\s*</script>#is',
            '',
            $html
        ) ?? $html;

        // Remove older D2D ads JS so a previous patch cannot add ads after page load.
        $html = preg_replace(
            '#<script\b[^>]*src=["\'][^"\']*/d2d-[^"\']*ads[^"\']*\.js[^"\']*["\'][^>]*>\s*</script>#i',
            '',
            $html
        ) ?? $html;

        // Remove prior runtime config blocks from older ads packs.
        $html = preg_replace('#<script\b[^>]*data-d2d-(?:ads-runtime|live-ads|manual-ads|r4-ads)[^>]*>.*?</script>#is', '', $html) ?? $html;

        return $html;
    }
}
