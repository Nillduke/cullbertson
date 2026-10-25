<?php
namespace App\Observers;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuoteRequest;
use App\Services\LeadMail;
class LeadMailObserver {
 public function created(Model $model):void {
  $event=$model instanceof QuoteRequest?($model->completion_status==='completed'?'completed':'partial'):'submitted';
  $this->enqueue($model,$event);
 }
 public function updated(Model $model):void {
  if($model instanceof QuoteRequest&&$model->wasChanged('completion_status')&&$model->completion_status==='completed')$this->enqueue($model,'completed');
 }
 private function enqueue(Model $model,string $event):void {try{app(LeadMail::class)->enqueue($model,$event);}catch(\Throwable $e){\Illuminate\Support\Facades\Log::warning('Could not queue agency email.',['model'=>get_class($model),'record_id'=>$model->id,'exception_class'=>get_class($e)]);}}
}
