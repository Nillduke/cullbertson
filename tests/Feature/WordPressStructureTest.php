<?php
namespace Tests\Feature;
use App\Models\{Page,Category,Tag,Author,TeamMember,LegacyQueryRedirect,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WordPressStructureTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 private function source(){return json_decode(file_get_contents(database_path('import/wordpress-structure.json')),true);}
 private function uri(string $url):string{return (parse_url($url,PHP_URL_PATH)?:'/').(($query=parse_url($url,PHP_URL_QUERY))?'?'.$query:'');}
 private function key(string $url):string{return rtrim(parse_url($url,PHP_URL_PATH)?:'/','/').'|'.(parse_url($url,PHP_URL_QUERY)??'');}
 private function admin(){ $user=User::factory()->create();$user->forceFill(['is_admin'=>true])->save();return $user; }
 public function test_import_preserves_real_terms_relationships_authors_and_team():void {
  $this->assertSame(13,Category::count());$this->assertSame(136,Tag::count());$this->assertSame(6,TeamMember::count());$this->assertSame(88,LegacyQueryRedirect::count());
  $posts=Page::where('type','post')->with(['categoryTerms','tags','author'])->get();$this->assertCount(92,$posts);
  foreach($posts as $post){$this->assertNotNull($post->wordpress_id);$this->assertSame('itmarketing',$post->author->slug);$edges=collect($this->source()['relationships'])->where('wordpress_id',$post->wordpress_id);$this->assertEqualsCanonicalizing($edges->where('taxonomy','category')->pluck('term_id')->unique()->all(),$post->categoryTerms->pluck('wordpress_id')->all());$this->assertEqualsCanonicalizing($edges->where('taxonomy','post_tag')->pluck('term_id')->unique()->all(),$post->tags->pluck('wordpress_id')->all());}
  foreach(TeamMember::all() as $member){$this->assertNotEmpty($member->biography_html);$this->assertFileExists(public_path($member->photo));}
  $tag=Tag::first();$tag->update(['name'=>'Edited tag retained']);$this->seed(\Database\Seeders\WordPressStructureSeeder::class);$this->assertSame('Edited tag retained',$tag->fresh()->name);$this->assertSame(136,Tag::count());$this->assertSame(6,TeamMember::count());
 }
 public function test_all_417_old_urls_work_with_the_same_final_paths():void {
  foreach($this->source()['expected_urls'] as $record){$uri=$this->uri($record['url']);$response=$this->get($uri);$seen=[];
   while($response->isRedirection()){$this->assertNotContains($uri,$seen,'Redirect loop '.$record['url']);$seen[]=$uri;$uri=$this->uri($response->headers->get('Location'));$response=$this->get($uri);}
   $response->assertOk();$this->assertSame($this->key($record['destination']),$this->key($uri),$record['url']);
  }
  $this->get('/?elementor_library=unknown-template')->assertNotFound();
 }
 public function test_archives_show_only_related_published_articles_with_wordpress_pagination():void {
  $tag=Tag::whereHas('pages',fn($q)=>$q->published()->where('type','post'))->first();$response=$this->get('/'.$tag->path().'/')->assertOk()->assertViewIs('content.archive');
  foreach($response->viewData('archivePosts') as $post)$this->assertTrue($post->tags->contains($tag));
  $this->get('/category/agency-news/')->assertOk()->assertSee('No articles here yet.');
  $this->get('/author/itmarketing/')->assertOk()->assertSee('/author/itmarketing/page/2/',false);$this->get('/author/itmarketing/page/2/')->assertOk();$this->get('/author/itmarketing/page/999/')->assertNotFound();
  $this->get('/expert-insurance-insights-and-tips-culbertson-agency-articles/page/2/')->assertOk()->assertSee('/expert-insurance-insights-and-tips-culbertson-agency-articles/?page=3',false);
  $this->get('/agency-staff/')->assertOk()->assertSee('Matt Pendleton')->assertSee('Victoria Ortiz');
 }
 public function test_sitemap_contains_every_old_listed_url_and_new_database_records():void {
  $this->get('/wp-sitemap.xml')->assertOk()->assertSee('sitemapindex');$paths=[];
  foreach(['posts-post-1','posts-page-1','posts-agency-staff-1','taxonomies-category-1','taxonomies-post_tag-1','users-1'] as $key){$response=$this->get('/wp-sitemap-'.$key.'.xml')->assertOk();$xml=simplexml_load_string($response->getContent());foreach($xml->url as $entry)$paths[]=$this->key((string)$entry->loc);}
  foreach($this->source()['expected_urls'] as $old)if($old['in_sitemap'])$this->assertContains($this->key($old['url']),$paths,$old['url']);
  $this->get('/sitemap_index.xml')->assertRedirect('/wp-sitemap.xml');
  $this->get('/site-map/')->assertOk()->assertViewIs('content.site-map')->assertSee('/category/agency-news/',false)->assertSee('/author/itmarketing/',false);
 }
 public function test_admin_can_manage_structure_and_new_content_appears_in_archives():void {
  $this->get('/admin/structure')->assertRedirect('/admin/login');$this->actingAs($this->admin());
  foreach(['categories','tags','authors','team','redirects','query-redirects'] as $kind)$this->get('/admin/structure?kind='.$kind)->assertOk();
  $this->post('/admin/structure/categories',['name'=>'New database category','slug'=>'new-db-category','description'=>'New category description'])->assertSessionHasNoErrors()->assertRedirect();
  $this->post('/admin/structure/tags',['name'=>'New database tag','slug'=>'new-db-tag'])->assertSessionHasNoErrors()->assertRedirect();
  $author=Author::first();
  $this->post('/admin/pages',['title'=>'Native relationships proof','slug'=>'native-relationships-proof','type'=>'post','status'=>'published','featured_image'=>'/assets/coverage/auto.webp','categories'=>'New database category','tags'=>'New database tag','author_id'=>$author->id,'new_section_name'=>'Body','new_section_html'=>'<p>Database article for new archives.</p>'])->assertSessionHasNoErrors()->assertRedirect();
  $newPost=Page::where('slug','native-relationships-proof')->firstOrFail();$this->assertCount(1,$newPost->categoryTerms);$this->assertCount(1,$newPost->tags);
  $this->get('/category/new-db-category/')->assertOk()->assertSee('Native relationships proof');$this->get('/tag/new-db-tag/')->assertOk()->assertSee('Native relationships proof');$this->get('/author/'.$author->slug.'/')->assertOk()->assertSee('Native relationships proof');
  $category=Category::where('slug','new-db-category')->first();$this->post('/admin/structure/categories/'.$category->id,['name'=>$category->name,'slug'=>'renamed-db-category'])->assertSessionHasNoErrors();$this->get('/category/new-db-category/')->assertRedirect('/category/renamed-db-category/');
  $this->post('/admin/structure/categories/'.$category->id,['name'=>$category->name,'slug'=>'renamed-db-category','parent_id'=>(string)$category->id])->assertSessionHasErrors('parent_id');
  $this->post('/admin/structure/redirects',['slug'=>'old-native-news','destination'=>'/native-relationships-proof/','status'=>301])->assertSessionHasNoErrors();$this->get('/old-native-news/')->assertRedirect('/native-relationships-proof/');
  $this->post('/admin/structure/redirects',['slug'=>'native-relationships-proof','destination'=>'/old-native-news/','status'=>301])->assertSessionHasErrors('destination');
  $this->post('/admin/structure/team',['name'=>'New team member','slug'=>'new-team-member','photo'=>'/assets/coverage/auto.webp','biography_html'=>'<p>New database team biography.</p>','position'=>10,'status'=>'published'])->assertSessionHasNoErrors();$this->get('/agency-staff/new-team-member/')->assertSee('New database team biography.');$this->get('/agency-staff/')->assertSee('New team member');
 }
}
