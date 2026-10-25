<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use DateTimeImmutable;

class QuoteValidator
{
private function textField(array $input,string $name,int $max,bool $required=true):string{$v=$input[$name]??'';if(!is_string($v)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v))$this->reject(422,['error'=>'Please check your '.preg_replace('/([A-Z])/',' $1',$name).'.']);$v=trim($v);if(($required&&$v==='')||mb_strlen($v)>$max)$this->reject(422,['error'=>'Please check your '.preg_replace('/([A-Z])/',' $1',$name).'.']);return $v;}
private function enumField(array $input,string $name,array $choices):string{$v=$input[$name]??null;if(!is_string($v)||!in_array($v,$choices,true))$this->reject(422,['error'=>'Please check your '.$name.' selection.']);return $v;}

private function cleanText(mixed $value,string $label,int $max=200,bool $required=true):string {
    if (!is_string($value)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value)) $this->reject(422,['error'=>'Please check '.$label.'.']);
    $value=trim($value);
    if (($required&&$value==='')||mb_strlen($value)>$max) $this->reject(422,['error'=>'Please check '.$label.'.']);
    return $value;
}
private function active(array $question,array $answers):bool {
    foreach ($question['when']??[] as $id=>$values) if (!in_array($answers[$id]??null,$values,true)) return false;
    return true;
}

