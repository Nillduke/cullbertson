<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('inbox_conversations',fn(Blueprint $t)=>$t->boolean('email_dnd')->default(false));
  Schema::create('inbox_tags',function(Blueprint $t){$t->id();$t->string('name',40);$t->string('slug',64)->unique();$t->timestamps();});
  Schema::create('inbox_conversation_tag',function(Blueprint $t){$t->foreignId('inbox_conversation_id')->constrained()->cascadeOnDelete();$t->foreignId('inbox_tag_id')->constrained()->cascadeOnDelete();$t->primary(['inbox_conversation_id','inbox_tag_id']);});
  Schema::create('inbox_conversation_followers',function(Blueprint $t){$t->foreignId('inbox_conversation_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->primary(['inbox_conversation_id','user_id']);});
 }
 public function down():void{Schema::dropIfExists('inbox_conversation_followers');Schema::dropIfExists('inbox_conversation_tag');Schema::dropIfExists('inbox_tags');Schema::table('inbox_conversations',fn(Blueprint $t)=>$t->dropColumn('email_dnd'));}
};
