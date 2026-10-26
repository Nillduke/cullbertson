<?php

namespace Tests\Feature;

use App\Models\{FormDefinition,QuoteRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteThankYouTest extends TestCase
{
    use RefreshDatabase;
    protected $seed=true;
    protected $seeder=\Database\Seeders\ContentSeeder::class;

    protected function setUp():void
    {
        parent::setUp();$this->withCredentials();$this->withSession([]);$this->withCookie(config('session.cookie'),session()->getId());
    }

    private function submission():array
    {
        return ['flow_id'=>(string)Str::uuid(),'request'=>['vehicles'=>[['year'=>'2024','make'=>'Honda','model'=>'Civic']],'drivers'=>[['month'=>'01','day'=>'15','year'=>'1990','gender'=>'Male','married'=>'No','accidents'=>'No','dui'=>'No','firstName'=>'Synthetic','lastName'=>'Visitor']],'insured'=>'No','homeowner'=>'No','address'=>['street'=>'123 Private Street','unit'=>'','city'=>'Fort Myers','state'=>'FL','zip'=>'33907'],'email'=>'private-quote@example.com','phone'=>'2025550100','consent'=>true,'sms'=>false]];
    }

    public function test_completed_quote_redirects_to_session_bound_confirmation_and_tracks_only_once():void
    {
        FormDefinition::updateOrCreate(['key'=>'global-scripts'],['name'=>'Scripts','definition'=>['header'=>'','footer'=>'','thank_you'=>'<script id="test-goal">window.testGoal=true;</script>']]);
        $input=$this->submission();$response=$this->postJson('/auto-quote/submit',$input)->assertCreated();
        $url=$response->json('redirect');$reference=$response->json('reference');
        $this->assertStringContainsString('/quote/thank-you?receipt=',$url);
        $this->assertStringNotContainsString($reference,$url);
        $this->postJson('/auto-quote/submit',$input)->assertCreated()->assertJson(['redirect'=>$url]);
        $first=$this->get($url)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow')->assertSee($reference)->assertSee('id="test-goal"',false)->assertSee('culbertson_quote_thank_you')->assertSee('quote-completion-data')->assertDontSee('private-quote@example.com')->assertDontSee('123 Private Street');
        $this->assertStringContainsString(hash('sha256',$reference),$first->getContent());
        $this->get($url)->assertOk()->assertSee($reference)->assertDontSee('id="test-goal"',false)->assertDontSee('id="quote-completion-data"',false);
        $this->get('/contact-us/')->assertOk()->assertDontSee('id="test-goal"',false);
        session()->migrate(true);$this->withCookie(config('session.cookie'),session()->getId());session()->forget('quote_receipts');
        $this->get($url)->assertRedirect(url('/get-a-quote/'));
    }

    public function test_direct_expired_and_partial_lead_visits_do_not_show_conversion_scripts():void
    {
        $this->get('/quote/thank-you/')->assertRedirect(url('/get-a-quote/'));
        $this->get('/quote/thank-you/?receipt=bad')->assertRedirect(url('/get-a-quote/'));
        $quote=QuoteRequest::create(['reference'=>'CUL-261027-ABCDEF123456','service'=>'auto','completion_status'=>'incomplete','payload'=>[]]);
        $token=str_repeat('a',48);
        $this->withSession(['quote_receipts'=>[$token=>['quote_id'=>$quote->id,'created'=>now()->timestamp]]])->get('/quote/thank-you/?receipt='.$token)->assertRedirect(url('/get-a-quote/'));
        $quote->update(['completion_status'=>'completed']);
        $this->travel(2)->days();
        $this->get('/quote/thank-you/?receipt='.$token)->assertRedirect(url('/get-a-quote/'));
        $this->travelBack();
    }
}
