<?php

namespace App\Services;

class ResponsiveImages
{
    private ?array $manifest = null;

    public function manifest(): array
    {
        return $this->manifest ??= json_decode(@file_get_contents(public_path('assets/optimized/manifest.json')) ?: '{}', true) ?: [];
    }

    public function build(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $root = realpath(public_path('assets'));
        $file = realpath(public_path(ltrim($path ?? '', '/')));
        if (! $root || ! $file || ! str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root).'/') || str_contains($path, '/optimized/') || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            return false;
        }
        $info = @getimagesize($file);
        if (! $info || $info[0] * $info[1] > 24000000 || ! function_exists('imagewebp')) {
            return false;
        }
        $manifest = $this->manifest();
        $hash = substr(hash_file('sha256', $file), 0, 16);
        if (($manifest[$path]['hash'] ?? '') === $hash && isset($manifest[$path]['variants'][min($info[0], 768)])) {
            return true;
        }
        $source = @imagecreatefromstring(file_get_contents($file));
        if (! $source) {
            return false;
        }
        $dir = public_path('assets/optimized');
        if ((! is_dir($dir) && ! @mkdir($dir, 0755, true)) || ! is_writable($dir)) {
            imagedestroy($source);

            return false;
        }
        $variants = [];
        foreach (array_unique(array_map(fn ($w) => min($info[0], $w), [480, 768, 960, 1440, 1920])) as $width) {
            $name = $hash.'-'.$width.'.webp';
            if (is_file($dir.'/'.$name)) {
                $variants[$width] = '/assets/optimized/'.$name;

                continue;
            }
            $height = max(1, (int) round($info[1] * $width / $info[0]));
            $image = imagecreatetruecolor($width, $height);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            if (imagewebp($image, $dir.'/'.$name, 78)) {
                $variants[$width] = '/assets/optimized/'.$name;
            }
            imagedestroy($image);
        }
        imagedestroy($source);
        if (! $variants) {
            return false;
        }
        $manifest[$path] = ['hash' => $hash, 'width' => $info[0], 'height' => $info[1], 'variants' => $variants];
        ksort($manifest);
        // Merge under a lock so simultaneous admin uploads cannot lose entries.
        $handle = fopen($dir.'/manifest.json', 'c+');
        flock($handle, LOCK_EX);
        $current = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];
        $current[$path] = $manifest[$path];
        ksort($current);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($current, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $this->manifest = $current;

        return true;
    }

    public function url(string $path, int $width = 1440): string
    {
        $entry = $this->manifest()[$path] ?? null;
        if (! $entry) {
            return $path;
        }
        foreach ($entry['variants'] as $size => $url) {
            if ($size >= $width) {
                return $url;
            }
        }

        return end($entry['variants']);
    }

    public function html(string $html): string
    {
        return preg_replace_callback('~<img\b[^>]*>~i', function ($match) {
            $tag = $match[0];
            if (! preg_match('~\bsrc=["\x27]([^"\x27]+)["\x27]~i', $tag, $src)) {
                return $tag;
            }
            if (! str_starts_with($src[1], '/assets/')) {
                return $tag;
            }
            $path = parse_url(html_entity_decode($src[1]), PHP_URL_PATH);
            $entry = $this->manifest()[$path] ?? null;
            if (! $entry || preg_match('~\bsrcset\s*=~i', $tag)) {
                return $tag;
            }
            $high = preg_match('~\bfetchpriority=["\x27]high["\x27]~i', $tag);
            $attributes = preg_match('~\bdecoding\s*=~i', $tag) ? '' : ' decoding="async"';
            if (! preg_match('~\bloading\s*=~i', $tag)) {
                $attributes .= $high ? ' loading="eager"' : ' loading="lazy"';
            }
            if (! preg_match('~\bwidth\s*=~i', $tag)) {
                $attributes .= ' width="'.$entry['width'].'"';
            }
            if (! preg_match('~\bheight\s*=~i', $tag)) {
                $attributes .= ' height="'.$entry['height'].'"';
            }
            $set = [];
            foreach ($entry['variants'] as $width => $url) {
                $set[] = $url.' '.$width.'w';
            }
            $attributes .= ' srcset="'.implode(', ', $set).'" sizes="'.($high ? '(min-width: 1400px) 1280px, calc(100vw - 40px)' : '(min-width: 1000px) 420px, (min-width: 640px) 50vw, calc(100vw - 40px)').'"';
            // Preserve the original URL as the fallback and SEO image reference.
            // Modern browsers choose the appropriately sized WebP from srcset.

            return preg_replace('~\s*/?>$~', $attributes.'>', $tag);
        }, $html);
    }
}
