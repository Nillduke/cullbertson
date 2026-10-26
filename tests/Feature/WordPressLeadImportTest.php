<?php

namespace Tests\Feature;

use App\Models\{MailSetting,QuoteRequest,ContactEnquiry,FormDefinition,User};
use App\Services\WordPressLeadImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class WordPressLeadImportTest extends TestCase
{
    use RefreshDatabase;

    private function source():PDO
    {
        $p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        foreach([
            'gf_form (id INTEGER,title TEXT)', 'gf_form_meta (form_id INTEGER,display_meta TEXT)',
            'gf_entry (id INTEGER,form_id INTEGER,status TEXT,is_read INTEGER,date_created TEXT,date_updated TEXT)',
            'gf_entry_meta (id INTEGER,entry_id INTEGER,meta_key TEXT,meta_value TEXT)',
            'gf_entry_notes (id INTEGER,entry_id INTEGER,value TEXT)',
            'e_submissions (id INTEGER,form_name TEXT,status TEXT,is_read INTEGER,created_at_gmt TEXT,updated_at_gmt TEXT,created_at TEXT,updated_at TEXT)',
            'e_submissions_values (id INTEGER,submission_id INTEGER,key TEXT,value TEXT)',
        ] as $schema)$p->exec('CREATE TABLE old_'.$schema);
        $p->exec("INSERT INTO old_gf_form VALUES (1,'Home Insurance Quote Form'),(2,'Contact Us Form')");
        $definition=json_encode(['fields'=>[['id'=>1,'type'=>'name','label'=>'Full name','inputs'=>[['id'=>'1.3','label'=>'First'],['id'=>'1.6','label'=>'Last']]],['id'=>2,'type'=>'email','label'=>'Email'],['id'=>3,'type'=>'textarea','label'=>'Message']]]);
        $q=$p->prepare('INSERT INTO old_gf_form_meta VALUES (?,?)');$q->execute([1,$definition]);$q->execute([2,$definition]);
        $p->exec("INSERT INTO old_gf_entry VALUES (1,1,'active',0,'2024-08-16 15:39:21','2024-08-16 16:00:00'),(2,2,'spam',1,'2024-08-17 15:39:21','2024-08-17 16:00:00'),(3,1,'active',0,'2024-08-18 15:39:21','2024-08-18 16:00:00')");
        $p->exec("INSERT INTO old_gf_entry_meta VALUES (1,1,'1.3','Example'),(2,1,'1.6','Visitor'),(3,1,'2','visitor@example.com'),(4,1,'partial_entry_percent','50'),(5,2,'3','<script>original message</script>'),(6,3,'2','akshay@example.com')");
        $p->exec("INSERT INTO old_gf_entry_notes VALUES (1,1,'Original note')");
        $p->exec("INSERT INTO old_e_submissions VALUES (1,'Support enquiry','new',1,'2024-09-01 12:00:00','2024-09-01 13:00:00','2024-09-01 12:00:00','2024-09-01 13:00:00')");
        $p->exec("INSERT INTO old_e_submissions_values VALUES (1,1,'name','Second Visitor'),(2,1,'email','second@example.com')");
        return $p;
    }

    public function test_import_preserves_history_excludes_test_leads_and_never_sends_notifications():void
    {
        MailSetting::current()->update(['enabled'=>true,'from_address'=>'agency@example.com','recipients'=>['admin@example.com']]);
        $importer=app(WordPressLeadImporter::class);$source=$this->source();
        $preview=$importer->import($source,'old_',true,['akshay','nillduke']);
        $this->assertSame(3,$preview['imported']);$this->assertDatabaseCount('quote_requests',0);
        $report=$importer->import($source,'old_',false,['akshay','nillduke']);
        $this->assertSame(1,$report['excluded']);$this->assertSame(1,$report['partial']);$this->assertSame(1,$report['spam']);
        $quote=QuoteRequest::firstOrFail();$this->assertSame('2024-08-16 15:39:21',$quote->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('incomplete',$quote->completion_status);$this->assertSame(50,(int)$quote->progress_percent);
        $this->assertSame('Example Visitor',$quote->payload['answers']['name']);$this->assertSame('Original note',$quote->payload['wordpress']['notes'][0]['value']);
        $this->assertDatabaseHas('contact_enquiries',['reference'=>'WP-GF-2','status'=>'spam']);
        $this->assertSame('<script>original message</script>',ContactEnquiry::where('reference','WP-GF-2')->first()->payload['message']);
        $this->assertDatabaseHas('contact_enquiries',['reference'=>'WP-EL-1','status'=>'reviewed']);
        $this->assertDatabaseCount('mail_deliveries',0);
        DB::table('quote_requests')->where('id',$quote->id)->update(['status'=>'contacted']);
        $repeat=$importer->import($source,'old_',false,['akshay','nillduke']);$this->assertSame(3,$repeat['duplicates']);$this->assertSame(0,$repeat['imported']);
        $this->assertSame('contacted',$quote->fresh()->status);$this->assertDatabaseCount('mail_deliveries',0);
        FormDefinition::create(['key'=>'services','name'=>'Services','definition'=>['services'=>['home'=>['name'=>'Home']]]]);
        FormDefinition::create(['key'=>'contact-page','name'=>'Contact','definition'=>['topics'=>[]]]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get('/admin/inbox?source=wordpress')->assertOk()->assertSee('3 submissions')->assertSee('Imported WordPress');
        $this->get('/admin/inbox?source=wordpress&status=spam')->assertOk()->assertSee('1 submission');
        $this->get('/admin/inbox/quotes/'.$quote->id)->assertOk()->assertSee('Original WordPress submission')->assertSee('Original note');
        $this->get('/admin/inbox/contacts/'.ContactEnquiry::where('reference','WP-GF-2')->value('id'))->assertOk()->assertSee('&lt;script&gt;original message&lt;/script&gt;',false)->assertDontSee('<script>original message</script>',false);
    }
}
