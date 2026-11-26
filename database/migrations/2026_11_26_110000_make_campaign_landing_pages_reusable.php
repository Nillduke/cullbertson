<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $pageId = DB::table('pages')->where('slug', 'auto-insurance')->value('id');
        if (!$pageId) return;

        $proof = DB::table('page_sections')->where('page_id', $pageId)->where('name', 'Local proof and why review')->first();
        if (!$proof || !preg_match('~<section class="auto-lp-compare">.*?</section>~s', $proof->content_html, $match)) return;

        $proofHtml = str_replace($match[0], '', $proof->content_html);
        DB::table('page_sections')->where('id', $proof->id)->update(['content_html' => $proofHtml, 'updated_at' => now()]);
        DB::table('page_sections')->where('page_id', $pageId)->where('position', '>=', 2)->increment('position', 2);

        $now = now();
        DB::table('page_sections')->insert([
            ['page_id'=>$pageId,'name'=>'Image before comparison','component'=>'html','position'=>2,'content_html'=>'<section class="auto-lp-image-section"><img src="/assets/family-auto.webp" alt="Florida family reviewing their auto insurance options"></section>','created_at'=>$now,'updated_at'=>$now],
            ['page_id'=>$pageId,'name'=>'Auto renewal comparison','component'=>'html','position'=>3,'content_html'=>$match[0],'created_at'=>$now,'updated_at'=>$now],
        ]);
    }

    public function down(): void
    {
        $pageId = DB::table('pages')->where('slug', 'auto-insurance')->value('id');
        if (!$pageId) return;
        $proof = DB::table('page_sections')->where('page_id', $pageId)->where('name', 'Local proof and why review')->first();
        $comparison = DB::table('page_sections')->where('page_id', $pageId)->where('name', 'Auto renewal comparison')->first();
        if ($proof && $comparison) {
            $html = preg_replace('~</section>\s*$~', $comparison->content_html.'</section>', $proof->content_html);
            DB::table('page_sections')->where('id', $proof->id)->update(['content_html'=>$html,'updated_at'=>now()]);
        }
        DB::table('page_sections')->where('page_id', $pageId)->whereIn('name', ['Image before comparison','Auto renewal comparison'])->delete();
        DB::table('page_sections')->where('page_id', $pageId)->where('position', '>', 1)->decrement('position', 2);
    }
};
