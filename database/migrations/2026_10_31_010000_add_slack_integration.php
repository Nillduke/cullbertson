<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  Schema::create('slack_settings',function(Blueprint $t){
   $t->id();$t->boolean('enabled')->default(false);$t->string('channel')->nullable();$t->text('webhook_url')->nullable();$t->string('website_url',500)->nullable();
   $t->boolean('notify_quotes')->default(true);$t->boolean('notify_partials')->default(false);$t->boolean('notify_contacts')->default(true);$t->boolean('notify_feedback')->default(false);$t->boolean('include_contact')->default(true);
   $t->timestamp('last_test_at')->nullable();$t->string('last_test_status')->nullable();$t->string('last_test_message',500)->nullable();$t->unsignedBigInteger('updated_by')->nullable();$t->timestamps();
  });
  Schema::create('slack_deliveries',function(Blueprint $t){
   $t->id();$t->string('dedupe_key')->unique();$t->string('kind',20);$t->unsignedBigInteger('source_id')->nullable();$t->string('event',30);$t->string('reference');$t->string('channel');$t->text('webhook_url');
   $t->string('status',20)->default('pending')->index();$t->unsignedInteger('attempts')->default(0);$t->timestamp('attempted_at')->nullable();$t->timestamp('next_attempt_at')->nullable();$t->timestamp('locked_at')->nullable();$t->timestamp('sent_at')->nullable();$t->string('last_error',500)->nullable();$t->timestamps();
  });
 }
 public function down():void {Schema::dropIfExists('slack_deliveries');Schema::dropIfExists('slack_settings');}
};
