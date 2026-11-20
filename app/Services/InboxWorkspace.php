<?php
namespace App\Services;

use App\Models\{QuoteRequest,ContactEnquiry,FeedbackEntry,InboxConversation,User};
use Illuminate\Support\Facades\DB;

class InboxWorkspace {
 public const MODELS=['quotes'=>QuoteRequest::class,'contacts'=>ContactEnquiry::class,'feedback'=>FeedbackEntry::class];
 public function record(string $kind,int $id){return (self::MODELS[$kind]??abort(404))::findOrFail($id);}
 public function query(){
  $union=null;
  foreach(self::MODELS as $kind=>$model){
   $q=DB::table((new $model)->getTable())->select(['id','reference','payload','status','created_at','updated_at'])->selectRaw("'".$kind."' as kind")->selectRaw($kind==='quotes'?"service,completion_status,progress_percent,last_step_label":"'' as service,'completed' as completion_status,100 as progress_percent,'' as last_step_label");
   if($union===null)$union=$q;else $union->unionAll($q);
  }
  return DB::query()->fromSub($union,'s')->leftJoin('inbox_conversations as c',fn($join)=>$join->on('c.kind','=','s.kind')->on('c.source_id','=','s.id'));
 }
 public function unread($query){return $query->where(fn($q)=>$q->whereNull('c.read_at')->orWhereColumn('s.updated_at','>','c.read_at'));}
 public function conversation(string $kind,int $id):InboxConversation{return InboxConversation::firstOrCreate(['kind'=>$kind,'source_id'=>$id]);}
 public function detail(string $kind,int $id,SubmissionPresentation $presenter,bool $markRead=true):array{
  $record=$this->record($kind,$id);$conversation=$this->conversation($kind,$id);
  if($markRead)$conversation->update(['read_at'=>now()]);
  $item=$presenter->summarize($record,$kind);
  return ['item'=>$item,'conversation'=>$conversation,'messages'=>$conversation->messages()->with('author')->orderBy('id')->get(),
   'owners'=>User::where('is_admin',true)->orderBy('name')->get(['id','name']),
   'attachments'=>$kind==='contacts'?DB::table('contact_files')->where('contact_enquiry_id',$id)->get():collect(),
   'statuses'=>$kind==='feedback'?['new','reviewed','resolved','spam','trash']:['new','reviewed','contacted','spam','trash'],
   'labels'=>$presenter->labels($item['service']),'groups'=>$presenter->groups($item)];
 }
}
