<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('contact_files',function(Blueprint $table){$table->id();$table->foreignId('contact_enquiry_id')->constrained()->cascadeOnDelete();$table->string('path');$table->string('original_name');$table->unsignedInteger('size');$table->timestamps();});
  (new \Database\Seeders\SupportPageSeeder)->run();
 }
 public function down():void {
  foreach(DB::table('pages')->get() as $page){$p=json_decode($page->presentation,true)??[];if(!isset($p['supportDesignVersion']))continue;DB::table('page_sections')->where('page_id',$page->id)->where('component','like','design_%')->delete();DB::table('page_sections')->where('page_id',$page->id)->where('component','legacy_archive')->update(['component'=>'html']);DB::table('pages')->where('id',$page->id)->update(['presentation'=>json_encode($p['previousPresentation']??[])]);}
  Schema::dropIfExists('contact_files');
 }
};
