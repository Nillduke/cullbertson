<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('quote_requests',function(Blueprint $table){$table->string('completion_status')->default('completed')->index();$table->string('capture_key',64)->nullable()->unique();$table->string('last_step',100)->nullable();$table->string('last_step_label')->nullable();$table->unsignedTinyInteger('progress_percent')->default(100);$table->timestamp('completed_at')->nullable();});
  DB::table('quote_requests')->update(['completed_at'=>DB::raw('created_at')]);
  DB::table('form_definitions')->insertOrIgnore(['key'=>'quote-progress-copy','name'=>'Quote lead capture & progress labels','definition'=>file_get_contents(database_path('import/quote-progress-copy.json')),'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down():void {Schema::table('quote_requests',function(Blueprint $table){$table->dropUnique(['capture_key']);$table->dropIndex(['completion_status']);$table->dropColumn(['completion_status','capture_key','last_step','last_step_label','progress_percent','completed_at']);});}
};
