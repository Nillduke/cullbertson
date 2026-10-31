<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SlackDelivery extends Model {
 protected $guarded=['id'];protected $hidden=['webhook_url'];
 protected function casts():array{return ['webhook_url'=>'encrypted','attempted_at'=>'datetime','next_attempt_at'=>'datetime','locked_at'=>'datetime','sent_at'=>'datetime'];}
}
