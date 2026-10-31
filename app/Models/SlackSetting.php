<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SlackSetting extends Model {
 protected $guarded=['id'];protected $hidden=['webhook_url'];
 protected function casts():array{return ['webhook_url'=>'encrypted','enabled'=>'boolean','notify_quotes'=>'boolean','notify_partials'=>'boolean','notify_contacts'=>'boolean','notify_feedback'=>'boolean','include_contact'=>'boolean','last_test_at'=>'datetime'];}
 public static function current():self{return static::unguarded(fn()=>static::firstOrCreate(['id'=>1],['website_url'=>MailSetting::query()->value('website_url')?:config('app.url')]));}
 public function configured():bool{return (bool)$this->getRawOriginal('webhook_url')&&(bool)$this->channel&&(bool)$this->website_url;}
}
