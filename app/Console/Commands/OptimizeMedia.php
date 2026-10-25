<?php

namespace App\Console\Commands;

use App\Services\ResponsiveImages;
use Illuminate\Console\Command;

class OptimizeMedia extends Command
{
    protected $signature = 'site:optimize-media';

    protected $description = 'Build responsive WebP derivatives without changing original media or database content';

    public function handle(ResponsiveImages $images): int
    {
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path('assets'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_contains(str_replace('\\', '/', $file->getPathname()), '/optimized/')) {
                continue;
            }
            $url = '/'.str_replace('\\', '/', substr($file->getPathname(), strlen(public_path()) + 1));
            if ($images->build($url)) {
                $count++;
            }
        }
        $this->info("Responsive variants ready for {$count} images.");

        return self::SUCCESS;
    }
}
