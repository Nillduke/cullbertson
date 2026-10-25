<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AdminAuthController extends Controller
{
    public function login(){return auth()->check() && auth()->user()->is_admin ? redirect('/admin') : view('admin.login');}
    public function authenticate(Request $request){
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if(!Auth::attempt($data))return back()->withErrors(['email'=>'The login details are incorrect.'])->onlyInput('email');
        if(!Auth::user()->is_admin){Auth::logout();abort(403);}
        $request->session()->regenerate();return redirect()->intended('/admin');
    }
    public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect('/admin/login');}
}
