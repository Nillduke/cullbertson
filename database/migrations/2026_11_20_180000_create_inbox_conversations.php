<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void{
  Schema::create('inbox_conversations',function(Blueprint $t){
   $t->id();$t->string('kind',20);$t->unsignedBigInteger('source_id');$t->unique(['kind','source_id']);
   $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();$t->boolean('starred')->default(false);$t->timestamp('read_at')->nullable();$t->timestamp('last_message_at')->nullable()->index();$t->timestamps();
  });
  Schema::create('inbox_messages',function(Blueprint $t){
   $t->id();$t->foreignId('conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $t->string('channel',20);$t->string('status',20);$t->text('body');$t->uuid('client_token')->unique();$t->timestamps();$t->index(['conversation_id','id']);
  });
 }
 public function down():void{Schema::dropIfExists('inbox_messages');Schema::dropIfExists('inbox_conversations');}
};
