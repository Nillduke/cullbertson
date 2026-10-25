<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  foreach(['categories','tags','authors'] as $name)Schema::create($name,function(Blueprint $t)use($name){$t->id();$t->unsignedBigInteger('wordpress_id')->nullable()->unique();$t->string('name');$t->string('slug')->unique();$t->text('description')->nullable();if($name==='categories')$t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();$t->timestamps();});
  Schema::table('pages',function(Blueprint $t){$t->unsignedBigInteger('wordpress_id')->nullable()->unique();$t->foreignId('author_id')->nullable()->constrained()->nullOnDelete();});
  foreach(['category_page'=>'category_id','page_tag'=>'tag_id'] as $name=>$column)Schema::create($name,function(Blueprint $t)use($column){$t->foreignId('page_id')->constrained()->cascadeOnDelete();$t->foreignId($column)->constrained($column==='category_id'?'categories':'tags')->cascadeOnDelete();$t->primary(['page_id',$column]);});
  Schema::create('team_members',function(Blueprint $t){$t->id();$t->foreignId('page_id')->unique()->constrained()->cascadeOnDelete();$t->string('name');$t->string('role')->nullable();$t->string('photo')->nullable();$t->longText('biography_html')->nullable();$t->unsignedInteger('position')->default(0);$t->timestamps();});
  Schema::create('legacy_query_redirects',function(Blueprint $t){$t->id();$t->string('path')->default('');$t->string('parameter');$t->string('value');$t->string('destination');$t->unsignedSmallInteger('status')->default(301);$t->unique(['path','parameter','value']);$t->timestamps();});
  Schema::table('url_redirects',fn(Blueprint $t)=>$t->unsignedSmallInteger('status')->default(301));
 }
 public function down():void {Schema::table('url_redirects',fn(Blueprint $t)=>$t->dropColumn('status'));foreach(['legacy_query_redirects','team_members','category_page','page_tag'] as $name)Schema::dropIfExists($name);Schema::table('pages',function(Blueprint $t){$t->dropConstrainedForeignId('author_id');$t->dropColumn('wordpress_id');});foreach(['categories','tags','authors'] as $name)Schema::dropIfExists($name);}
};