public function auto(array $body): array
{
$options=\App\Models\FormDefinition::data('auto-options');if(!is_array($options))$this->reject(503,['error'=>'The form is temporarily unavailable.']);$request=$body['request']??null;if(!is_array($request))$this->reject(422,['error'=>'Please complete your quote details.']);
$clean=['vehicles'=>[],'drivers'=>[]];foreach(['vehicles','drivers'] as $name){if(!is_array($request[$name]??null)||!array_is_list($request[$name])||count($request[$name])<1||count($request[$name])>6)$this->reject(422,['error'=>'Include between one and six '.$name.'.']);}
foreach($request['vehicles'] as $v){if(!is_array($v))$this->reject(422,['error'=>'Please check your vehicles.']);$year=$this->textField($v,'year',4);if(!preg_match('/^\d{4}$/',$year)||(int)$year<1987||(int)$year>max(2027,(int)date('Y')+1))$this->reject(422,['error'=>'Please check the vehicle year.']);$make=$this->enumField($v,'make',$options['makes']);$model=$this->textField($v,'model',100);$clean['vehicles'][]=['year'=>$year,'make'=>$make,'model'=>$model];}
foreach($request['drivers'] as $d){if(!is_array($d))$this->reject(422,['error'=>'Please check your drivers.']);$year=$this->textField($d,'year',4);$month=$this->textField($d,'month',2);$day=$this->textField($d,'day',2);if(!ctype_digit($year)||!ctype_digit($month)||!ctype_digit($day)||(int)$year<1900||!checkdate((int)$month,(int)$day,(int)$year))$this->reject(422,['error'=>'Please enter a real date of birth.']);$dob=new DateTimeImmutable(sprintf('%04d-%02d-%02d',(int)$year,(int)$month,(int)$day));$today=new DateTimeImmutable('today');if($dob>$today||$dob->diff($today)->y<18)$this->reject(422,['error'=>'This quote form is for adult drivers aged 18 or older.']);$clean['drivers'][]=['firstName'=>$this->textField($d,'firstName',80),'lastName'=>$this->textField($d,'lastName',80),'dateOfBirth'=>$dob->format('Y-m-d'),'gender'=>$this->enumField($d,'gender',$options['genders']),'married'=>$this->enumField($d,'married',['Yes','No']),'accidents'=>$this->enumField($d,'accidents',['Yes','No']),'dui'=>$this->enumField($d,'dui',['Yes','No'])];}
$clean['insured']=$this->enumField($request,'insured',['Yes','No']);if($clean['insured']==='Yes'){$clean['carrier']=$this->enumField($request,'carrier',$options['carriers']);$clean['tenure']=$this->enumField($request,'tenure',$options['tenures']);}else{$clean['carrier']='';$clean['tenure']='';}
$clean['homeowner']=$this->enumField($request,'homeowner',['Yes','No']);$clean['bundleHome']=$clean['homeowner']==='Yes'?$this->enumField($request,'bundleHome',['Yes','No']):'No';
$address=$request['address']??null;if(!is_array($address))$this->reject(422,['error'=>'Please check your address.']);$clean['address']=['street'=>$this->textField($address,'street',160),'unit'=>$this->textField($address,'unit',40,false),'city'=>$this->textField($address,'city',80),'state'=>$this->enumField($address,'state',$options['states']),'zip'=>$this->textField($address,'zip',5)];if(!preg_match('/^\d{5}$/',$clean['address']['zip']))$this->reject(422,['error'=>'Enter a five-digit ZIP code.']);
$clean['email']=$this->textField($request,'email',254);if(!filter_var($clean['email'],FILTER_VALIDATE_EMAIL))$this->reject(422,['error'=>'Enter a valid email address.']);$phone=$this->textField($request,'phone',24);$digits=preg_replace('/\D/','',$phone);if(strlen($digits)===11&&$digits[0]==='1')$digits=substr($digits,1);if(!preg_match('/^[2-9]\d{9}$/',$digits))$this->reject(422,['error'=>'Enter a valid 10-digit US phone number.']);$clean['phone']=$digits;
if(($request['consent']??false)!==true)$this->reject(422,['error'=>'Please acknowledge contact about your quote request.']);if(isset($request['sms'])&&!is_bool($request['sms']))$this->reject(422,['error'=>'Please check the text-message preference.']);$clean['contactConsent']=true;$clean['smsRequested']=$request['sms']??false;

return $clean;
}
public function service(array $body): array
{
$catalog=\App\Models\FormDefinition::data('services');
$options=\App\Models\FormDefinition::data('auto-options');
if (!is_array($catalog)||!is_array($options)) $this->reject(503,['error'=>'The form is temporarily unavailable.']);
$service=$body['service']??null;
if (!is_string($service)||!isset($catalog['services'][$service])) $this->reject(422,['error'=>'Please choose an insurance service.']);
$answers=$body['answers']??null;
if (!is_array($answers)) $this->reject(422,['error'=>'Please complete your quote details.']);
$questions=$catalog['services'][$service]['questions'];
$questions[]=['id'=>'insured','type'=>'choice','title'=>'current insurance','options'=>['Yes','No','Not sure']];
$questions[]=['id'=>'carrier','type'=>'fields','when'=>['insured'=>['Yes']],'fields'=>[['id'=>'carrier','label'=>'current insurer','maxLength'=>100,'required'=>false]]];
$questions[]=['id'=>'start','type'=>'choice','title'=>'coverage start','options'=>['As soon as possible','Within 30 days','In 1-2 months','At my renewal','Just exploring']];
$questions[]=['id'=>'name','type'=>'fields','fields'=>[['id'=>'firstName','label'=>'first name','maxLength'=>80],['id'=>'lastName','label'=>'last name','maxLength'=>80]]];
$questions[]=['id'=>'address','type'=>'fields','fields'=>[['id'=>'street','label'=>'street address','maxLength'=>160],['id'=>'unit','label'=>'unit','maxLength'=>40,'required'=>false],['id'=>'city','label'=>'city','maxLength'=>80],['id'=>'state','label'=>'state','type'=>'select','options'=>$options['states']],['id'=>'zip','label'=>'ZIP code','pattern'=>'[0-9]{5}','maxLength'=>5]]];
$questions[]=['id'=>'email','type'=>'fields','fields'=>[['id'=>'email','label'=>'email address','type'=>'email','maxLength'=>254]]];
$questions[]=['id'=>'phone','type'=>'fields','fields'=>[['id'=>'phone','label'=>'phone number','type'=>'tel','maxLength'=>24]]];
$clean=[];
foreach ($questions as $q) {
    if (!$this->active($q,$answers)) continue;
    $value=$answers[$q['id']]??null;
    if ($q['type']==='choice') {
        if (!is_string($value)||!in_array($value,$q['options'],true)) $this->reject(422,['error'=>'Please check your '.$q['title'].' selection.']);
        $clean[$q['id']]=$value; continue;
    }
    if (!is_array($value)) $this->reject(422,['error'=>'Please complete your '.$q['id'].' details.']);
    $clean[$q['id']]=[];
    foreach ($q['fields'] as $f) {
        $v=$this->cleanText($value[$f['id']]??'',$f['label'],$f['maxLength']??200,$f['required']??true);
        if ($v==='') { $clean[$q['id']][$f['id']]=''; continue; }
        $type=$f['type']??'text';
        if ($type==='number'&&(!preg_match('/^\d+$/',$v)||(float)$v<$f['min']||(float)$v>$f['max'])) $this->reject(422,['error'=>'Please check '.$f['label'].'.']);
        if ($type==='select'&&!in_array($v,$f['options'],true)) $this->reject(422,['error'=>'Please check '.$f['label'].'.']);
        if (isset($f['pattern'])&&!preg_match('/^'.$f['pattern'].'$/D',$v)) $this->reject(422,['error'=>'Please check '.$f['label'].'.']);
        if ($type==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL)) $this->reject(422,['error'=>'Enter a valid email address.']);
        if ($type==='tel') { $digits=preg_replace('/\D/','',$v); if (strlen($digits)===11&&$digits[0]==='1') $digits=substr($digits,1); if (!preg_match('/^[2-9]\d{9}$/D',$digits)) $this->reject(422,['error'=>'Enter a valid 10-digit US phone number.']); $v=$digits; }
        $clean[$q['id']][$f['id']]=$v;
    }
}
if (($answers['consent']??false)!==true) $this->reject(422,['error'=>'Please acknowledge contact about this quote request.']);
if (isset($answers['sms'])&&!is_bool($answers['sms'])) $this->reject(422,['error'=>'Please check the text-message preference.']);
$clean['consent']=true;$clean['sms']=$answers['sms']??false;

return ['service'=>$service,'serviceName'=>$catalog['services'][$service]['name'],'answers'=>$clean];
}
private function reject(int $status, array $data): never
{ throw new HttpResponseException(response()->json($data,$status)); }
}
