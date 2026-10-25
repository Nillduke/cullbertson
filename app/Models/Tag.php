<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tag extends Model {protected $guarded=['id'];public function pages(){return $this->belongsToMany(Page::class,'page_tag');}public function path():string{return 'tag/'.$this->slug;}}
