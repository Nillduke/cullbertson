<?php
namespace App\Http\Controllers;
use App\Models\{Page,ContactEnquiry,FormDefinition};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\Rule;
class SupportRequestController extends Controller {
 private const PAGES=['careers'=>'recruit','documents'=>'securely-send-pictures-documents-to-us','classic'=>'classic-collector-car-insurance-in-florida'];
 public function store(Request $request,string $kind){
  $slug=self::PAGES[$kind]??abort(404);Page::published()->where('slug',$slug)->firstOrFail();
  $rules=['name'=>'required|string|max:100','email'=>'required|email:rfc|max:254','phone'=>'nullable|string|max:40','message'=>'required|string|min:10|max:3000','consent'=>'required|accepted','company_website'=>'nullable|string|max:0','files'=>($kind==='documents'?'required':'nullable').'|array|max:3','files.*'=>['required','file','max:2048','mimes:'.($kind==='careers'?'pdf':'pdf,jpg,jpeg,png,webp'),'extensions:'.($kind==='careers'?'pdf':'pdf,jpg,jpeg,png,webp')]];
  if($kind==='documents')$rules['files'].='|min:1';if($kind==='classic')$rules['files']='prohibited';
  $data=$request->validate($rules);$paths=[];$copy=FormDefinition::data('support-page-copy')['request'];
  try{$entry=DB::transaction(function()use($data,$kind,$request,$copy,&$paths){
   $entry=ContactEnquiry::create(['reference'=>'ENQ-'.strtoupper(bin2hex(random_bytes(5))),'payload'=>array_merge(array_intersect_key($data,array_flip(['name','email','phone','message'])),['topic'=>$kind==='classic'?'Collector car enquiry':($kind==='careers'?'Career enquiry':'Document submission'),'consent'=>true,'consentText'=>$copy['consent']])]);
   foreach($request->file('files',[]) as $file){$path=$file->store('enquiry-files','local');if(!$path)throw new \RuntimeException('File storage unavailable.');$paths[]=$path;$name=mb_substr(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u','_',basename($file->getClientOriginalName())),0,180);DB::table('contact_files')->insert(['contact_enquiry_id'=>$entry->id,'path'=>$path,'original_name'=>$name,'size'=>$file->getSize(),'created_at'=>now(),'updated_at'=>now()]);}
   return $entry;
  });}catch(\Throwable $error){foreach($paths as $path)Storage::disk('local')->delete($path);report($error);return back()->withErrors(['files'=>'We could not save your request. Please try again or contact our team.'])->withInput($request->except(['files','_token']));}
  return redirect('/'.$slug.'/#service-request')->with('support_reference',$entry->reference);
 }
 public function download(int $id){
  $file=DB::table('contact_files')->find($id)??abort(404);abort_unless(Storage::disk('local')->exists($file->path),404);
  return Storage::disk('local')->download($file->path,$file->original_name,['Content-Type'=>'application/octet-stream','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
 }
}
