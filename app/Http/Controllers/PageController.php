<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\{Page,UrlRedirect};

class PageController extends Controller
{
    public function show(?string $path = null): View|RedirectResponse
    {
        $slug = trim($path ?? '', '/');
        foreach(request()->query() as $parameter=>$value)if(is_string($value) && ($queryRedirect=\App\Models\LegacyQueryRedirect::where('path',$slug)->where('parameter',$parameter)->where('value',$value)->first()))return redirect($queryRedirect->destination,$queryRedirect->status);
        if(request()->has('elementor_library'))abort(404);
        if($redirect=UrlRedirect::where('slug',$slug)->first())return redirect($redirect->destination,$redirect->status);
        if($archive=app(\App\Services\PublicArchives::class)->render($slug))return $archive;
        if(preg_match('~^(.+)/page/([1-9][0-9]*)$~D',$slug,$pagination) && Page::published()->where('slug',$pagination[1])->where('type','blog_index')->exists()){$slug=$pagination[1];request()->merge(['page'=>(int)$pagination[2]]);}
        $contentPage=Page::published()->with('sections')->where('slug',$slug)->firstOrFail();
        $data=array_merge($contentPage->presentation??[],['contentPage'=>$contentPage,'title'=>$contentPage->title,'description'=>$contentPage->description]);
        if(($contentPage->presentation['template']??null)==='support'){
            $data['styles']=['/support-pages.css?v='.filemtime(public_path('support-pages.css'))];$data['scripts']=['/support-pages.js?v='.filemtime(public_path('support-pages.js'))];
        }
        if(($contentPage->presentation['template']??null)==='html-sitemap'){
            $data['supportCopy']=\App\Models\FormDefinition::data('support-page-copy');$data['scripts']=['/support-pages.js?v='.filemtime(public_path('support-pages.js'))];$data['mapCopy']=\App\Models\FormDefinition::data('html-sitemap-copy');$data['pageGroups']=Page::published()->orderBy('title')->get()->groupBy('type')->sortBy(fn($items,$kind)=>array_search($kind,['page','insurance','staff','blog_index','post']));$data['categories']=\App\Models\Category::orderBy('name')->get();$data['tags']=\App\Models\Tag::orderBy('name')->get();$data['authors']=\App\Models\Author::orderBy('name')->get();$data['styles']=['/support-pages.css?v='.filemtime(public_path('support-pages.css'))];return view('content.site-map',$data);
        }
        if($contentPage->type==='staff' && ($member=$contentPage->teamMember)){
            $data['teamCopy']=\App\Models\FormDefinition::data('team-design');$data['otherMembers']=\App\Models\TeamMember::with('page')->where('id','!=',$member->id)->whereHas('page',fn($q)=>$q->published()->where('type','staff'))->orderBy('position')->orderBy('id')->limit(3)->get();$data['member']=$member;$data['archiveCopy']=\App\Models\FormDefinition::data('archive-copy');$data['styles']=['/archives.css?v='.filemtime(public_path('archives.css'))];$data['styles'][]='/team.css?v='.filemtime(public_path('team.css'));$data['bodyClass']='content-page team-detail-page';return view('content.team-member',$data);
        }
        if(($contentPage->presentation['template']??null)==='contact'){
            $data['contact']=\App\Models\FormDefinition::data('contact-page');
            $data['styles']=['/contact.css?v='.filemtime(public_path('contact.css'))];
            $data['scripts']=['/contact.js?v='.filemtime(public_path('contact.js'))];
            $data['bodyClass']='content-page contact-page';
            return view('content.contact',$data);
        }
        if($contentPage->type==='post'){
            $contentPage->load(['categoryTerms','tags','author']);
            $body=app(\App\Services\ArticleContent::class)->render($contentPage);
            $category=$contentPage->categoryLabels()[0]??null;
            $posts=Page::published()->with(['categoryTerms','tags'])->where('type','post')->where('id','!=',$contentPage->id)->latest('published_at')->get();
            $data['insuranceArticles']=$posts->sortBy(fn($post)=>count(array_intersect($contentPage->categoryLabels(),$post->categoryLabels()))?0:1)->take(6)->map(fn($post)=>$post->article())->values()->all();
            $data['articleCategory']=$category;
            $data['articleBody']=$body;
            $data['readingMinutes']=max(1,(int)ceil(str_word_count(strip_tags($body))/220));
            $data['blogCopy']=\App\Models\FormDefinition::data('blog-copy');
            $data['articleFeedTitle']=$data['blogCopy']['relatedTitle']??'';
            $data['heroAlt']=\App\Models\MediaAsset::where('path',$contentPage->featured_image)->value('alt')?:$contentPage->title;
            $data['styles']=['/blog-detail.css?v='.filemtime(public_path('blog-detail.css'))];
            $data['scripts']=[];$data['bodyClass']='content-page blog-detail-page';
            return view('content.article',$data);
        }
        if($contentPage->type==='insurance'){
            $category=$contentPage->article_category;
            $posts=Page::published()->with(['categoryTerms','tags'])->where('type','post')->latest('published_at')->get();
            $posts=$posts->sortBy(fn($post)=>in_array($category,$post->categoryLabels(),true)?0:1);
            $data['insuranceArticles']=$posts->take(6)->map(fn($post)=>$post->article())->values()->all();
            $data['articleCategory']=$category;
            $data['insurance']=$contentPage->insurance_content??[];
            $data['styles']=['/insurance-detail.css?v='.filemtime(public_path('insurance-detail.css'))];
            $data['scripts']=[];
            $data['bodyClass']='content-page insurance-detail-page';
            return view('content.insurance',$data);
        }
        if($contentPage->type==='blog_index'){
            $data['siteCopy']=\App\Models\FormDefinition::data('site-copy');
            $featured=Page::published()->with(['sections','categoryTerms','tags'])->where('type','post')->latest('published_at')->orderByDesc('id')->limit(4)->get();
            $data['featuredPosts']=$featured;
            $data['leadPost']=$featured->first();
            $data['listingCopy']=\App\Models\FormDefinition::data('blog-listing-copy');
            $data['leadExcerpt']='';
            if($lead=$featured->first()){
                $body=app(\App\Services\ArticleContent::class)->render($lead);
                preg_match('~<p\b[^>]*>(.*?)</p>~is',$body,$paragraph);
                $data['leadExcerpt']=\Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($paragraph[1]??$body)))),240,'...',true);
            }
            $data['posts']=Page::published()->with(['categoryTerms','tags'])->where('type','post')->whereNotIn('id',$featured->pluck('id'))->latest('published_at')->orderByDesc('id')->simplePaginate(12);
            $data['posts']->withPath('/'.$contentPage->slug.'/');
            $data['styles']=['/blog-listing.css?v='.filemtime(public_path('blog-listing.css'))];
            $data['bodyClass']='content-page blog-listing-page';
            return view('content.blog-index',$data);
        }
        return view('content.page',$data);
    }
}
