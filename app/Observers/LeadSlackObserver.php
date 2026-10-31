<?php
namespace App\Observers;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuoteRequest;
class LeadSlackObserver {
 public function created(Model $model):void{$this->enqueue($model,$model instanceof QuoteRequest?($model->completion_status==='completed'?'completed':'partial'):'submitted');}
 public function updated(Model $model):void{if($model instanceof QuoteRequest&&$model->wasChanged('completion_status')&&$model->completion_status==='completed')$this->enqueue($model,'completed');}
 private function enqueue(Model $model,string $event):void{try{app(\App\Services\LeadSlack::class)->enqueue($model,$event);}catch(\Throwable $e){\Illuminate\Support\Facades\Log::warning('Could not queue Slack notification.',['record_id'=>$model->id,'exception_class'=>get_class($e)]);}}
}
