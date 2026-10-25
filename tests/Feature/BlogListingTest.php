<?php
namespace Tests\Feature;
use App\Models\{Page,FormDefinition};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class BlogListingTest extends TestCase {
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;
    private string $url='/expert-insurance-insights-and-tips-culbertson-agency-articles/';
    public function test_featured_section_and_pagination_cover_every_post_without_duplicates():void {
        $response=$this->get($this->url)->assertOk()->assertSee('Featured Articles')->assertSee('class="blog-lead"',false);
        $featured=$response->viewData('featuredPosts');$this->assertCount(4,$featured);
        $this->assertSame(3,substr_count($response->getContent(),'class="blog-featured-small"'));
        $seen=$featured->pluck('id')->all();$page=1;
        do {
            $response=$this->get($this->url.'?page='.$page)->assertOk();$posts=$response->viewData('posts');
            if($page>1)$response->assertDontSee('class="blog-featured"',false);
            foreach($posts as $post){$this->assertNotContains($post->id,$seen);$seen[]=$post->id;}
            $page++;
        }while($posts->hasMorePages());
        $this->assertCount(92,$seen);$this->assertEqualsCanonicalizing(Page::where('type','post')->pluck('id')->all(),$seen);
    }
    public function test_featured_articles_and_labels_update_from_database_and_hide_unpublished_content():void {
        $post=Page::create(['slug'=>'new-featured-proof','title'=>'Database featured proof','type'=>'post','status'=>'published','published_at'=>now()->subSecond(),'featured_image'=>'/assets/coverage/boat.webp','categories'=>['Boat'],'description'=>'SEO description proof']);
        $post->sections()->create(['name'=>'Body','content_html'=>'<p>Database opening excerpt proof.</p>']);
        Page::create(['slug'=>'future-feature-proof','title'=>'Hidden future proof','type'=>'post','status'=>'published','published_at'=>'2099-01-01']);
        Page::create(['slug'=>'draft-feature-proof','title'=>'Hidden draft proof','type'=>'post','status'=>'draft']);
        $this->get($this->url)->assertSee('Database featured proof')->assertSee('Database opening excerpt proof.')->assertDontSee('Hidden future proof')->assertDontSee('Hidden draft proof')->assertViewHas('leadPost',fn($lead)=>$lead->id===$post->id);
        $post->update(['title'=>'Updated featured proof','featured_image'=>'/assets/coverage/home.webp']);
        $copy=FormDefinition::where('key','blog-listing-copy')->first();$labels=$copy->definition;$labels['featuredTitle']='Database featured heading';$copy->update(['definition'=>$labels]);
        $this->get($this->url)->assertSee('Updated featured proof')->assertSee('Database featured heading')->assertSee('/assets/coverage/home.webp',false)->assertDontSee('Database featured proof');
    }
}
