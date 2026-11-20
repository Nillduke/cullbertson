<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InboxMessage extends Model {
 protected $guarded=['id'];
 public function twilioDelivery(){return $this->hasOne(TwilioDelivery::class);}
 public function author(){return $this->belongsTo(User::class,'user_id');}
 public function delivery(){return $this->hasOne(MailDelivery::class,'source_id')->where('kind','inbox_reply');}
}
