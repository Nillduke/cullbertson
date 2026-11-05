<?php
namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Services\PageScripts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PageScriptsController extends Controller
{
 public function index(Request $request,PageScripts $scripts){
  $catalog=$scripts->catalog();$selected=$request->query('page');
  if($selected!==null)abort_unless(is_string($selected)&&isset($catalog[$selected]),404);
  $selected??=isset($catalog['/'])?'/':array_key_first($catalog);$settings=$scripts->settings();
  return response()->view('admin.page-scripts',['catalog'=>$catalog,'selected'=>$selected,'entry'=>array_replace(['enabled'=>false,'header'=>'','footer'=>''],$settings[$selected]??[]),'saved'=>$settings])->header('Cache-Control','private, no-store');
 }
 public function save(Request $request,PageScripts $scripts){
  $data=$request->validate(['page'=>['required','string',Rule::in(array_keys($scripts->catalog()))],'enabled'=>'sometimes|boolean','header'=>'nullable|string|max:200000','footer'=>'nullable|string|max:200000']);
  DB::transaction(function()use($data,$request){
   FormDefinition::firstOrCreate(['key'=>'page-scripts'],['name'=>'Page-specific header and footer scripts','definition'=>['entries'=>[]]]);
   $row=FormDefinition::where('key','page-scripts')->lockForUpdate()->firstOrFail();$definition=$row->definition;
   $definition['entries'][$data['page']]=['enabled'=>$request->boolean('enabled'),'header'=>$data['header']??'','footer'=>$data['footer']??''];$row->update(['definition'=>$definition]);
  });
  return redirect(url('/admin/page-scripts').'?'.http_build_query(['page'=>$data['page']]))->with('success','Page-specific scripts saved.');
 }
}
