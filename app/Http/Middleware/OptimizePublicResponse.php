<?php

namespace App\Http\Middleware;

use App\Services\ResponsiveImages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptimizePublicResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->is('admin', 'admin/*') || ! $response instanceof \Illuminate\Http\Response || ! str_contains($response->headers->get('Content-Type', ''), 'text/html') || $response->getStatusCode() !== 200) {
            return $response;
        }
        $html = app(ResponsiveImages::class)->html($response->getContent());
        // Discover overview/article hero photos before CSS and secondary backgrounds.
        $preloadHero = $request->is('insurance') || preg_match('~<body\b[^>]*class="[^"]*\bblog-detail-page\b~i', $html);
        if ($preloadHero && preg_match('~<img\b[^>]*fetchpriority="high"[^>]*>~i', $html, $hero)) {
            preg_match('~\bsrc="([^"]+)"~', $hero[0], $src);
            preg_match('~\bsrcset="([^"]+)"~', $hero[0], $srcset);
            preg_match('~\bsizes="([^"]+)"~', $hero[0], $sizes);
            if ($src && $srcset && $sizes) {
                $preload = '<link rel="preload" as="image" href="'.app(ResponsiveImages::class)->url($src[1], 768).'" imagesrcset="'.$srcset[1].'" imagesizes="'.$sizes[1].'" fetchpriority="high">';
                $html = str_replace('</head>', $preload.'</head>', $html);
            }
        }
        // The background remains full-screen; defer the video bytes until playback is appropriate.
        $html = preg_replace_callback('~(<video\b[^>]*\bid="hero-video"[^>]*>)(.*?)</video>~is', function ($match) {
            $tag = preg_replace('~\sautoplay(?:="[^"]*")?~i', '', $match[1]);
            $tag = preg_replace('~\bpreload="[^"]*"~i', 'preload="none"', $tag);
            $tag = preg_replace_callback('~\bposter="([^"]*)"~', fn ($m) => 'poster="'.app(ResponsiveImages::class)->url($m[1], 768).'"', $tag);
            $source = $match[2];
            if (is_file(public_path('assets/florida-life-optimized.mp4'))) {
                $source = str_replace('/assets/florida-life.mp4', '/assets/florida-life-optimized.mp4', $source);
            }

            return $tag.preg_replace('~\bsrc=~i', 'data-src=', $source).'</video>';
        }, $html);
        if (preg_match('~<video\b[^>]*\bid="hero-video"[^>]*\bposter="([^"]+)"~i', $html, $poster)) {
            $html = str_replace('</head>', '<link rel="preload" as="image" href="'.$poster[1].'" fetchpriority="high"></head>', $html);
        }
        if (str_contains($request->header('Accept-Encoding', ''), 'gzip') && ! $response->headers->has('Content-Encoding') && function_exists('gzencode')) {
            $html = gzencode($html, 6);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->setVary('Accept-Encoding', false);
            $response->headers->remove('Content-Length');
        }
        $original = $response->original;
        $response->setContent($html);
        $response->original = $original;

        return $response;
    }
}
