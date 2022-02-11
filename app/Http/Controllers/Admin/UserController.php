<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $users = User::query()->when($status > -1, function (Builder $builder) use ($status) {
            $builder->where('status', $status);
        })->when($request->query('keywords'), function (Builder $builder, $keywords) {
            $builder->whereRaw("concat(name, email) like ?",["%{$keywords}%"]);
        })->with('group')->withSum('images', 'size')->latest()->paginate();
        $statuses = [-1 => '全部', 1 => '正常', 0 => '冻结'];
        return view('admin.user.index', compact('users', 'statuses'));
    }

    public function edit(Request $request): View
    {
        $user = User::query()->findOrFail($request->route('id'));
        return view('admin.user.edit', compact('user'));
    }

    public function update(UserRequest $request): Response
    {
        /** @var User $user */
        $user = User::query()->findOrFail($request->route('id'));
        $user->fill($request->validated());
        if ($password = $request->validated('password')) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ]);

            event(new PasswordReset($user));
        }
        if (!$user->save()) {
            return $this->error('保存失败');
        }
        return $this->success('保存成功');
    }

    public function delete(Request $request): Response
    {
        /** @var User $user */
        if ($user = User::query()->find($request->route('id'))) {
            DB::transaction(function () use ($user) {
                $user->images()->update(['user_id' => null]);
                $user->albums()->delete();
                $user->delete();
            });
        }
        return $this->success('删除成功');
    }
}
