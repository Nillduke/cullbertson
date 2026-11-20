<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TwilioDelivery extends Model {protected $guarded=['id'];protected function casts():array{return ['locked_at'=>'datetime'];}public function message(){return $this->belongsTo(InboxMessage::class,'inbox_message_id');}public function conversation(){return $this->belongsTo(InboxConversation::class,'inbox_conversation_id');}}
