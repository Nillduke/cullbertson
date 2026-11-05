<?php
namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Services\SitemapSettings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SitemapSettingsController extends Controller
{
 public function index(Request $request,SitemapSettings $settings){
  $all=$settings->entries();$entries=$all;
  if($q=trim((string)$request->query('q','')))$entries=$entries->filter(fn($row)=>str_contains(mb_strtolower($row['title'].' '.$row['path']),mb_strtolower($q)));
  if($type=$request->query('type'))$entries=$entries->where('type',$type);
  $page=max(1,$request->integer('page',1));
  return response()->view('admin.sitemap-index',['entries'=>new LengthAwarePaginator($entries->forPage($page,25)->values(),$entries->count(),25,$page,['path'=>url('/admin/sitemap'),'query'=>$request->query()]),'total'=>$all->count(),'included'=>$all->where('included',true)->count(),'types'=>$all->pluck('type')->unique()->sort()])->header('Cache-Control','private, no-store');
 }
 public function edit(Request $request,SitemapSettings $settings){
  $entry=$settings->entries()->firstWhere('path',$request->query('path'));abort_unless($entry,404);
  return response()->view('admin.sitemap-edit',['entry'=>$entry,'frequencies'=>SitemapSettings::FREQUENCIES])->header('Cache-Control','private, no-store');
 }
 public function save(Request $request,SitemapSettings $settings){
  $data=$request->validate(['path'=>'required|string|max:1000','action'=>['required',Rule::in(['save','reset'])], 'visibility'=>['required_if:action,save',Rule::in(['auto','exclude'])], 'frequency'=>['nullable',Rule::in(SitemapSettings::FREQUENCIES)],'priority'=>'nullable|numeric|between:0,1','modified'=>'nullable|date|before_or_equal:now']);
  abort_unless($settings->entries()->contains('path',$data['path']),404);
  DB::transaction(function()use($data){
   FormDefinition::firstOrCreate(['key'=>'sitemap-settings'],['name'=>'XML sitemap settings','definition'=>['entries'=>[]]]);
   $row=FormDefinition::where('key','sitemap-settings')->lockForUpdate()->firstOrFail();$definition=$row->definition;$entries=$definition['entries']??[];
   if($data['action']==='reset')unset($entries[$data['path']]);
   else $entries[$data['path']]=array_filter(['excluded'=>$data['visibility']==='exclude','frequency'=>$data['frequency']??null,'priority'=>isset($data['priority'])?number_format((float)$data['priority'],1,'.',''):null,'modified'=>empty($data['modified'])?null:\Illuminate\Support\Carbon::parse($data['modified'],'UTC')->utc()->toDateTimeString()],fn($value)=>$value!==null&&$value!==false);
   $definition['entries']=$entries;$row->update(['definition'=>$definition]);
  });
  return redirect(url('/admin/sitemap/edit').'?'.http_build_query(['path'=>$data['path']]))->with('success',$data['action']==='reset'?'Automatic sitemap settings restored.':'Sitemap settings saved and published.');
 }
}
