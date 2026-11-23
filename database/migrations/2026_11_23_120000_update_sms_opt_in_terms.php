<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $item = collect(json_decode(file_get_contents(database_path('import/form-policy-pages.json')), true, flags: JSON_THROW_ON_ERROR))
            ->firstWhere('slug', 'sms-terms-of-service');
        if (! $item) return;

        $page = Page::where('slug', 'sms-terms-of-service')->first();
        if (! $page) return;

        $page->sections()->where('name', 'Policy content')->update([
            'content_html' => '<article class="sp-shell sp-section sp-rich legal-copy">'.$item['body'].'</article>',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the current customer-facing SMS terms when rolling back application code.
    }
};
