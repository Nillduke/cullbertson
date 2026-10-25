<?php
namespace App\Http\Controllers;
use App\Models\{Category,Tag,Author,TeamMember,Page,UrlRedirect,LegacyQueryRedirect,FormDefinition};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class StructureController extends Controller {
 public static function kinds():array{return ['categories'=>'Categories','tags'=>'Tags','authors'=>'Authors','team'=>'Team members','redirects'=>'URL redirects','query-redirects'=>'Legacy query redirects'];}
 private function model(string $kind):string{return match($kind){'categories'=>Category::class,'tags'=>Tag::class,'authors'=>Author::class,'team'=>TeamMember::class,'redirects'=>UrlRedirect::class,'query-redirects'=>LegacyQueryRedirect::class,default=>abort(404)};}
 public function index(Request $request){$kind=$request->input('kind','categories');$class=$this->model($kind);$query=$class::query();if($kind==='team')$query->with('page')->orderBy('position');elseif(in_array($kind,['categories','tags','authors']))$query->withCount('pages')->orderBy('name');else $query->orderBy('id');return view('admin.structure',['kind'=>$kind,'kinds'=>self::kinds(),'records'=>$query->paginate(25)->withQueryString(),'newRecord'=>new $class]);}
 public function save(Request $request,string $kind,?int $id=null){
  $class=$this->model($kind);$record=$id?$class::findOrFail($id):new $class;
  DB::transaction(function()use($kind,$request,$record,$class){
   if(in_array($kind,['categories','tags','authors'])){
    $request->merge(['slug'=>Str::slug($request->input('slug')?:$request->input('name'))]);
    $data=$request->validate(['name'=>'required|string|max:255','slug'=>['required','string','max:200',Rule::unique($record->getTable(),'slug')->ignore($record->id)],'description'=>'nullable|string|max:20000','parent_id'=>'nullable|integer|exists:categories,id']);
    $paths=$kind==='categories'?Category::all()->mapWithKeys(fn($x)=>[$x->id=>$x->path()]):($record->exists?collect([$record->id=>$record->path()]):collect());
    if($kind==='categories'){$parent=$data['parent_id']??null;$seen=[];while($parent){if((int)$parent===(int)$record->id || in_array($parent,$seen))throw ValidationException::withMessages(['parent_id'=>'A category cannot be its own ancestor.']);$seen[]=$parent;$parent=Category::find($parent)?->parent_id;}}
    else unset($data['parent_id']);
    $record->fill($data)->save();
    foreach($paths as $key=>$old){$fresh=$class::find($key);if($fresh && $old!==$fresh->path())UrlRedirect::updateOrCreate(['slug'=>$old],['destination'=>'/'.$fresh->path().'/','status'=>301]);}
   }elseif($kind==='team'){
    $request->merge(['profile_path'=>'agency-staff/'.Str::slug($request->input('slug')?:$request->input('name'))]);
    $data=$request->validate(['name'=>'required|string|max:200','role'=>'nullable|string|max:200','photo'=>['nullable','string','max:500','regex:~^/assets/[a-zA-Z0-9_./%+() -]+$~'],'biography_html'=>'nullable|string|max:200000','position'=>'required|integer|min:0|max:999','status'=>'required|in:draft,published','profile_path'=>['required','string','max:240','regex:~^agency-staff/[a-z0-9][a-z0-9-]*$~',Rule::unique('pages','slug')->ignore($record->page_id)]]);
    $page=$record->exists?$record->page:new Page;$old=$page->slug;
    $page->fill(['slug'=>$data['profile_path'],'title'=>$data['name'].' | '.(FormDefinition::data('site-copy')['brand']??''),'type'=>'staff','status'=>$data['status'],'featured_image'=>$data['photo']??null,'published_at'=>$page->published_at??now()])->save();
    $record->fill(array_intersect_key($data,array_flip(['name','role','photo','biography_html','position'])));$record->page_id=$page->id;$record->save();
    if($old && $old!==$page->slug)UrlRedirect::updateOrCreate(['slug'=>$old],['destination'=>'/'.$page->slug.'/','status'=>301]);
   }else{
    $common=['destination'=>['required','string','max:500','regex:~^/(?!/)[a-zA-Z0-9_/?=&%+.#-]*$~'],'status'=>'required|integer|in:301,302,307,308'];
    if($kind==='redirects'){$request->merge(['slug'=>trim($request->input('slug',''),'/')]);$common['slug']=['required','string','max:255','regex:~^[a-z0-9%_./-]+$~',Rule::unique('url_redirects','slug')->ignore($record->id)];}
    else{$request->merge(['path'=>trim($request->input('path',''),'/')]);$common+=['path'=>'nullable|string|max:255|regex:~^[a-z0-9%_./-]*$~','parameter'=>'required|string|max:100|regex:~^[a-zA-Z0-9_]+$~','value'=>'required|string|max:255'];}
    $data=$request->validate($common);$target=trim(parse_url($data['destination'],PHP_URL_PATH)??'','/');
    if($kind==='redirects'){
     $seen=[$data['slug']];while(true){if(in_array($target,$seen,true))throw ValidationException::withMessages(['destination'=>'This redirect would create a loop.']);$seen[]=$target;$next=UrlRedirect::where('slug',$target)->first();if(!$next)break;$target=trim(parse_url($next->destination,PHP_URL_PATH)??'','/');}
     if(preg_match('~^(admin|forms)(/|$)|^(wp-sitemap|sitemap)|^(feedback|contact-us/send|quote/submit|auto-quote/submit|up)$~',$data['slug']))throw ValidationException::withMessages(['slug'=>'This URL is reserved for an application endpoint.']);
    }else{
     $data['path']=$data['path']??'';parse_str(parse_url($data['destination'],PHP_URL_QUERY)??'',$query);
     if($target===$data['path'] && ($query[$data['parameter']]??null)===$data['value'])throw ValidationException::withMessages(['destination'=>'This redirect would create a loop.']);
     if(LegacyQueryRedirect::where('path',$data['path'])->where('parameter',$data['parameter'])->where('value',$data['value'])->when($record->exists,fn($q)=>$q->where('id','!=',$record->id))->exists())throw ValidationException::withMessages(['value'=>'This legacy query already has a redirect.']);
    }
    $record->fill($data)->save();
   }
  });
  return redirect('/admin/structure?kind='.$kind)->with('success','Database records saved. Public pages and links update immediately.');
 }
}
