<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')->where(function ($query) {
            $query->where('content_html', 'like', '%>Shop Home</a>%')
                ->orWhere('content_html', 'like', '%>Shop Auto</a>%');
        })->orderBy('id')->each(function ($section) {
            $html = str_replace(
                ['>Shop Home</a>', '>Shop Auto</a>'],
                ['>Home Insurance</a>', '>Auto Insurance</a>'],
                $section->content_html
            );
            DB::table('page_sections')->where('id', $section->id)->update(['content_html' => $html, 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
