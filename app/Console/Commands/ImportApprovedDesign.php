<?php
namespace App\Console\Commands;

use App\Models\{Page,SiteBlock,UrlRedirect,FormDefinition};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class ImportApprovedDesign extends Command
{
    protected $signature='cms:import-design';
    protected $description='Import the approved design once into the CMS database without overwriting edits';
    public function handle(): int
    {
        if(Page::exists()) { $this->error('Content already exists. Import skipped to preserve edits.'); return 1; }
        View::addNamespace('design-import',database_path('import/views'));
        View::addNamespace('design-shared',database_path('import/shared'));
        $pages=json_decode(file_get_contents(database_path('import/content/pages.json')),true,flags:JSON_THROW_ON_ERROR);
        $catalog=json_decode(file_get_contents(database_path('import/content/articles.json')),true,flags:JSON_THROW_ON_ERROR)['articles'];
        $articles=[];foreach($catalog as $article)$articles[trim($article['url'],'/')]=$article;
        $categories=['auto-insurance'=>'Auto','boat-insurance'=>'Boat','condo-insurance'=>'Condo','flood-insurance'=>'Flood','florida-motorcycle-insurance'=>'Motorcycle','florida-rv-mobile-home-insurance'=>'Auto','golf-cart-insurance'=>'Auto','homeowners-insurance'=>'Homeowners','life-insurance'=>'Life','personal-umbrella-protection'=>'Umbrella','renters-insurance'=>'Homeowners'];
        DB::transaction(function()use($pages,$articles,$categories){
            foreach($pages as $slug=>$data){
                $article=$articles[$slug]??null;
                $type=$article?'post':(str_starts_with($slug,'insurance/')&&isset($categories[basename($slug)])?'insurance':(str_starts_with($slug,'agency-staff/')?'staff':'page'));
                $page=Page::create(['slug'=>$slug,'title'=>$data['title'],'description'=>$data['description'],'type'=>$type,'status'=>'published','featured_image'=>$article['image']??null,'categories'=>$article['categories']??[],'article_category'=>$type==='insurance'?$categories[basename($slug)]:null,'published_at'=>$article['published']??now()->subMinute(),'presentation'=>array_intersect_key($data,array_flip(['bodyClass','service','styles','scripts']))]);
                if($article)$page->update(['title'=>$article['title']]);
                $rendered=view(preg_replace('/^pages\./','design-import::',$data['view']),$data)->render();
                $rendered=str_replace(rtrim(config('app.url'),'/').'/', '/', $rendered);
                $rendered=preg_replace('~https?://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?(?=/)~i','',$rendered);
                if(preg_match('~<main\b[^>]*class="([^"]*)"~',$rendered,$mainClass))$page->update(['presentation'=>array_merge($page->presentation,['mainClass'=>$mainClass[1]])]);
                if($slug==='expert-insurance-insights-and-tips-culbertson-agency-articles')$page->update(['type'=>'blog_index']);
                preg_match('~<main\b[^>]*>(.*)</main>~s',$rendered,$match);
                if(!isset($match[1]))throw new \RuntimeException('Missing content for '.$slug);
                $dom=new \DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="cms-import-root">'.$match[1].'</div></body></html>');libxml_clear_errors();libxml_use_internal_errors($previous);
                $container=$dom->getElementById('cms-import-root');$position=0;
                foreach(iterator_to_array($container->childNodes) as $node){
                    if(!$node instanceof \DOMElement)continue;
                    $component='html';
                    if($slug===''&&$node->getAttribute('id')==='insurance-articles'){
                        $grid=$dom->getElementById('homepage-article-grid');
                        if($grid){while($grid->firstChild)$grid->removeChild($grid->firstChild);$grid->appendChild($dom->createTextNode('%%CMS_ARTICLES%%'));$component='home_articles';}
                    }
                    $heading=(new \DOMXPath($dom))->query('.//h1|.//h2',$node)->item(0);
                    $name=$heading?trim($heading->textContent):($node->getAttribute('id')?:ucfirst($node->tagName));
                    $page->sections()->create(['name'=>mb_substr($name,0,180),'position'=>$position++,'component'=>$component,'content_html'=>$dom->saveHTML($node)]);
                }
            }
            foreach(['header','footer','feedback','exit-intent','not-found'] as $key){
                $html=view('design-shared::'.$key)->render();
                $html=str_replace(rtrim(config('app.url'),'/').'/', '/', $html);
                $html=preg_replace('~https?://(?:localhost|127\.0\.0\.1)(?::[0-9]+)?(?=/)~i','',$html);
                if($key==='not-found'){preg_match('~<main\b[^>]*>.*</main>~s',$html,$matches);$html=$matches[0];}
                if($key==='exit-intent')$html=preg_replace('/data-preview="[^"]*"/','data-preview="%%EXIT_PREVIEW%%"',$html);
                SiteBlock::create(['key'=>$key,'name'=>ucwords(str_replace('-',' ',$key)),'content_html'=>$html]);
            }
            foreach(json_decode(file_get_contents(database_path('import/content/redirects.json')),true) as $slug=>$destination)UrlRedirect::create(compact('slug','destination'));
            foreach(['auto-options'=>database_path('import/auto-options.json'),'services'=>database_path('import/services.json'),'site-copy'=>database_path('import/site-copy.json'),'homepage-products'=>database_path('import/homepage-products.json')] as $key=>$path)FormDefinition::create(['key'=>$key,'name'=>ucwords(str_replace('-',' ',$key)),'definition'=>json_decode(file_get_contents($path),true,flags:JSON_THROW_ON_ERROR)]);
        });
        $this->info('Imported '.Page::count().' pages/posts and shared content into the database.');return 0;
    }
}
