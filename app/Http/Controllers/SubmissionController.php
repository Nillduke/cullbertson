<?php
namespace App\Http\Controllers;
use App\Models\{QuoteRequest,ContactEnquiry,FeedbackEntry,FormDefinition};
use App\Services\SubmissionPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SubmissionController extends Controller
{
 public const MODELS=['quotes'=>QuoteRequest::class,'contacts'=>ContactEnquiry::class,'feedback'=>FeedbackEntry::class];
 public function index(Request $request,SubmissionPresentation $presenter){
  $filters=$request->validate(['kind'=>'nullable|in:quotes,contacts,feedback','completion'=>'nullable|in:incomplete,completed','status'=>'nullable|in:new,reviewed,contacted,resolved','service'=>'nullable|string|max:80','q'=>'nullable|string|max:200','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':'')]);
  $union=null;$counts=[];$newCount=0;
  foreach(self::MODELS as $kind=>$model){
   $counts[$kind]=$model::count();$newCount+=$model::where('status','new')->count();
   $table=(new $model)->getTable();$query=DB::table($table)->select(['id','reference','payload','status','created_at','updated_at'])->selectRaw("'".$kind."' as kind")->selectRaw($kind==='quotes'?"service":"'' as service")->selectRaw($kind==='quotes'?"completion_status,progress_percent,last_step_label":"'completed' as completion_status,100 as progress_percent,'' as last_step_label");
   if($union===null)$union=$query;else $union->unionAll($query);
  }
  $query=DB::query()->fromSub($union,'submissions');
  foreach(['kind','status','service'] as $key)if(!empty($filters[$key]))$query->where($key,$filters[$key]);
  if(!empty($filters['completion']))$query->where('completion_status',$filters['completion']);
  if(!empty($filters['q']))$query->where(fn($q)=>$q->where('reference','like','%'.$filters['q'].'%')->orWhere('payload','like','%'.$filters['q'].'%'));
  if(!empty($filters['from']))$query->whereDate('created_at','>=',$filters['from']);
  if(!empty($filters['to']))$query->whereDate('created_at','<=',$filters['to']);
  $entries=$query->orderByDesc('created_at')->orderBy('kind')->orderByDesc('id')->paginate(20)->withQueryString();
  $entries->setCollection($entries->getCollection()->map(fn($record)=>$presenter->summarize($record,$record->kind)));
  return response()->view('admin.inbox',compact('entries','counts','newCount','filters')+['partialCount'=>QuoteRequest::where('completion_status','incomplete')->count(),'services'=>FormDefinition::data('services')['services']??[]])->header('Cache-Control','private, no-store');
 }
 public function show(string $kind,int $id,SubmissionPresentation $presenter){
  $model=self::MODELS[$kind]??abort(404);$record=$model::findOrFail($id);$item=$presenter->summarize($record,$kind);
  $previous=$model::where(fn($q)=>$q->where('created_at','>',$record->created_at)->orWhere(fn($q)=>$q->where('created_at',$record->created_at)->where('id','>',$id)))->orderBy('created_at')->orderBy('id')->value('id');$next=$model::where(fn($q)=>$q->where('created_at','<',$record->created_at)->orWhere(fn($q)=>$q->where('created_at',$record->created_at)->where('id','<',$id)))->orderByDesc('created_at')->orderByDesc('id')->value('id');
  $statuses=$kind==='feedback'?['new','reviewed','resolved']:['new','reviewed','contacted'];
  return response()->view('admin.submission',compact('item','previous','next','statuses')+['labels'=>$presenter->labels($item['service']),'groups'=>$presenter->groups($item)])->header('Cache-Control','private, no-store');
 }
 public function status(Request $request,string $kind,int $id){
  $model=self::MODELS[$kind]??abort(404);$record=$model::findOrFail($id);
  $record->update($request->validate(['status'=>'required|in:'.($kind==='feedback'?'new,reviewed,resolved':'new,reviewed,contacted')]));
  return redirect('/admin/inbox/'.$kind.'/'.$id)->with('success','Submission status updated.');
 }
}
