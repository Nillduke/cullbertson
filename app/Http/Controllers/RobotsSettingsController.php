<?php
namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Services\RobotsTxt;
use Illuminate\Http\Request;

class RobotsSettingsController extends Controller
{
    public function index(RobotsTxt $robots)
    {
        return response()->view('admin.robots', ['content'=>$robots->content(), 'defaults'=>$robots->defaults(), 'custom'=>FormDefinition::where('key','robots-txt')->exists()])
            ->header('Cache-Control','private, no-store');
    }

    public function save(Request $request)
    {
        $data=$request->validate(['content'=>['required','string','max:100000','not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/']]);
        $content=rtrim(str_replace(["\r\n","\r"],"\n",$data['content']))."\n";
        FormDefinition::updateOrCreate(['key'=>'robots-txt'],['name'=>'Robots.txt crawl directives','definition'=>['content'=>$content]]);
        return redirect(url('/admin/robots'))->with('success','Robots.txt saved and published.');
    }

    public function reset()
    {
        FormDefinition::where('key','robots-txt')->delete();
        return redirect(url('/admin/robots'))->with('success','Robots.txt restored to the environment default.');
    }
}
