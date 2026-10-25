<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SeoProfile extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['fields'=>'array','source'=>'array'];}
}
