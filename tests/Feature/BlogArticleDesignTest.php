<?php
namespace Tests\Feature;
use App\Models\{Page,FormDefinition,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BlogArticleDesignTest extends TestCase {
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;
    public function test_all_existing_posts_share_article_design_and_keep_editorial_content():void {
        $posts=Page::where('type','post')->get();$this->assertCount(92,$posts);
        foreach($posts as $post){
            $response=$this->get('/'.$post->slug)->assertOk()->assertViewIs('content.article')->assertSee('data-template="shared-blog-article"',false)->assertSee($post->title)->assertSee($post->published_at->toDateString())->assertSee('blog-detail.css',false);
            $html=$response->getContent();$dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($dom);
            $this->assertSame(1,$xpath->query('//main//h1')->length,$post->slug);
            $this->assertSame(1,$xpath->query('//figure[contains(@class,"blog-hero")]/img')->length,$post->slug);
            $body=app(\App\Services\ArticleContent::class)->render($post);
            $this->assertStringNotContainsString('content-page-heading',$body);
            // Every imported article paragraph survives the wrapper/header cleanup.
            foreach($post->sections as $section){$source=new \DOMDocument();@$source->loadHTML('<?xml encoding="UTF-8">'.$section->content_html);$q=new \DOMXPath($source);foreach($q->query('//article//p') as $paragraph){$text=preg_replace('/\s+/u',' ',trim($paragraph->textContent));if(mb_strlen($text)>25)$this->assertStringContainsString($text,preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($body))),$post->slug);}}
            $related=$response->viewData('insuranceArticles');$this->assertCount(6,$related);$this->assertNotContains($post->id,array_column($related,'id'));
        }
    }
    public function test_new_post_inherits_design_and_database_edits_change_header_body_and_labels():void {
        $admin=User::factory()->create();$admin->forceFill(['is_admin'=>true])->save();$this->actingAs($admin);
        $this->post('/admin/pages',['title'=>'New database article design','slug'=>'new-article-design','type'=>'post','status'=>'published','published_at'=>'2026-10-20T10:00','featured_image'=>'/assets/coverage/boat.webp','categories'=>'Boat','new_section_name'=>'Article body','new_section_html'=>'<p>Database-only opening paragraph.</p><h2>Database body heading</h2><p>Another live paragraph.</p>'])->assertSessionHasNoErrors()->assertRedirect();
        $page=Page::where('slug','new-article-design')->firstOrFail();
        $this->get('/'.$page->slug)->assertOk()->assertViewIs('content.article')->assertSee('New database article design')->assertSee('Database-only opening paragraph.')->assertSee('2026-10-20')->assertSee('/assets/coverage/boat.webp',false);
        $page->update(['title'=>'Updated database headline','featured_image'=>'/assets/coverage/home.webp']);$page->sections()->first()->update(['content_html'=>'<p>Updated database article body.</p>']);
        $copy=FormDefinition::where('key','blog-copy')->first();$data=$copy->definition;$data['back']='Database back label';$data['relatedTitle']='Database related heading';$copy->update(['definition'=>$data]);
        $this->get('/'.$page->slug)->assertSee('Updated database headline')->assertSee('Updated database article body.')->assertSee('/assets/coverage/home.webp',false)->assertSee('Database back label')->assertSee('Database related heading')->assertDontSee('Database-only opening paragraph.');
        $page->update(['status'=>'draft']);$this->get('/'.$page->slug)->assertNotFound();
    }
}
