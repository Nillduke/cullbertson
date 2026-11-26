<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $pageId = DB::table('pages')->where('slug', 'auto-insurance')->value('id');
        $presentation = ['template'=>'landing','bodyClass'=>'content-page auto-landing-page','styles'=>[],'scripts'=>[]];
        $pageData = ['title'=>'Florida Auto Insurance, Reviewed by a Local Agent | Culbertson Agency','type'=>'page','status'=>'published','description'=>'A licensed Florida agent reviews your auto coverage and walks you through your options in plain English. Get a personal review from Culbertson Agency in Fort Myers.','featured_image'=>'/assets/family-auto.webp','presentation'=>json_encode($presentation),'published_at'=>$now,'updated_at'=>$now];
        if ($pageId) {
            DB::table('pages')->where('id',$pageId)->update($pageData);
        } else {
            $pageData['slug']='auto-insurance';
            $pageData['created_at']=$now;
            $pageId=DB::table('pages')->insertGetId($pageData);
        }

        $sections = [
            ['name'=>'Auto landing hero','position'=>0,'content_html'=>self::hero()],
            ['name'=>'Local proof and why review','position'=>1,'content_html'=>self::proof()],
            ['name'=>'Reviews, questions and quote CTA','position'=>2,'content_html'=>self::faq()],
        ];
        foreach ($sections as $section) {
            DB::table('page_sections')->updateOrInsert(['page_id'=>$pageId,'name'=>$section['name']],['component'=>'html','position'=>$section['position'],'content_html'=>$section['content_html'],'updated_at'=>$now,'created_at'=>$now]);
        }

        $fields=['title'=>'Florida Auto Insurance, Reviewed by a Local Agent | Culbertson Agency','description'=>'A licensed Florida agent reviews your auto coverage and walks you through your options in plain English. Get a personal review from Culbertson Agency in Fort Myers.','canonical'=>'/auto-insurance/','robots'=>'max-image-preview:large','lang'=>'en-US','og:title'=>'Florida Auto Insurance, Reviewed by a Local Agent | Culbertson Agency','og:description'=>'Get a local Florida agent to review your auto coverage and explain your options.','og:image'=>'/assets/family-auto.webp','og:type'=>'website','og:url'=>'/auto-insurance/','og:site_name'=>'Culbertson Agency','twitter:card'=>'summary_large_image','twitter:title'=>'Florida Auto Insurance | Culbertson Agency','twitter:description'=>'A licensed local agent reviews your auto coverage and explains your options.','twitter:image'=>'/assets/family-auto.webp','schemas'=>[['@context'=>'https://schema.org','@type'=>'InsuranceAgency','name'=>'Culbertson Agency','url'=>'https://culbertsonagency.com/auto-insurance/','telephone'=>'+1-239-210-7700','address'=>['@type'=>'PostalAddress','streetAddress'=>'13099 S Cleveland Ave, Suite 310','addressLocality'=>'Fort Myers','addressRegion'=>'FL','postalCode'=>'33907','addressCountry'=>'US']]],'hreflang'=>[]];
        DB::table('seo_profiles')->updateOrInsert(['url_path'=>'/auto-insurance/'],['fields'=>json_encode($fields,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'source'=>json_encode(['source'=>'New auto insurance landing page','updated'=>now()->toIso8601String()]),'updated_at'=>$now,'created_at'=>$now]);
    }

    private static function hero(): string
    {
        return <<<'HTML'
<section class="auto-lp-hero" aria-labelledby="auto-lp-title"><div class="auto-lp-shade"></div><div class="auto-lp-hero-inner"><p class="auto-lp-eyebrow">FLORIDA DRIVERS · FORT MYERS AGENCY SINCE 2008</p><p class="auto-lp-rating"><span aria-label="5 stars">★★★★★</span> <a href="https://www.google.com/search?q=Culbertson+Agency+Fort+Myers+reviews" target="_blank" rel="noopener">4.8 stars on Google (700+ reviews)</a></p><h1 id="auto-lp-title">Overpaying and underinsured?<br>Most Florida drivers are both.</h1><p class="auto-lp-intro">A licensed Florida agent from Culbertson Agency reviews your current auto policy and walks you through your options in plain English.</p><div class="auto-lp-quiz" data-auto-quiz><div class="auto-lp-quiz-top"><button type="button" class="auto-lp-quiz-back" data-quiz-back aria-label="Previous question" hidden>←</button><span data-quiz-progress>Question 1 of 3</span><div class="auto-lp-progress"><i data-quiz-bar></i></div></div><h2 data-quiz-question>Do you own or lease your vehicle?</h2><div class="auto-lp-answers" data-quiz-answers><button type="button" data-answer="own">I own my car <span aria-hidden="true">→</span></button><button type="button" data-answer="lease">I lease my car <span aria-hidden="true">→</span></button></div><div class="auto-lp-quiz-result" data-quiz-result hidden></div></div><p class="auto-lp-hero-note">No obligation. Just clear answers from a local agent.</p></div></section>
HTML;
    }

    private static function proof(): string
    {
        return <<<'HTML'
<section class="auto-lp-proof" aria-label="Why Florida drivers choose Culbertson Agency"><div class="auto-lp-local"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="6"/><path d="m8.5 13-1 9 4.5-2.5 4.5 2.5-1-9"/></svg>Local Agency · Fort Myers, Florida · Since 2008</div><div class="auto-lp-benefits"><div><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h2l2-5h10l3 5h1a1 1 0 0 1 1 1v5h-2"/><path d="M5 17H3v-5a1 1 0 0 1 1-1h16"/><circle cx="7.5" cy="17" r="2"/><circle cx="17.5" cy="17" r="2"/></svg>Auto coverage reviewed line by line</div><div><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>Real licensed Florida agents answer the phone</div><div><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>Florida drivers only</div></div><section class="auto-lp-compare"><h2>Auto renewal vs. a local review</h2><div class="auto-lp-compare-grid"><article class="auto-lp-downside"><h3><span aria-hidden="true">×</span> Accepting the renewal as is</h3><p>Your limits and deductibles never get a second look.</p><p>Changes in your drivers or vehicles can go unnoticed.</p></article><article class="auto-lp-upside"><h3><span aria-hidden="true">✓</span> Reviewed by a local agent</h3><p>A licensed Florida agent walks through your auto coverage with you.</p><p>You hear your options and decide. No obligation.</p></article></div></section></section>
HTML;
    }

    private static function faq(): string
    {
        return <<<'HTML'
<section class="auto-lp-social"><div class="auto-lp-review"><h2>Trusted by Florida drivers</h2><p class="auto-lp-stars" aria-label="5 stars">★★★★★</p><p><strong>4.8 stars on Google (700+ reviews)</strong></p><a href="https://www.google.com/search?q=Culbertson+Agency+Fort+Myers+reviews" target="_blank" rel="noopener">Read reviews from Culbertson Agency customers</a></div><div class="auto-lp-faq"><h2>Questions</h2><details><summary>How long does this take?</summary><p>Start with a quick conversation. A local agent will learn what matters to you and review your options with you.</p></details><details><summary>Does this change my current policy?</summary><p>No. A review does not change your policy. You decide whether you want to make any changes.</p></details><details><summary>Who calls me?</summary><p>A licensed Florida agent from the Culbertson Agency team in Fort Myers.</p></details><details><summary>What do I need to have ready?</summary><p>Your current policy and basic vehicle and driver details can help, but our team can guide you if you do not have everything handy.</p></details></div><a class="auto-lp-final-cta" href="/auto-quote/">See my auto options <span aria-hidden="true">→</span></a><p class="auto-lp-disclaimer">Coverage, eligibility, and availability vary by carrier and individual circumstances. A quote request does not bind coverage.</p></section>
HTML;
    }

    public function down(): void
    {
        $pageId=DB::table('pages')->where('slug','auto-insurance')->value('id');
        if($pageId){DB::table('page_sections')->where('page_id',$pageId)->whereIn('name',['Auto landing hero','Local proof and why review','Reviews, questions and quote CTA'])->delete();DB::table('pages')->where('id',$pageId)->delete();}
        DB::table('seo_profiles')->where('url_path','/auto-insurance/')->delete();
    }
};
