<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MailDelivery extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['recipients'=>'encrypted:array','attempted_at'=>'datetime','next_attempt_at'=>'datetime','locked_at'=>'datetime','sent_at'=>'datetime'];}
}
