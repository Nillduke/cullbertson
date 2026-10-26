<?php

namespace App\Services;

use App\Models\QuoteRequest;
use Illuminate\Http\Request;

class QuoteThankYou
{
    public function issue(Request $request,QuoteRequest $quote):string
    {
        $receipts=array_filter($request->session()->get('quote_receipts',[]),fn($r)=>($r['created']??0)>now()->subDay()->timestamp);
        foreach($receipts as $token=>$receipt)if($receipt['quote_id']===$quote->id)return url('/quote/thank-you/').'?receipt='.$token;
        $token=bin2hex(random_bytes(24));
        $receipts[$token]=['quote_id'=>$quote->id,'created'=>now()->timestamp,'tracking_shown'=>false];
        $request->session()->put('quote_receipts',array_slice($receipts,-20,null,true));
        return url('/quote/thank-you/').'?receipt='.$token;
    }
}
