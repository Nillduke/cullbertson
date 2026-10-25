<?php
namespace App\Http\Controllers;
use App\Models\{Page,SiteBlock,FormDefinition,QuoteRequest,FeedbackEntry};
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class CmsController extends Controller
{
    public function dashboard(){
        $counts=['Content items'=>Page::count(),'Published pages'=>Page::published()->count(),'Quote requests'=>QuoteRequest::count(),'Contact enquiries'=>\App\Models\ContactEnquiry::count()];
        $mix=Page::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total','type');
        $activity=collect();
        foreach([QuoteRequest::class=>'Quote request',\App\Models\ContactEnquiry::class=>'Contact enquiry',FeedbackEntry::class=>'Feedback'] as $model=>$kind){
            foreach($model::latest()->limit(5)->get() as $item)$activity->push(['url'=>'/admin/inbox/'.match($model){QuoteRequest::class=>'quotes',\App\Models\ContactEnquiry::class=>'contacts',default=>'feedback'}.'/'.$item->id,'reference'=>$item->reference,'kind'=>$kind,'status'=>$item->status??'received','created_at'=>$item->created_at]);
        }
        return view('admin.dashboard',compact('counts','mix')+['recentPages'=>Page::latest('updated_at')->limit(6)->get(),'activity'=>$activity->sortByDesc('created_at')->take(5),'drafts'=>Page::where('status','draft')->count(),'newRequests'=>QuoteRequest::where('status','new')->count()+\App\Models\ContactEnquiry::where('status','new')->count(),'mediaCount'=>MediaAsset::count(),'redirectCount'=>\App\Models\UrlRedirect::count()+\App\Models\LegacyQueryRedirect::count()]);
    }
    public function index(Request $request){
        $query=Page::query();
        if($request->filled('q'))$query->where(fn($q)=>$q->where('title','like','%'.$request->string('q').'%')->orWhere('slug','like','%'.$request->string('q').'%'));
        if(in_array($request->input('type'),['post','insurance','page','staff','blog_index']))$query->where('type',$request->input('type'));
        if(in_array($request->input('status'),['draft','published']))$query->where('status',$request->input('status'));
        return view('admin.index',['pages'=>$query->orderBy('type')->orderBy('title')->paginate(25)->withQueryString(),'counts'=>['Content items'=>Page::count(),'Blog posts'=>Page::where('type','post')->count(),'Quote requests'=>QuoteRequest::count(),'Feedback'=>FeedbackEntry::count()]]);
    }
    public function create(Request $request){return view('admin.edit',['page'=>new Page(['type'=>in_array($request->input('type'),['post','insurance','page','staff','blog_index'])?$request->input('type'):'page','status'=>'draft','categories'=>[]]),'sections'=>collect()]);}
    public function edit(Page $page){return view('admin.edit',['page'=>$page,'sections'=>$page->sections]);}
    public function save(Request $request,?Page $page=null){
        $page=$page??new Page;
        if(is_string($request->input('slug')))$request->merge(['slug'=>trim($request->input('slug'),'/')]);
        $input=$request->validate(['title'=>'required|string|max:255','slug'=>['nullable','string','max:255','regex:~^[a-z0-9/-]*$~',Rule::unique('pages','slug')->ignore($page->id)],'type'=>'required|in:page,post,insurance,staff,blog_index','status'=>'required|in:draft,published','description'=>'nullable|string|max:2000','featured_image'=>[Rule::requiredIf($request->input('type')==='post'&&$request->input('status')==='published'),'nullable','string','max:500','regex:~^/assets/[a-zA-Z0-9_./%+() -]+$~'],'tags'=>'nullable|string|max:1800','author_id'=>'nullable|integer|exists:authors,id','categories'=>'nullable|string|max:300','article_category'=>'nullable|string|max:80','published_at'=>'nullable|date','sections'=>'nullable|array','sections.*.name'=>'required|string|max:180','sections.*.position'=>'required|integer|min:0|max:999','sections.*.content_html'=>'nullable|string|max:2000000','new_section_name'=>'nullable|string|max:180','new_section_html'=>'nullable|string|max:2000000']);
        $slug=trim($input['slug']??'','/');
        $insurance=null;
        if($input['type']==='insurance'){
            $rules=['insurance'=>'required|array','feature_points'=>'nullable|string|max:20000','insurance.coverages'=>'nullable|array|max:50','insurance.questions'=>'nullable|array|max:50','insurance.coverages.*.title'=>'required|string|max:255','insurance.coverages.*.body'=>'required|string|max:50000','insurance.questions.*.title'=>'required|string|max:255','insurance.questions.*.body'=>'required|string|max:50000','resources_json'=>'nullable|json'];
            foreach(\App\Services\InsuranceFields::keys() as $key)$rules['insurance.'.$key]='nullable|string|max:20000';
            if($input['status']==='published')foreach(['heading','hero','intro_heading','intro_body','quote_heading','quote_url','feature_heading','feature_image','coverage_heading','faq_heading'] as $key)$rules['insurance.'.$key]='required|string|max:20000';
            foreach(['hero','feature_image'] as $key)$rules['insurance.'.$key]=($input['status']==='published'?'required':'nullable').'|string|max:500|regex:~^/assets/[a-zA-Z0-9_./%+() -]+$~';
            $rules['insurance.quote_url']=($input['status']==='published'?'required':'nullable').'|string|max:500|regex:~^/(?!/)[a-zA-Z0-9_/?=&%+.#-]+$~';
            $rules['insurance.phone_url']='nullable|string|max:100|regex:~^tel:[+0-9() -]+$~';
            $values=$request->validate($rules);
            $insurance=array_replace(\App\Services\InsuranceFields::defaults(),array_intersect_key($values['insurance'],array_flip(array_merge(\App\Services\InsuranceFields::keys(),['coverages','questions']))));
            $insurance['coverages']=array_values($insurance['coverages']??[]);$insurance['questions']=array_values($insurance['questions']??[]);
            $insurance['feature_points']=array_values(array_filter(array_map('trim',preg_split('/\R/',$values['feature_points']??''))));
            $resources=json_decode($values['resources_json']??'[]',true);
            \Illuminate\Support\Facades\Validator::make(['resources'=>$resources],['resources'=>'array|max:6','resources.*.eyebrow'=>'required|string|max:255','resources.*.title'=>'required|string|max:255','resources.*.body'=>'required|string|max:20000','resources.*.links'=>'required|array|max:15','resources.*.links.*.label'=>'required|string|max:255','resources.*.links.*.url'=>['required','string','max:500','regex:~^(?:/(?!/)|https?://|tel:)[^<>\s]+$~']])->validate();
            $insurance['resources']=$resources;
        }
        abort_if(preg_match('~^(admin|forms)(/|$)|^(feedback|quote/submit|auto-quote/submit|contact-us/send|up)$~',$slug),422,'This URL is reserved.');
        DB::transaction(function()use($input,$page,$slug,$insurance){
            $attributes=array_intersect_key($input,array_flip(['title','type','status','description','featured_image','article_category','published_at']));
            $attributes['slug']=$slug;$attributes['categories']=array_values(array_filter(array_unique(array_map('trim',explode(',',$input['categories']??'')))));
            if($attributes['status']==='published'&&empty($attributes['published_at']))$attributes['published_at']=now();
            $page->fill($attributes);if($insurance!==null)$page->insurance_content=$insurance;if(!$page->exists)$page->presentation=['bodyClass'=>'content-page','styles'=>[],'scripts'=>[]];$page->save();
            if($page->type==='post'){
                $categoryIds=[];foreach(array_filter(array_map('trim',explode(',',$input['categories']??''))) as $name)$categoryIds[]=\App\Models\Category::firstOrCreate(['name'=>$name],['slug'=>\Illuminate\Support\Str::slug($name)])->id;
                $page->categoryTerms()->sync($categoryIds);
                if(array_key_exists('tags',$input)){$tagIds=[];foreach(array_filter(array_map('trim',explode(',',$input['tags']??''))) as $name)$tagIds[]=\App\Models\Tag::firstOrCreate(['name'=>$name],['slug'=>\Illuminate\Support\Str::slug($name)])->id;$page->tags()->sync($tagIds);}
                $page->categories=$page->categoryTerms()->pluck('name')->merge($page->tags()->pluck('name'))->unique()->values()->all();
                if(array_key_exists('author_id',$input))$page->author_id=$input['author_id'];$page->save();
            }
            foreach($input['sections']??[] as $id=>$section){$record=$page->sections()->whereKey($id)->firstOrFail();$record->update(['name'=>$section['name'],'position'=>$section['position'],'content_html'=>$section['content_html']??'']);}
            if(!empty($input['new_section_name']))$page->sections()->create(['name'=>$input['new_section_name'],'position'=>($page->sections()->max('position')??-1)+1,'content_html'=>$input['new_section_html']??'']);
        });
        return redirect('/admin/pages/'.$page->id.'/edit')->with('success','Saved to the database. Published changes are live immediately.');
    }
    public function globals(){return view('admin.globals',['blocks'=>SiteBlock::all()]);}
    public function saveBlock(Request $request,SiteBlock $block){$block->update($request->validate(['content_html'=>'required|string|max:2000000']));return back()->with('success',$block->name.' saved.');}
    public function forms(){return view('admin.forms',['forms'=>FormDefinition::all()]);}
    public function saveForm(Request $request,FormDefinition $form){$data=$request->validate(['definition'=>'required|json']);$definition=json_decode($data['definition'],true);abort_unless(is_array($definition),422);if($form->key==='auto-options')foreach(['makes','states','genders','carriers','tenures'] as $key)abort_unless(is_array($definition[$key]??null),422);if($form->key==='services')abort_unless(is_array($definition['services']??null),422);$form->update(['definition'=>$definition]);return back()->with('success','Form definition saved.');}
    public function inbox(){return view('admin.inbox',['contacts'=>\App\Models\ContactEnquiry::latest()->paginate(20,['*'],'contacts'),'quotes'=>QuoteRequest::latest()->paginate(20,['*'],'quotes'),'feedback'=>FeedbackEntry::latest()->paginate(20,['*'],'feedback')]);}
    public function quoteStatus(Request $request,QuoteRequest $quote){$quote->update($request->validate(['status'=>'required|in:new,reviewed,contacted']));return back()->with('success','Request status updated.');}
    public function contactStatus(Request $request,\App\Models\ContactEnquiry $contact){$contact->update($request->validate(['status'=>'required|in:new,reviewed,contacted']));return back()->with('success','Enquiry status updated.');}
    public function mediaPicker(Request $request){
        $query=MediaAsset::query();
        if($request->filled('q'))$query->where(fn($q)=>$q->where('name','like','%'.$request->string('q').'%')->orWhere('alt','like','%'.$request->string('q').'%'));
        return response()->json($query->latest()->paginate(24,['id','name','path','alt']));
    }
    public function media(){return view('admin.media',['media'=>MediaAsset::latest()->paginate(24)]);}
    public function upload(Request $request){
        $data=$request->validate(['image'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120','alt'=>'nullable|string|max:255']);$file=$request->file('image');
        $name=bin2hex(random_bytes(12)).'.'.$file->extension();$dir=public_path('assets/media');if(!is_dir($dir))mkdir($dir,0755,true);
        $record=['path'=>'/assets/media/'.$name,'name'=>mb_substr($file->getClientOriginalName(),0,255),'alt'=>$data['alt']??'','mime'=>$file->getMimeType(),'size'=>$file->getSize()];
        $file->move($dir,$name);MediaAsset::create($record);return $request->expectsJson()?response()->json(['location'=>$record['path'],'alt'=>$record['alt']],201):back()->with('success','Image uploaded. Copy its path into the page editor.');
    }
}
