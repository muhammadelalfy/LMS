<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
class AuthController extends Controller {
    public function register(Request $request) { $data = $request->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email','password'=>['required',Password::defaults(),'confirmed'],'role'=>'sometimes|in:parent,student']); $user = User::create($data); return response()->json(['user'=>$user,'token'=>$user->createToken('lms-web')->plainTextToken], 201); }
    public function login(Request $request) { $data = $request->validate(['email'=>'required|email','password'=>'required|string']); $user = User::where('email',$data['email'])->first(); abort_unless($user && Hash::check($data['password'], $user->password), 422, 'بيانات الدخول غير صحيحة.'); return ['user'=>$user,'token'=>$user->createToken('lms-web')->plainTextToken]; }
    public function me(Request $request) { return $request->user()->load('studentAccount.student'); }
    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return ['success'=>true]; }
}
