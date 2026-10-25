<?php

namespace App\Http\Controllers;

use App\Models\{ContactEnquiry, FormDefinition, Page};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyRequestController extends Controller
{
    public function store(Request $request)
    {
        Page::published()->where('slug', 'privacy-choices')->firstOrFail();
        $copy = FormDefinition::data('form-information')['request'];
        $data = $request->validate([
            'name'=>'required|string|max:100', 'email'=>'required|email:rfc|max:254',
            'phone'=>'nullable|string|max:40', 'request_type'=>['required', Rule::in(array_keys($copy['types']))],
            'message'=>'nullable|string|max:3000', 'authorized'=>'required|accepted', 'company_website'=>'nullable|string|max:0',
        ]);
        $entry = ContactEnquiry::create([
            'reference'=>'PRV-'.strtoupper(bin2hex(random_bytes(5))),
            'payload'=>[
                'name'=>$data['name'], 'email'=>$data['email'], 'phone'=>$data['phone']??'',
                'topic'=>$copy['inboxTopic'], 'requestType'=>$data['request_type'],
                'message'=>$copy['types'][$data['request_type']]."\n\n".($data['message']??''),
                'consent'=>true, 'consentText'=>$copy['authorized'], 'marketingConsent'=>false,
            ],
        ]);
        return redirect(url('/privacy-choices/').'#privacy-request')->with('privacy_reference', $entry->reference);
    }
}
