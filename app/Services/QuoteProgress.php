<?php
namespace App\Services;
use App\Models\{QuoteRequest,FormDefinition};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\Rule;
class QuoteProgress {
 public function key(Request $request,string $flow):string {return hash('sha256',$request->session()->getId().'|'.$flow);}
 public function service(string $service):string {
  $catalog=FormDefinition::data('services')['services']??[];
  abort_unless($service==='auto'||isset($catalog[$service]),422,'Choose a valid insurance service.');
  return $service==='auto'?'Auto':$catalog[$service]['name'];
 }
 public function validateSnapshot(Request $request,string $service):array {
  $options=FormDefinition::data('auto-options');$base=$service==='auto'?'request':'answers';
  $rules=[$base=>'required|array'];
  if($service==='auto'){
   $rules[$base]='required|array:email,phone,consent,sms,vehicles,drivers,insured,carrier,tenure,homeowner,bundleHome,address';
   $rules+=['request.email'=>'required|email:rfc|max:254','request.phone'=>'nullable|string|max:24','request.consent'=>'boolean','request.sms'=>'boolean','request.vehicles'=>'array|list|min:1|max:6','request.drivers'=>'array|list|min:1|max:6','request.vehicles.*'=>'array:year,make,model','request.vehicles.*.year'=>'nullable|integer|min:1987|max:'.max(2027,date('Y')+1),'request.vehicles.*.make'=>['nullable',Rule::in($options['makes'])],'request.vehicles.*.model'=>'nullable|string|max:100','request.drivers.*'=>'array:firstName,lastName,month,day,year,gender,married,accidents,dui','request.address'=>'array:street,unit,city,state,zip'];
   foreach(['firstName'=>80,'lastName'=>80,'month'=>2,'day'=>2,'year'=>4,'gender'=>50,'married'=>10,'accidents'=>10,'dui'=>10] as $key=>$max)$rules['request.drivers.*.'.$key]='nullable|string|max:'.$max;
   foreach(['street'=>160,'unit'=>40,'city'=>80,'state'=>10,'zip'=>5] as $key=>$max)$rules['request.address.'.$key]='nullable|string|max:'.$max;
   foreach(['insured','carrier','tenure','homeowner','bundleHome'] as $key)$rules['request.'.$key]='nullable|string|max:100';
  }else{
   $catalog=FormDefinition::data('services');$questions=$catalog['services'][$service]['questions'];foreach($catalog['common'] as $group)$questions=array_merge($questions,$group);
   $ids=array_unique(array_merge(array_column($questions,'id'),['consent','sms']));$rules['answers']='required|array:'.implode(',',$ids);
   foreach($questions as $q){$path='answers.'.$q['id'];
    if($q['type']==='choice'){$rules[$path]=['nullable',Rule::in($q['options'])];continue;}
    $rules[$path]='nullable|array:'.implode(',',array_column($q['fields'],'id'));
    foreach($q['fields'] as $f){$r=['nullable','string','max:'.($f['maxLength']??200)];
     if(($f['type']??'')==='email')$r[]='email:rfc';
     if(($f['type']??'')==='number')$r=['nullable','integer','min:'.$f['min'],'max:'.$f['max']];
     if(($f['type']??'')==='select')$r[]=Rule::in($f['id']==='state'?$options['states']:$f['options']);
     if(isset($f['pattern']))$r[]='regex:~^'.$f['pattern'].'$~D';$rules[$path.'.'.$f['id']]=$r;
    }
   }
   $rules['answers.email.email']='required|email:rfc|max:254';$rules['answers.consent']='boolean';$rules['answers.sms']='boolean';
  }
  $data=Validator::make($request->all(),$rules)->validate()[$base];
  $phone=$service==='auto'?($data['phone']??''):($data['phone']['phone']??'');
  if($phone!==''){
   $digits=preg_replace('/\D/','',$phone);if(strlen($digits)===11&&$digits[0]==='1')$digits=substr($digits,1);
   Validator::make(['phone'=>$digits],['phone'=>'regex:/^[2-9]\d{9}$/'])->validate();
   if($service==='auto')$data['phone']=$digits;else $data['phone']['phone']=$digits;
  }
  if(strtok($request->input('step'),':')==='phone')Validator::make(['phone'=>$phone,'consent'=>$data['consent']??false],['phone'=>'required','consent'=>'accepted'])->validate();
  return [$base=>$data];
 }
 public function save(Request $request,array $input):QuoteRequest {
  $service=$input['service'];$name=$this->service($service);$snapshot=$this->validateSnapshot($request,$service);$capture=$this->key($request,$input['flow_id']);
  return DB::transaction(function()use($capture,$snapshot,$input,$name,$service){
   $quote=QuoteRequest::where('capture_key',$capture)->lockForUpdate()->first();
   if($quote){abort_unless($quote->service===$service,422);if($quote->completion_status==='completed')return $quote;}
   else $quote=new QuoteRequest(['capture_key'=>$capture,'reference'=>'CUL-'.gmdate('ymd').'-'.strtoupper(bin2hex(random_bytes(6))),'service'=>$service,'completion_status'=>'incomplete','status'=>'new']);
   $notice=FormDefinition::data('quote-progress-copy');$payload=array_merge($snapshot,['reference'=>$quote->reference,'service'=>$service,'serviceName'=>$name,'createdAt'=>$quote->created_at?->toIso8601String()??gmdate('c'),'mode'=>'progress-capture','progressNotice'=>$quote->payload['progressNotice']??($notice['notice']??'')]);
   if(($snapshot[array_key_first($snapshot)]['consent']??false)===true)$payload['consentText']=$notice['consent']??'';
   $quote->fill(['payload'=>$payload,'last_step'=>$input['step'],'last_step_label'=>$input['step_label'],'progress_percent'=>min(99,$input['percent'])]);$quote->save();return $quote;
  });
 }
 public function complete(Request $request,string $service,array $payload):QuoteRequest {
  $flow=$request->input('flow_id');if($flow!==null)Validator::make(['flow_id'=>$flow],['flow_id'=>'required|uuid'])->validate();
  $capture=$flow?$this->key($request,$flow):null;
  return DB::transaction(function()use($capture,$service,$payload){
   $quote=$capture?QuoteRequest::where('capture_key',$capture)->lockForUpdate()->first():null;
   if($quote){abort_unless($quote->service===$service,422);if($quote->completion_status==='completed')return $quote;}
   else $quote=new QuoteRequest(['capture_key'=>$capture,'reference'=>'CUL-'.gmdate('ymd').'-'.strtoupper(bin2hex(random_bytes(6))),'service'=>$service]);
   $evidence=array_intersect_key($quote->payload??[],array_flip(['progressNotice','consentText']));
   $quote->fill(['payload'=>array_merge($payload,$evidence,['reference'=>$quote->reference]),'completion_status'=>'completed','progress_percent'=>100,'completed_at'=>now(),'last_step'=>'review','last_step_label'=>'Request submitted']);$quote->save();return $quote;
  });
 }
}
