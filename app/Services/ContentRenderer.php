<?php
namespace App\Services;
use App\Models\{Page,PageSection};
class ContentRenderer
{
    public function render(PageSection $section): string
    {
        if($section->component==='home_articles'){
            $articles=Page::published()->with(['categoryTerms','tags'])->where('type','post')->latest('published_at')->limit(6)->get();
            return str_replace('%%CMS_ARTICLES%%',view('content.home-article-cards',compact('articles'))->render(),$section->content_html);
        }
        return $section->content_html;
    }
}
