<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  Schema::create('mail_settings',function(Blueprint $t){
   $t->id();$t->boolean('enabled')->default(false);$t->string('provider')->default('godaddy_local');
   $t->string('host')->default('localhost');$t->unsignedInteger('port')->default(25);$t->string('encryption')->default('none');
   $t->boolean('authentication')->default(false);$t->string('username')->nullable();$t->text('password')->nullable();
   $t->string('from_address')->nullable();$t->string('from_name')->default('Culbertson Agency');$t->string('reply_to')->nullable();
   $t->text('recipients')->nullable();$t->string('website_url')->nullable();$t->string('subject_prefix')->default('Culbertson Agency');
   $t->boolean('notify_quotes')->default(true);$t->boolean('notify_partials')->default(false);$t->boolean('notify_contacts')->default(true);
   $t->boolean('notify_feedback')->default(false);$t->boolean('include_contact')->default(true);
   $t->unsignedInteger('timeout')->default(10);$t->string('connection_status')->nullable();$t->string('connection_message',500)->nullable();
   $t->timestamp('connection_checked_at')->nullable();$t->unsignedBigInteger('updated_by')->nullable();$t->timestamps();
  });
  Schema::create('mail_deliveries',function(Blueprint $t){
   $t->id();$t->string('dedupe_key')->unique();$t->string('kind');$t->unsignedBigInteger('source_id')->nullable();
   $t->string('event');$t->string('reference')->nullable();$t->text('recipients');$t->string('subject');
   $t->string('status')->default('pending')->index();$t->unsignedInteger('attempts')->default(0);
   $t->timestamp('attempted_at')->nullable();$t->timestamp('next_attempt_at')->nullable()->index();
   $t->timestamp('locked_at')->nullable();$t->timestamp('sent_at')->nullable();$t->string('message_id')->nullable();
   $t->string('last_error',500)->nullable();$t->timestamps();
  });
 }
 public function down():void {Schema::dropIfExists('mail_deliveries');Schema::dropIfExists('mail_settings');}
};
