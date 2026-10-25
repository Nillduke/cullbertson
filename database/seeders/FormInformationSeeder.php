<?php

namespace Database\Seeders;

use App\Models\{FormDefinition, Page, SeoProfile, UrlRedirect};
use Illuminate\Database\Seeder;

class FormInformationSeeder extends Seeder
{
    public function run(): void
    {
        // A fresh install imports its original pages before installing this content.
        if (! Page::where('slug', '')->exists()) return;
        FormDefinition::firstOrCreate(['key'=>'form-information'], [
            'name'=>'Public form footer, policy links and privacy request copy',
            'definition'=>json_decode(file_get_contents(database_path('import/form-information.json')), true, flags:JSON_THROW_ON_ERROR),
        ]);
        $pages = json_decode(file_get_contents(database_path('import/form-policy-pages.json')), true, flags:JSON_THROW_ON_ERROR);
        foreach ($pages as $item) {
            $page = Page::firstOrCreate(['slug'=>$item['slug']], [
                'title'=>$item['heading'].' | Culbertson Agency', 'description'=>$item['intro'],
                'type'=>'page', 'status'=>'published', 'published_at'=>now(),
                'presentation'=>['template'=>'support', 'mainClass'=>'support-page legal-page', 'bodyClass'=>'content-page support-designed-page'],
            ]);
            if (! $page->sections()->exists()) {
                $hero = '<section class="sp-hero sp-hero-wide"><div class="sp-shell sp-hero-inner"><div class="sp-hero-copy"><nav class="sp-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">'.e($item['heading']).'</span></nav><p class="sp-eyebrow">CULBERTSON AGENCY · FORT MYERS, FLORIDA</p><h1>'.e($item['heading']).'</h1><p class="sp-lead">'.e($item['intro']).'</p></div></div></section>';
                $page->sections()->create(['name'=>'Page introduction', 'component'=>'design_html', 'position'=>0, 'content_html'=>$hero]);
                $page->sections()->create(['name'=>'Policy content', 'component'=>'design_html', 'position'=>1, 'content_html'=>'<article class="sp-shell sp-section sp-rich legal-copy">'.$item['body'].'</article>']);
                if (!empty($item['requestForm'])) $page->sections()->create(['name'=>'Privacy request form', 'component'=>'privacy_request', 'position'=>2, 'content_html'=>'']);
            }
            SeoProfile::firstOrCreate(['url_path'=>'/'.$item['slug'].'/'], ['fields'=>[
                'title'=>$page->title, 'description'=>$page->description, 'canonical'=>'/'.$item['slug'].'/',
            ], 'source'=>['kind'=>'agency-form-policy']]);
        }
        if ($privacy=Page::where('slug', 'privacy-policy-2')->first()) {
            if (!$privacy->sections()->where('component','form_privacy_supplement')->exists()) {
                $privacy->sections()->create([
                    'name'=>'Online forms and privacy choices', 'component'=>'form_privacy_supplement', 'position'=>((int)$privacy->sections()->max('position'))+1,
                    'content_html'=>file_get_contents(database_path('import/form-privacy-supplement.html')),
                ]);
            }
        }
        foreach (['privacy-policy'=>'/privacy-policy-2/', 'terms-of-service'=>'/terms-of-use/', 'ccpa-opt-out'=>'/do-not-sell-my-personal-information/', 'cookies-and-other-technology'=>'/cookie-policy/'] as $slug=>$destination) {
            if (!Page::where('slug',$slug)->exists()) UrlRedirect::firstOrCreate(['slug'=>$slug], ['destination'=>$destination,'status'=>301]);
        }
    }
}
