<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Category extends Model {
 protected $guarded=['id'];
 public function pages(){return $this->belongsToMany(Page::class,'category_page');}
 public function parent(){return $this->belongsTo(self::class,'parent_id');}
 public function path():string {$parts=[$this->slug];$parent=$this->parent;$seen=[$this->id];while($parent && !in_array($parent->id,$seen,true)){$seen[]=$parent->id;array_unshift($parts,$parent->slug);$parent=$parent->parent;}return 'category/'.implode('/',$parts);}
}
