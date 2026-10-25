<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('seo_profiles',function(Blueprint $table){$table->id();$table->string('url_path',700)->unique();$table->json('fields');$table->json('source')->nullable();$table->timestamps();});(new \Database\Seeders\SeoProfileSeeder)->run();}
 public function down():void {Schema::dropIfExists('seo_profiles');}
};
