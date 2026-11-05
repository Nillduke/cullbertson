<?php
namespace App\Services;

use App\Models\FormDefinition;

class RobotsTxt
{
    public function defaults(): string
    {
        return config('app.search_engine_indexing')
            ? "User-agent: *\nDisallow: /admin/\nDisallow: /wp-admin/\n\nSitemap: ".rtrim(config('app.url'),'/')."/wp-sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";
    }

    public function content(): string
    {
        return FormDefinition::where('key','robots-txt')->first()?->definition['content'] ?? $this->defaults();
    }
}
