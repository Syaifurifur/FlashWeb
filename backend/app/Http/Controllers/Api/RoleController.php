<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return ['roles'=>AccessRole::orderBy('name')->get(),'permissions'=>AccessRole::PERMISSIONS];
    }

    public function store(Request $request)
    {
        $data=$this->validated($request);
        $data['slug']=$this->uniqueSlug($data['name']);
        return response()->json(AccessRole::create($data),201);
    }

    public function update(Request $request, AccessRole $accessRole)
    {
        $accessRole->update($this->validated($request));
        return $accessRole;
    }

    public function destroy(AccessRole $accessRole)
    {
        if(User::where('role',$accessRole->slug)->exists())return response()->json(['message'=>'Role masih digunakan oleh akun dan tidak dapat dihapus.'],422);
        $accessRole->delete();
        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        $data=$request->validate([
            'name'=>'required|string|max:80','permissions'=>'required|array|min:1',
            'permissions.*'=>['string',Rule::in(array_keys(AccessRole::PERMISSIONS))],
        ]);
        $data['permissions']=array_values(array_unique(array_merge(['dashboard.view'],$data['permissions'])));
        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base=Str::slug($name,'_') ?: 'role'; $slug=$base; $number=2;
        while(AccessRole::where('slug',$slug)->exists()||in_array($slug,['super_admin','pic','spv','judge','participant'],true))$slug=$base.'_'.$number++;
        return $slug;
    }
}
