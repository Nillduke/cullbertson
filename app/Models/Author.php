<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Author extends Model {protected $guarded=['id'];public function pages(){return $this->hasMany(Page::class);}public function path():string{return 'author/'.$this->slug;}}
