<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('twilio_settings',function(Blueprint $t){$t->id();$t->boolean('enabled')->default(false);$t->boolean('sms_enabled')->default(false);$t->boolean('voice_enabled')->default(false);$t->string('account_sid',34)->nullable();$t->text('auth_token')->nullable();$t->string('from_number',20)->nullable();$t->string('messaging_service_sid',34)->nullable();$t->string('voice_agent_number',20)->nullable();$t->string('connection_status',20)->nullable();$t->string('connection_message',255)->nullable();$t->timestamp('connection_checked_at')->nullable();$t->timestamps();});
  Schema::table('inbox_conversations',function(Blueprint $t){$t->boolean('sms_dnd')->default(false);$t->boolean('voice_dnd')->default(false);});
  Schema::create('twilio_deliveries',function(Blueprint $t){$t->id();$t->foreignId('inbox_message_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('inbox_conversation_id')->constrained()->cascadeOnDelete();$t->string('kind',12);$t->string('direction',12);$t->string('provider_sid',34)->nullable()->unique();$t->string('customer_call_sid',34)->nullable()->unique();$t->string('from_number',20);$t->string('to_number',20);$t->string('agent_number',20)->nullable();$t->string('status',30)->default('pending');$t->string('agent_status',30)->nullable();$t->string('customer_status',30)->nullable();$t->unsignedInteger('duration')->nullable();$t->unsignedInteger('error_code')->nullable();$t->string('last_error',255)->nullable();$t->timestamp('locked_at')->nullable();$t->timestamps();$t->unique('inbox_message_id');$t->index(['to_number','direction']);});
 }
 public function down():void{Schema::dropIfExists('twilio_deliveries');Schema::table('inbox_conversations',fn(Blueprint $t)=>$t->dropColumn(['sms_dnd','voice_dnd']));Schema::dropIfExists('twilio_settings');}
};
