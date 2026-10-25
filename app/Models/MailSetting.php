<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model {
 protected $guarded=['id'];
 protected $hidden=['password'];
 protected function casts():array{return [
  'password'=>'encrypted','recipients'=>'array','enabled'=>'boolean','authentication'=>'boolean',
  'notify_quotes'=>'boolean','notify_partials'=>'boolean','notify_contacts'=>'boolean','notify_feedback'=>'boolean',
  'include_contact'=>'boolean','connection_checked_at'=>'datetime',
 ];}
 public static function current():self{return static::unguarded(fn()=>static::firstOrCreate(['id'=>1],['recipients'=>[],'website_url'=>config('app.url')]));}
 public function missing(bool $test=false):array {
  $missing=[];if(!filter_var($this->from_address,FILTER_VALIDATE_EMAIL))$missing[]='sender email address';
  if(!$test&&!count($this->recipients??[]))$missing[]='notification recipient';
  if($this->authentication&&(!$this->username||!$this->getRawOriginal('password')))$missing[]='SMTP username/password';
  return $missing;
 }
}
