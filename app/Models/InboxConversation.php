<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InboxConversation extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['starred'=>'boolean','email_dnd'=>'boolean','sms_dnd'=>'boolean','voice_dnd'=>'boolean','read_at'=>'datetime','last_message_at'=>'datetime'];}
 public function messages(){return $this->hasMany(InboxMessage::class,'conversation_id');}
 public function owner(){return $this->belongsTo(User::class,'assigned_to');}
 public function tags(){return $this->belongsToMany(InboxTag::class,'inbox_conversation_tag');}
 public function followers(){return $this->belongsToMany(User::class,'inbox_conversation_followers');}
}
