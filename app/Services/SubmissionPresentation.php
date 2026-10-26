<?php
namespace App\Services;
use App\Models\FormDefinition;
use Illuminate\Support\Str;
class SubmissionPresentation
{
 private ?array $catalog=null;
 private ?array $contactCopy=null;
 public function summarize(object $record,string $kind):array {
  $payload=is_array($record->payload)?$record->payload:json_decode($record->payload,true)??[];
  $answers=$payload['answers']??$payload['request']??$payload;
  $person=$answers['name']??($answers['drivers'][0]??[]);
  $name=is_array($person)?trim(($person['firstName']??'').' '.($person['lastName']??'')):(string)$person;
  $email=$answers['email']??'';$phone=$answers['phone']??'';
  $email=is_array($email)?($email['email']??''):$email;$phone=is_array($phone)?($phone['phone']??''):$phone;
  $service=$record->service??($payload['service']??'');
  $this->catalog??=FormDefinition::data('services');$services=$this->catalog['services']??[];
  $topic=$payload['topic']??$payload['type']??'';
  $this->contactCopy??=FormDefinition::data('contact-page');$topics=collect($this->contactCopy['topics']??[])->pluck('label','value');
  $subject=$kind==='quotes'?($payload['serviceName']??$services[$service]['name']??ucfirst($service)).' insurance':($kind==='contacts'?($topics[$topic]??Str::headline($topic)):Str::headline($topic?:'Website feedback'));
  if(!empty($payload['wordpress']['form_name']))$subject=$payload['wordpress']['form_name'];
  return ['id'=>$record->id,'kind'=>$kind,'reference'=>$record->reference,'status'=>$record->status,'created_at'=>\Illuminate\Support\Carbon::parse($record->created_at),'updated_at'=>\Illuminate\Support\Carbon::parse($record->updated_at),'name'=>$name?:($kind==='feedback'?'Anonymous visitor':'Name not provided'),'email'=>(string)$email,'phone'=>(string)$phone,'service'=>$service,'subject'=>$subject,'message'=>(string)($payload['message']??''),'rating'=>$payload['rating']??null,'payload'=>$payload,'answers'=>$answers,'completion_status'=>$record->completion_status??'completed','progress_percent'=>(int)($record->progress_percent??100),'last_step_label'=>$record->last_step_label??'', 'url'=>'/admin/inbox/'.$kind.'/'.$record->id];
 }
 public function labels(string $service):array {
  $catalog=$this->catalog??=FormDefinition::data('services');$labels=['contactConsent'=>'Contact permission','smsRequested'=>'Text messages requested','consent'=>'Contact permission','sms'=>'Text messages requested','dateOfBirth'=>'Date of birth','insured'=>'Currently insured','bundleHome'=>'Bundle home coverage','dui'=>'DUI history','zip'=>'ZIP code','createdAt'=>'Submitted at','consentText'=>'Consent statement'];
  $questions=$catalog['services'][$service]['questions']??[];
  foreach($catalog['common']??[] as $group)foreach($group as $question)if(is_array($question))$questions[]=$question;
  foreach($questions as $question){if(isset($question['id'],$question['title']))$labels[$question['id']]=$question['title'];foreach($question['fields']??[] as $field)$labels[$field['id']]=$field['label'];}
  return $labels;
 }
 public function groups(array $item):array {
  if($item['kind']!=='quotes')return [];
  $groups=[];$contact=[];$coverage=[];$consent=[];$specific=[];
  foreach($item['answers'] as $key=>$value){
   if(in_array($key,['name','address','email','phone'])){$contact[$key]=$value;continue;}
   if(in_array($key,['insured','carrier','tenure','start','homeowner','bundleHome'])){$coverage[$key]=$value;continue;}
   if(in_array($key,['consent','sms','contactConsent','smsRequested'])){$consent[$key]=$value;continue;}
   if(in_array($key,['vehicles','drivers']))$groups[]=['title'=>Str::headline($key),'values'=>$value];else $specific[$key]=$value;
  }
  if($specific)array_unshift($groups,['title'=>$item['subject'].' details','values'=>$specific]);
  if($contact)$groups[]=['title'=>'Contact information','values'=>$contact];
  if($coverage)$groups[]=['title'=>'Coverage preferences','values'=>$coverage];
  if(!empty($item['payload']['consentText']))$consent['consentText']=$item['payload']['consentText'];
  if($consent)$groups[]=['title'=>'Permissions & preferences','values'=>$consent];
  return $groups;
 }
}
