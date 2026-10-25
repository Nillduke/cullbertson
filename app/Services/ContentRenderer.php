<?php
namespace App\Services;
use App\Models\{Page,PageSection};
class ContentRenderer
{
    public function render(PageSection $section): string
    {
        if($section->component==='privacy_request')return view('content.privacy-request')->render();
        if(str_contains($section->content_html,'id="auto-quote-wizard"') || str_contains($section->content_html,'id="service-quote-wizard"'))return app(QuoteInitialView::class)->render($section->content_html,Page::find($section->page_id)?->presentation['service']??'');
        if($section->component==='legacy_archive')return '';
        if(in_array($section->component,['design_insurance_directory','design_agency_team','design_request_form'],true)){
            $copy=\App\Models\FormDefinition::data('support-page-copy');$html=$section->content_html;
            if($section->component==='design_insurance_directory')return str_replace('%%DIRECTORY%%',view('content.support-insurance',['items'=>Page::published()->where('type','insurance')->orderBy('id')->get(),'copy'=>$copy])->render(),$html);
            if($section->component==='design_agency_team')return str_replace('%%TEAM%%',view('content.support-team',['members'=>\App\Models\TeamMember::with('page')->whereHas('page',fn($q)=>$q->published())->orderBy('position')->get(),'copy'=>$copy])->render(),$html);
            if($section->component==='design_request_form'){preg_match('/data-request-kind="(careers|documents|classic)"/',$html,$match);return str_replace('%%REQUEST_FORM%%',view('content.support-request',['kind'=>$match[1],'copy'=>$copy])->render(),$html);}
        }
        if($section->component==='home_articles'){
            $articles=Page::published()->with(['categoryTerms','tags'])->where('type','post')->latest('published_at')->limit(6)->get();
            return str_replace('%%CMS_ARTICLES%%',view('content.home-article-cards',compact('articles'))->render(),$section->content_html);
        }
        return $section->content_html;
    }
}
