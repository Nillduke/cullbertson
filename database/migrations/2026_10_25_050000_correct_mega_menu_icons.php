<?php

use App\Services\MegaMenuIcons;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $header = DB::table('site_blocks')->where('key', 'header')->first();
        if ($header) {
            DB::table('site_blocks')->where('id', $header->id)->update(['content_html' => MegaMenuIcons::apply($header->content_html), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Cosmetic corrections remain editable in the shared-header content editor.
    }
};
