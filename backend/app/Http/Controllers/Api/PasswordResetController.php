<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $data=$request->validate(['email'=>'required|email']);
        $user=User::where('email',$data['email'])->where('is_active',true)->first();
        $resetUrl=null;
        if($user){
            $token=Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(['email'=>$user->email],[
                'token'=>Hash::make($token),'created_at'=>now(),
            ]);
            $resetUrl=rtrim(config('app.frontend_url',env('FRONTEND_URL','http://localhost:5173')),'/').'/reset-password?token='.$token.'&email='.urlencode($user->email);
            try{Mail::raw("Gunakan tautan berikut untuk mengatur ulang password BSI Flash 2027 Anda:\n\n$resetUrl\n\nTautan berlaku selama 60 menit.",fn($mail)=>$mail->to($user->email)->subject('Reset Password BSI Flash 2027'));}catch(\Throwable $e){report($e);}
        }
        $response=['message'=>'Jika email terdaftar, instruksi reset password telah dikirim.'];
        if(app()->environment(['local','testing'])&&$resetUrl)$response['reset_url']=$resetUrl;
        return response()->json($response);
    }

    public function reset(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','token'=>'required|string','password'=>'required|string|min:8|confirmed']);
        $record=DB::table('password_reset_tokens')->where('email',$data['email'])->first();
        if(!$record||now()->diffInMinutes($record->created_at,true)>60||!Hash::check($data['token'],$record->token)){
            return response()->json(['message'=>'Tautan reset tidak valid atau sudah kedaluwarsa.'],422);
        }
        $user=User::where('email',$data['email'])->firstOrFail();
        $user->update(['password'=>$data['password'],'api_token'=>null]);
        DB::table('password_reset_tokens')->where('email',$data['email'])->delete();
        return response()->json(['message'=>'Password berhasil diperbarui. Silakan login kembali.']);
    }
}
