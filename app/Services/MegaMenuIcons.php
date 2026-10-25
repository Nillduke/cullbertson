<?php

namespace App\Services;

class MegaMenuIcons
{
    // Kept inline in the database header so its current colors and sizing apply.
    private const ICONS = [
        '/insurance/homeowners-insurance' => ['home', '<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>'],
        '/insurance/condo-insurance' => ['condo-building', '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1M10 21v-3h4v3"/>'],
        '/insurance/renters-insurance' => ['renters-key', '<circle cx="8" cy="8" r="5"/><path d="m11.5 11.5 9 9m-3-3 2-2m-5-1 2-2"/><circle cx="6.5" cy="6.5" r=".6"/>'],
        '/insurance/flood-insurance' => ['flood-house', '<path d="m4 10 8-7 8 7M6 9v4m12-4v4M10 12V8h4v4M2 16q2.5-3 5 0t5 0 5 0 5 0M2 21q2.5-3 5 0t5 0 5 0 5 0"/>'],
        '/insurance/auto-insurance' => ['auto-car', '<path d="m4 10 3-6h10l3 6v8H4ZM4 10h16M7 18v3m10-3v3M7 14h1m8 0h1"/>'],
        '/insurance/florida-motorcycle-insurance' => ['motorcycle', '<circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/><path d="M19 17 15 5h-3m3 0h3M3 10h5m-3 7 3-5M8 10h4a2 2 0 0 1 2 2v2H9l-2-3M5 17h9l3-7M9 20h5"/>'],
        '/insurance/boat-insurance' => ['sailboat', '<path d="M12 3v11M12 3l-7 9h7m2-7 5 7h-5M3 15l3 5h12l3-5-9-2ZM3 22q3-2 6 0t6 0 6 0"/>'],
        '/insurance/golf-cart-insurance' => ['golf-cart', '<path d="M3 4h15v2H3ZM5 6v9m11-9v5h3l2 4v4h-1M8 10v5h8M4 15h12v4H8m8-4h5"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>'],
        '/insurance/florida-rv-mobile-home-insurance' => ['rv-motorhome', '<path d="M3 6h13l5 6v6H2V7a1 1 0 0 1 1-1ZM15 6v7h6M5 9h6v4H5Zm8 9v-5h-2v5"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>'],
        '/insurance/life-insurance' => ['life-heart', '<path d="M12 21 3.5 12A5 5 0 0 1 12 5a5 5 0 0 1 8.5 7Z"/>'],
        '/insurance/personal-umbrella-protection' => ['umbrella', '<path d="M3 12a9 9 0 0 1 18 0H3ZM12 3v16a3 3 0 0 0 6 0M8 12c0-5 2-9 4-9s4 4 4 9"/>'],
        '/expert-insurance-insights-and-tips-culbertson-agency-articles' => ['articles-book', '<path d="M12 5v16M12 5Q7 1 2 4v15q5-3 10 1 5-4 10-1V4q-5-3-10 1Z"/>'],
        '/insurance' => ['coverage-shield', '<path d="m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6ZM8 12l3 3 5-6"/>'],
        '/media' => ['media-camera', '<path d="M3 7h5l2-3h4l2 3h5v13H3Z"/><circle cx="12" cy="13" r="4"/>'],
        '/claims' => ['claims-clipboard', '<path d="M8 4H5v18h14V4h-3M8 11l3 3 5-5M8 18h8"/><rect x="8" y="2" width="8" height="4" rx="1"/>'],
        '/contact-us' => ['billing-receipt', '<path d="M5 3h14v19l-3-2-4 2-4-2-3 2ZM9 7h6m-6 4h6m-6 4h3"/>'],
        '/get-a-quote' => ['quote-pencil', '<path d="M4 3h11l5 5v4M15 3v5h5M4 3v18h6m3-3 6-6 3 3-6 6h-3ZM7 7h4m-4 5h5"/>'],
    ];

    public static function apply(string $html): string
    {
        return preg_replace_callback('~(<a\b[^>]*\bclass=[\x22\x27][^\x22\x27]*\bmega-item\b[^\x22\x27]*[\x22\x27][^>]*>)(.*?)(</a>)~is', function ($match) {
            if (! preg_match('~\bhref=([\x22\x27])(.*?)\1~is', $match[1], $link)) {
                return $match[0];
            }
            $path = rtrim(parse_url(html_entity_decode($link[2]), PHP_URL_PATH) ?? '', '/');
            if (! isset(self::ICONS[$path])) {
                return $match[0];
            }
            [$name, $drawing] = self::ICONS[$path];
            $svg = '<svg aria-hidden="true" focusable="false" data-menu-icon="'.$name.'" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewBox="0 0 24 24">'.$drawing.'</svg>';
            $body = preg_replace('~(<span\b[^>]*class=[\x22\x27]mega-icon[\x22\x27][^>]*>).*?(</span>)~is', '$1'.$svg.'$2', $match[2], 1);

            return $match[1].$body.$match[3];
        }, $html);
    }
}
