<?php
namespace App\Http\Controllers;
use App\Models\{Page,FormDefinition,ContactEnquiry};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ContactController extends Controller {
    public function store(Request $request){
        Page::published()->where('slug','contact-us')->firstOrFail();
        $copy=FormDefinition::data('contact-page');
        $data=$request->validate(['name'=>'required|string|max:100','email'=>'required|email:rfc|max:254','phone'=>'nullable|string|max:40','topic'=>['required',Rule::in(array_column($copy['topics'],'value'))],'message'=>'required|string|min:10|max:3000','company_website'=>'nullable|string|max:0']);
        unset($data['company_website']);$reference='ENQ-'.strtoupper(bin2hex(random_bytes(5)));
        ContactEnquiry::create(['reference'=>$reference,'payload'=>$data]);
        return redirect('/contact-us/#contact-form')->with('contact_reference',$reference);
    }
}
