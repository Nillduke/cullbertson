<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TwilioSetting extends Model {
 protected $guarded=['id'];protected $hidden=['auth_token'];
 protected function casts():array{return ['auth_token'=>'encrypted','enabled'=>'boolean','sms_enabled'=>'boolean','voice_enabled'=>'boolean','connection_checked_at'=>'datetime'];}
 public static function current():self{return static::firstOrCreate(['id'=>1]);}
 public function credentialsReady():bool{return (bool)($this->account_sid&&$this->getRawOriginal('auth_token'));}
}
