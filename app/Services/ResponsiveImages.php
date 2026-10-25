<?php

namespace App\Services;

class ResponsiveImages
{
    private ?array $manifest = null;

    private function orientation(string $file): int
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg'])) {
            return 1;
        }
        $value = (int) ((@exif_read_data($file))['Orientation'] ?? 1);

        return $value >= 1 && $value <= 8 ? $value : 1;
    }

    private function source(string $file): \GdImage|false
    {
        $info = @getimagesize($file);
        if (! $info) {
            return false;
        }
        $orientation = $this->orientation($file);
        $limit = ini_parse_quantity(ini_get('memory_limit') ?: '-1');
        $needed = $info[0] * $info[1] * ($orientation > 1 ? 10 : 6) + 20 * 1024 * 1024;
        if ($limit > 0 && memory_get_usage(true) + $needed > $limit) {
            return false;
        }
        $bytes = file_get_contents($file);
        if (! function_exists('exif_read_data') && str_contains(substr($bytes, 0, 65536), "Exif\0\0")) {
            return false;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return false;
        }
        if (in_array($orientation, [2, 5, 7])) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        $angle = [3 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) {
            $rotated = imagerotate($image, $angle, 0);
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

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
        $orientation = $this->orientation($file);
        if (in_array($orientation, [5, 6, 7, 8])) {
            [$info[0], $info[1]] = [$info[1], $info[0]];
        }
        $hash = hash_file('sha256', $file);
        if ($orientation > 1) {
            $hash = hash('sha256', $hash.'-orientation-'.$orientation);
        }
        $hash = substr($hash, 0, 16);
        if (($manifest[$path]['hash'] ?? '') === $hash && isset($manifest[$path]['variants'][min($info[0], 768)])) {
            return true;
        }
        $source = $this->source($file);
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

    public function buildAvif(string $path): bool
    {
        if (! function_exists('imageavif') || ! (gd_info()['AVIF Support'] ?? false) || ! $this->build($path)) {
            return false;
        }
        $entry = $this->manifest()[$path];
        $source = $this->source(public_path(ltrim($path, '/')));
        if (! $source) {
            return false;
        }
        $formats = [];
        $avifHash = substr(hash('sha256', $entry['hash'].'-avif-q40-v1'), 0, 16);
        foreach ($entry['variants'] as $width => $unused) {
            $url = '/assets/optimized/'.$avifHash.'-'.$width.'.avif';
            $file = public_path(ltrim($url, '/'));
            if (! is_file($file)) {
                $height = max(1, (int) round($entry['height'] * $width / $entry['width']));
                $image = imagecreatetruecolor($width, $height);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $entry['width'], $entry['height']);
                imageavif($image, $file, 40, 8);
                imagedestroy($image);
            }
            if (is_file($file)) {
                $formats[$width] = $url;
            }
        }
        imagedestroy($source);
        if (! $formats) {
            return false;
        }
        $handle = fopen(public_path('assets/optimized/manifest.json'), 'c+');
        flock($handle, LOCK_EX);
        $current = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];
        if (($current[$path]['hash'] ?? '') === $entry['hash']) {
            $current[$path]['avif'] = $formats;
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($current, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $this->manifest = $current;

        return true;
    }

    public function url(string $path, int $width = 1440, string $format = 'variants'): string
    {
        $entry = $this->manifest()[$path] ?? null;
        if (! $entry) {
            return $path;
        }
        foreach ($entry[$format] ?? $entry['variants'] as $size => $url) {
            if ($size >= $width) {
                return $url;
            }
        }

        $variants = $entry[$format] ?? $entry['variants'];

        return end($variants);
    }

    public function html(string $html, bool $preferAvif = false): string
    {
        return preg_replace_callback('~<img\b[^>]*>~i', function ($match) use ($preferAvif) {
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

            $tag = preg_replace('~\s*/?>$~', $attributes.'>', $tag);
            if ($preferAvif && $high && ! empty($entry['avif'])) {
                $set = [];
                foreach ($entry['avif'] as $width => $url) {
                    $set[] = $url.' '.$width.'w';
                }
                $avif = implode(', ', $set);
                $sizes = '(min-width: 1400px) 1280px, calc(100vw - 40px)';
                $tag = str_replace('<img ', '<img data-avif-srcset="'.$avif.'" ', $tag);

                return '<picture style="display:contents"><source type="image/avif" srcset="'.$avif.'" sizes="'.$sizes.'">'.$tag.'</picture>';
            }

            return $tag;
        }, $html);
    }
}
