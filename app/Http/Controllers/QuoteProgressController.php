<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\QuoteProgress;
use App\Models\QuoteRequest;
class QuoteProgressController extends Controller {
 public function show(Request $request,QuoteProgress $progress){
  $input=$request->validate(['flow_id'=>'required|uuid','service'=>'required|string|max:80']);$progress->service($input['service']);
  $quote=QuoteRequest::where('capture_key',$progress->key($request,$input['flow_id']))->where('service',$input['service'])->first();
  return response()->json(['draft'=>$quote?['payload'=>$quote->payload,'step'=>$quote->last_step,'percent'=>$quote->progress_percent,'completion_status'=>$quote->completion_status,'reference'=>$quote->reference]:null])->header('Cache-Control','private, no-store');
 }
 public function store(Request $request,QuoteProgress $progress){
  abort_unless($request->isJson(),415);abort_if(strlen($request->getContent())>24000,413);
  $input=$request->validate(['flow_id'=>'required|uuid','service'=>'required|string|max:80','step'=>'required|string|max:100|regex:/^[a-zA-Z0-9:_-]+$/','step_label'=>'required|string|max:255','percent'=>'required|integer|between:0,100']);
  $quote=$progress->save($request,$input);
  return response()->json(['ok'=>true,'reference'=>$quote->reference,'completion_status'=>$quote->completion_status,'percent'=>$quote->progress_percent])->header('Cache-Control','private, no-store');
 }
}
