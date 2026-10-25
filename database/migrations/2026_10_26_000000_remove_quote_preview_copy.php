<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')->where(function ($query) {
            $query->where('content_html', 'like', '%Local design preview%')
                ->orWhere('content_html', 'like', '%This local preview saves requests on this computer.%');
        })->orderBy('id')->each(function ($section) {
            $html = str_replace(
                ['<span class="quote-preview-label">Local design preview</span>', 'This local preview saves requests on this computer. Requests are not sent to an insurer.'],
                ['', 'Your request is saved for our agency team to review. Coverage is not bound.'],
                $section->content_html
            );
            DB::table('page_sections')->where('id', $section->id)->update(['content_html' => $html, 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
