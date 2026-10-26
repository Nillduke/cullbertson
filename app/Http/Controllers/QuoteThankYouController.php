<?php

namespace App\Http\Controllers;

use App\Models\{FormDefinition,QuoteRequest};
use App\Services\GlobalScripts;
use Illuminate\Http\Request;

class QuoteThankYouController extends Controller
{
    public function show(Request $request,GlobalScripts $scripts)
    {
        $token=$request->query('receipt');
        if(!is_string($token)||!preg_match('/^[a-f0-9]{48}$/D',$token))return redirect(url('/get-a-quote/'));
        $receipt=$request->session()->get('quote_receipts.'.$token);
        if(!$receipt||($receipt['created']??0)<=now()->subDay()->timestamp)return redirect(url('/get-a-quote/'));
        $quote=QuoteRequest::whereKey($receipt['quote_id'])->where('completion_status','completed')->first();
        if(!$quote)return redirect(url('/get-a-quote/'));
        $runTracking=!($receipt['tracking_shown']??false);
        $request->session()->put('quote_receipts.'.$token.'.tracking_shown',true);
        $copy=FormDefinition::data('quote-thank-you-copy');
        $completion=['reference'=>$quote->reference,'service'=>$quote->service,'completed'=>true];
        $event=['event'=>'culbertson_quote_thank_you','transaction_id'=>hash('sha256',$quote->reference),'coverage_type'=>$quote->service];
        return response()->view('content.quote-thank-you',[
            'title'=>$copy['title'],'description'=>$copy['description'],'bodyClass'=>'content-page quote-thank-you-page',
            'styles'=>[asset('quote-thank-you.css').'?v='.filemtime(public_path('quote-thank-you.css'))],
            'scripts'=>[asset('quote-thank-you.js').'?v='.filemtime(public_path('quote-thank-you.js'))],
            'copy'=>$copy,'reference'=>$quote->reference,'completion'=>$completion,'event'=>$event,'runTracking'=>$runTracking,
            'goalScripts'=>$scripts->settings()['thank_you'],
        ])->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow')->header('Referrer-Policy','same-origin');
    }
}
