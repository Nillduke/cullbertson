<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FormDefinition extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['definition'=>'array']; }
    public static function data(string $key): array { return static::where('key',$key)->firstOrFail()->definition; }
}
