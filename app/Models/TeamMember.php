<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamMember extends Model {protected $guarded=['id'];public function page(){return $this->belongsTo(Page::class);}}
