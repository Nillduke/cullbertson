<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InboxConversation extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['starred'=>'boolean','read_at'=>'datetime','last_message_at'=>'datetime'];}
 public function messages(){return $this->hasMany(InboxMessage::class,'conversation_id');}
 public function owner(){return $this->belongsTo(User::class,'assigned_to');}
}
