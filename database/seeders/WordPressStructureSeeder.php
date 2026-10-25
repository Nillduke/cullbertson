<?php
namespace Database\Seeders;
use App\Models\{Page,Category,Tag,Author,TeamMember,LegacyQueryRedirect,FormDefinition};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class WordPressStructureSeeder extends Seeder {
 public function run():void {
  $source=json_decode(file_get_contents(database_path('import/wordpress-structure.json')),true,512,JSON_THROW_ON_ERROR);
  DB::transaction(function()use($source){
   $terms=[];foreach($source['terms'] as $term){$class=$term['taxonomy']==='category'?Category::class:Tag::class;$terms[$term['taxonomy']][$term['term_id']]=$class::firstOrCreate(['wordpress_id'=>$term['term_id']],['name'=>$term['name'],'slug'=>$term['slug'],'description'=>$term['description']]);}
   foreach($source['terms'] as $term)if($term['taxonomy']==='category' && $term['parent']){$category=$terms['category'][$term['term_id']];if(!$category->parent_id)$category->update(['parent_id'=>$terms['category'][$term['parent']]->id??null]);}
   $authors=[];foreach($source['authors'] as $author)$authors[$author['wordpress_id']]=Author::firstOrCreate(['wordpress_id'=>$author['wordpress_id']],$author);
   $relationships=collect($source['relationships'])->groupBy('wordpress_id');
   foreach($source['posts'] as $record){if(Page::where('wordpress_id',$record['wordpress_id'])->exists())continue;$page=Page::where('slug',$record['slug'])->first();if(!$page || $page->wordpress_id!==null)continue;
    $page->update(['wordpress_id'=>$record['wordpress_id'],'author_id'=>$authors[$record['author_wordpress_id']]->id??null]);
    foreach($relationships[$record['wordpress_id']]??[] as $edge){$term=$terms[$edge['taxonomy']][$edge['term_id']]??null;if(!$term)continue;($edge['taxonomy']==='category'?$page->categoryTerms():$page->tags())->syncWithoutDetaching([$term->id]);}
   }
   foreach($source['team'] as $record){$page=Page::where('wordpress_id',$record['wordpress_id'])->first()??Page::where('slug',$record['slug'])->firstOrFail();TeamMember::firstOrCreate(['page_id'=>$page->id],array_intersect_key($record,array_flip(['name','photo','position','biography_html'])));}
   foreach($source['query_redirects'] as $record)LegacyQueryRedirect::firstOrCreate(array_intersect_key($record,array_flip(['path','parameter','value'])),$record);
   FormDefinition::firstOrCreate(['key'=>'archive-copy'],['name'=>'Categories, tags, authors & team archive labels','definition'=>['perPage'=>$source['per_page'],'categoryEyebrow'=>'CATEGORY','tagEyebrow'=>'TOPIC','authorEyebrow'=>'AUTHOR','intro'=>'Insurance news, guidance and ideas from Culbertson Agency.','emptyTitle'=>'No articles here yet.','emptyBody'=>'Explore our latest insurance articles for more news and practical guidance.','browse'=>'Browse all articles','readMore'=>'Read more','teamTitle'=>'Meet our team','teamIntro'=>'Your local Culbertson Agency team in Fort Myers, Florida.','teamEyebrow'=>'PEOPLE YOU CAN COUNT ON','viewProfile'=>'Meet','backToTeam'=>'Our team','relatedTitle'=>'More insurance insights']]);
   $footer=\App\Models\SiteBlock::where('key','footer')->first();
   if($footer && !str_contains($footer->content_html,'href="/agency-staff/"'))$footer->update(['content_html'=>str_replace('<a href="/about-the-culbertson-agency/">Our agency</a>','<a href="/about-the-culbertson-agency/">Our agency</a><a href="/agency-staff/">Our team</a><a href="/category/agency-news/">Agency news</a>',$footer->content_html)]);
   FormDefinition::firstOrCreate(['key'=>'html-sitemap-copy'],['name'=>'Public site map labels','definition'=>['heading'=>'Explore our website','intro'=>'Find insurance information, articles and our local team.','pages'=>'Pages','insurance'=>'Insurance','post'=>'News and articles','staff'=>'Our team','blog_index'=>'Article overview','categories'=>'Categories','tags'=>'Tags','authors'=>'Authors','team'=>'Meet our team','xml'=>'XML sitemap']]);
   if($map=Page::where('slug','site-map')->first())$map->update(['presentation'=>array_merge($map->presentation??[],['template'=>'html-sitemap'])]);
  });
  $this->call(TeamDesignSeeder::class);
 }
}
