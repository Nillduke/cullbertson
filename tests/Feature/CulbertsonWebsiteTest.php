<?php

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Page,UrlRedirect,FormDefinition,QuoteRequest,FeedbackEntry,SiteBlock,User};
use Tests\TestCase;

class CulbertsonWebsiteTest extends TestCase
{
    use RefreshDatabase;
    protected $seed = true;
    protected $seeder = \Database\Seeders\ContentSeeder::class;
    public function test_every_imported_page_uses_the_shared_layout(): void
    {
        $pages = Page::all()->keyBy('slug')->all();
        foreach ($pages as $path => $page) {
            $response = $this->get('/'.$path);
            $response->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, 'class="site-header pro-header"'), $path);
            $this->assertSame(1, substr_count($html, 'id="site-footer"'), $path);
            $this->assertSame(1, substr_count($html, 'id="feedback-dialog"'), $path);
            $this->assertStringNotContainsString('/design/', $html, $path);
        }
    }

    public function test_original_aliases_and_missing_page(): void
    {
        foreach (UrlRedirect::pluck('destination','slug') as $from => $to) {
            $this->get('/'.$from)->assertStatus(301)->assertRedirect($to);
        }
        $this->get('/not-a-real-culbertson-page')->assertNotFound()->assertSee('site-footer', false);
    }

    public function test_homepage_has_the_approved_sections(): void
    {
        $this->get('/')->assertOk()->assertSee('hero-video', false)
            ->assertSee('coverage-quote-form', false)->assertSee('feedback-tab', false);
    }

    public function test_article_slider_uses_existing_posts_on_each_insurance_page(): void
    {
        $catalog = Page::where('type','post')->get()->map(fn($post)=>$post->article())->all();
        $this->assertCount(92, $catalog);
        $pages = Page::all()->keyBy('slug')->all();
        foreach ($pages as $path => $page) {
            if (!str_starts_with($path, 'insurance/') || str_contains($path, 'get-a-')) continue;
            $response = $this->get('/'.$path);
            $response->assertOk()->assertSee('Latest insurance articles');
            $this->assertSame(6, substr_count($response->getContent(), 'class="insurance-article-card"'), $path);
        }
        $this->get('/')->assertDontSee('data-article-slider', false);
        $this->get('/get-a-quote/')->assertDontSee('data-article-slider', false);
        foreach ($catalog as $article) {
            $this->assertFileExists(public_path(ltrim($article['image'], '/')));
            $this->assertArrayHasKey(trim($article['url'], '/'), $pages);
        }
    }

    public function test_feedback_is_validated_and_saved_privately(): void
    {
        
        $this->postJson('/feedback', ['message' => 'bad'])->assertUnprocessable();
        $response = $this->postJson('/feedback', ['message' => 'Synthetic Laravel feedback test.', 'type' => 'suggestion', 'rating' => '5', 'email' => 'laravel-test@example.com', 'page' => '/']);
        $response->assertCreated()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('feedback_entries',['reference'=>$response->json('id')]);
    }

    public function test_all_service_forms_save_validated_requests(): void
    {
        
        $this->withoutMiddleware(ThrottleRequests::class);
        $catalog = FormDefinition::data('services')['services'];
        foreach ($catalog as $key => $service) {
            $answers = [];
            foreach ($service['questions'] as $q) {
                if ($q['type'] === 'choice') $answers[$q['id']] = $q['options'][0];
                else foreach ($q['fields'] as $f) {
                    $answers[$q['id']][$f['id']] = ($f['type'] ?? 'text') === 'number'
                        ? (string) min($f['max'], max($f['min'], in_array($f['id'], ['year', 'yearBuilt']) ? 2020 : 500))
                        : 'Example';
                }
            }
            $answers = array_merge($answers, [
                'insured' => 'No', 'start' => 'Just exploring',
                'name' => ['firstName' => 'Synthetic', 'lastName' => 'Test'],
                'address' => ['street' => '123 Example Street', 'unit' => '', 'city' => 'Fort Myers', 'state' => 'FL', 'zip' => '33907'],
                'email' => ['email' => 'laravel-test@example.com'], 'phone' => ['phone' => '2025550100'], 'consent' => true, 'sms' => false,
            ]);
            $response = $this->postJson('/quote/submit', ['service' => $key, 'answers' => $answers]);
            $response->assertCreated();
            $this->assertDatabaseHas('quote_requests',['reference'=>$response->json('reference')]);
        }
    }

    public function test_auto_validation_and_storage(): void
    {
        
        $this->postJson('/auto-quote/submit', ['request' => []])->assertUnprocessable();
        $request = [
            'vehicles' => [['year' => '2024', 'make' => 'Jeep', 'model' => 'Compass']],
            'drivers' => [['firstName' => 'Synthetic', 'lastName' => 'Test', 'month' => '01', 'day' => '15', 'year' => '1990', 'gender' => 'Male', 'married' => 'No', 'accidents' => 'No', 'dui' => 'No']],
            'insured' => 'No', 'homeowner' => 'No',
            'address' => ['street' => '123 Example Street', 'unit' => '', 'city' => 'Fort Myers', 'state' => 'FL', 'zip' => '33907'],
            'email' => 'laravel-test@example.com', 'phone' => '2025550100', 'consent' => true, 'sms' => false,
        ];
        $response = $this->postJson('/auto-quote/submit', ['request' => $request]);
        $response->assertCreated();
        $this->assertDatabaseHas('quote_requests',['reference'=>$response->json('reference')]);
    }
}
