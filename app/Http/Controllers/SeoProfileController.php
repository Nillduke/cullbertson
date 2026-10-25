<?php
namespace App\Http\Controllers;
use App\Models\{SeoProfile,Page};
use App\Services\SeoMetadata;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class SeoProfileController extends Controller {
 public function index(Request $request){$query=SeoProfile::query();if($request->filled('q'))$query->where(fn($q)=>$q->where('url_path','like','%'.$request->string('q').'%')->orWhere('fields','like','%'.$request->string('q').'%'));return view('admin.seo-index',['profiles'=>$query->orderBy('url_path')->paginate(25)->withQueryString(),'count'=>SeoProfile::count()]);}
 public function create(Request $request){$path=SeoMetadata::path($request->string('path','/')->toString());if($record=SeoProfile::where('url_path',$path)->first())return redirect('/admin/seo/'.$record->id.'/edit');$page=Page::where('slug',trim($path,'/'))->first();return view('admin.seo-edit',['profile'=>new SeoProfile(['url_path'=>$path,'fields'=>['title'=>$page?->title??'','description'=>$page?->description??'','canonical'=>$path,'robots'=>'max-image-preview:large','lang'=>'en-US']])]);}
 public function edit(SeoProfile $profile){return view('admin.seo-edit',compact('profile'));}
 public function save(Request $request,?SeoProfile $profile=null){
  $profile??=new SeoProfile;$rules=['url_path'=>['required','string','max:700','regex:~^/[a-zA-Z0-9_/%-]*$~',Rule::unique('seo_profiles','url_path')->ignore($profile->id)],'fields'=>'required|array','fields.title'=>'required|string|max:500','fields.description'=>'nullable|string|max:3000','fields.robots'=>'required|string|max:1000','fields.lang'=>'required|string|max:35|regex:/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/','schemas_json'=>'nullable|json|max:300000','hreflang_json'=>'nullable|json|max:10000'];
  foreach(['canonical','og:image','og:url','twitter:image'] as $key)$rules['fields.'.$key]=['nullable','string','max:1500','regex:~^(?:/(?!/)[^<>\s]*|https?://[^<>\s]+)$~'];
  foreach(['og:title','og:description','og:type','og:site_name','twitter:title','twitter:description','twitter:card'] as $key)$rules['fields.'.$key]='nullable|string|max:3000';
  $input=$request->validate($rules);$schemas=json_decode($input['schemas_json']??'[]',true)??[];$alternates=json_decode($input['hreflang_json']??'[]',true)??[];
  validator(['schemas'=>$schemas,'alternates'=>$alternates],['schemas'=>'array|list|max:30','schemas.*'=>'array','alternates'=>'array|list|max:50','alternates.*.lang'=>'required|string|max:35','alternates.*.url'=>['required','string','regex:~^(?:/(?!/)[^<>\s]*|https?://[^<>\s]+)$~']])->validate();
  $allowed=['title','description','robots','lang','canonical','og:image','og:url','og:title','og:description','og:type','og:site_name','twitter:image','twitter:title','twitter:description','twitter:card'];$fields=array_intersect_key($input['fields'],array_flip($allowed));
  foreach($fields as $key=>$value)if($value===null)$fields[$key]='';$fields['canonical']=$fields['canonical']?:SeoMetadata::path($input['url_path']);
  $profile->fill(['url_path'=>SeoMetadata::path($input['url_path']),'fields'=>array_merge($profile->fields??[],$fields,['schemas'=>$schemas,'hreflang'=>$alternates])])->save();return redirect('/admin/seo/'.$profile->id.'/edit')->with('success','SEO metadata saved. The original audit fields remain preserved.');
 }
}
