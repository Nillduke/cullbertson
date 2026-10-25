<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void { $this->replace(['/auto-quote/form.'=>'/quote-assets/auto/form.','/quote/services.'=>'/quote-assets/services/services.']); }
 public function down(): void { $this->replace(['/quote-assets/auto/form.'=>'/auto-quote/form.','/quote-assets/services/services.'=>'/quote/services.']); }
 private function replace(array $paths): void {
  foreach(DB::table('pages')->select('id','presentation')->get() as $page){
   $presentation=$page->presentation;if(!$presentation)continue;$values=json_decode($presentation,true,512,JSON_THROW_ON_ERROR);array_walk_recursive($values,function(&$value)use($paths){if(is_string($value))$value=strtr($value,$paths);});$updated=json_encode($values,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
   if($updated!==$presentation)DB::table('pages')->where('id',$page->id)->update(['presentation'=>$updated]);
  }
 }
};
