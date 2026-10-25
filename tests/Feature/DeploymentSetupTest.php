<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class DeploymentSetupTest extends TestCase {
 use RefreshDatabase;
 protected $seed=true;
 protected $seeder=\Database\Seeders\ContentSeeder::class;
 public function test_first_server_admin_can_be_created_interactively_without_changing_existing_admins():void {
  $this->artisan('cms:create-admin',['--email'=>'deployment-test@example.com'])->expectsQuestion('New administrator password (at least 12 characters)','deployment-test-password')->expectsQuestion('Confirm password','deployment-test-password')->assertSuccessful();
  $user=User::where('email','deployment-test@example.com')->firstOrFail();$this->assertSame(1,(int)$user->is_admin);$this->assertTrue(Hash::check('deployment-test-password',$user->password));$hash=$user->password;
  $this->artisan('cms:create-admin',['--email'=>'another@example.com'])->assertSuccessful();$this->assertSame($hash,$user->fresh()->password);$this->assertDatabaseCount('users',1);
 }
 public function test_invalid_server_admin_password_is_rejected():void {
  $this->artisan('cms:create-admin',['--email'=>'deployment-test@example.com'])->expectsQuestion('New administrator password (at least 12 characters)','short')->assertFailed();$this->assertDatabaseCount('users',0);
 }
 public function test_indexing_is_a_server_setting_and_admin_stays_private():void {
  $this->get('/')->assertSee('content="noindex, nofollow"',false);
  config(['app.search_engine_indexing'=>true]);$this->get('/')->assertSee('content="index, follow"',false);$this->get('/admin/login')->assertSee('content="noindex,nofollow"',false);
 }
}
