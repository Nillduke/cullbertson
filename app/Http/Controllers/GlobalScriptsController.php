<?php

namespace App\Http\Controllers;

use App\Models\{FormDefinition,SiteBlock};
use App\Services\GlobalScripts;
use Illuminate\Http\Request;

class GlobalScriptsController extends Controller
{
    public const GOOGLE_FIELDS=[
        'containerId'=>['Google Tag Manager','GTM-'],
        'adsId'=>['Google Ads','AW-'],
        'primaryAnalyticsId'=>['Main Google Analytics property','G-'],
        'formsAnalyticsId'=>['Forms Google Analytics property','G-'],
        'additionalAnalyticsId'=>['Additional Google Analytics property','G-'],
        'additionalGoogleTagId'=>['Associated Google tag','GT-'],
        'adsConversionLabel'=>['Auto quote conversion label',''],
    ];

    public function index(GlobalScripts $scripts)
    {
        $tracking=FormDefinition::where('key','google-tracking')->first()?->definition;
        $managedHead=$tracking?view('partials.google-tracking-head',['googleTracking'=>$tracking+['useContainer'=>true]])->render():'';
        $managedBody=$tracking?view('partials.google-tracking-noscript',['googleTracking'=>$tracking+['useContainer'=>true]])->render():'';
        return response()->view('admin.globals',['scriptSettings'=>$scripts->settings(),'tracking'=>$tracking,'googleFields'=>self::GOOGLE_FIELDS,'managedHead'=>$managedHead,'managedBody'=>$managedBody,'inventory'=>$scripts->inventory(),'trackingEnabled'=>config('tracking.enabled'),'otherBlocks'=>SiteBlock::whereNotIn('key',['header','footer'])->get()])->header('Cache-Control','private, no-store');
    }

    public function save(Request $request,GlobalScripts $scripts)
    {
        $data=$request->validate(['header'=>'nullable|string|max:200000','footer'=>'nullable|string|max:200000','thank_you'=>'nullable|string|max:200000']);
        foreach($data as &$value)$value??='';unset($value);
        FormDefinition::updateOrCreate(['key'=>'global-scripts'],['name'=>'Public website and quote thank-you scripts','definition'=>array_replace($scripts->settings(),$data)]);
        return redirect(url('/admin/globals'))->with('success','Website scripts saved.');
    }

    public function tracking(Request $request)
    {
        $rules=[];
        foreach(self::GOOGLE_FIELDS as $key=>[$label,$prefix])$rules[$key]=['required','string','max:100','regex:/^'.preg_quote($prefix,'/').($prefix?'[A-Z0-9]+':'[A-Za-z0-9_-]+').'$/D'];
        $data=$request->validate($rules);$row=FormDefinition::where('key','google-tracking')->firstOrFail();
        $row->update(['definition'=>array_replace($row->definition,$data)]);
        return redirect(url('/admin/globals').'#google-integrations')->with('success','Google integration settings saved. Existing conversion rules are preserved.');
    }
}
