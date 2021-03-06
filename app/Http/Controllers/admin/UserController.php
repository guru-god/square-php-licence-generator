<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Mail\CookieActivated;
use App\Model\Licences;
use App\Model\Plans;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mail;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $users = User::orderBy('id', 'desc')->paginate(10);

        return view('admin.user.users', compact('users'));
    }

    public function userSearch(Request $request)
    {
        $this->validate($request,
            [
                'search' => 'required',
            ]);

        $users = User::where('name', 'like', '%' . $request->search . '%')->orWhere('email', 'like', '%' . $request->search . '%')->get();
        if (!$users)
            $users = [];
        return view('admin.user.search', compact('users'));

    }

    public function single($id)
    {
        $user = User::findorFail($id);

        return view('admin.user.single', compact('user'));
    }

    public function statupdate(Request $request, $id)
    {
        $user = User::find($id);

        $this->validate($request,
            [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users')->ignore($id),
                ]
            ]);

        $user['name'] = $request->name;
        $user['email'] = $request->email;

        if ($request->cookies)
            $user['cookies'] = $request['cookies'];

        $user->save();

        if ($request['cookies']) {
            Mail::to($user)->send(new CookieActivated($user));
        }

        return back()->withSuccess('User Profile Updated Successfuly');
    }

    public function createUserIndex()
    {
        $plans = Plans::all();
        return view('admin.user.create', compact('plans'));
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required | email | unique:users',
//            'mac' => 'required | unique:users',
            'password' => 'required | min:5',
            'plan' => 'required'
        ]);

        if ($request['cookies']) {
            $user = User::create([
                'name' => $request['name'],
                'email' => $request['email'],
//            'mac' => $request['mac'],
                'cookies' => json_encode($request['cookies'],JSON_UNESCAPED_SLASHES),
                'password' => Hash::make($request['password']),
            ]);
        } else {
            $user = User::create([
                'name' => $request['name'],
                'email' => $request['email'],
//            'mac' => $request['mac'],
                'password' => Hash::make($request['password']),
            ]);
        }

        $pid = $request['plan'];
        $plan = Plans::find($pid);
        $purchased = date('Y-m-d H:i:s');
        $expired = date('Y-m-d H:i:s', strtotime('+' . $plan->term, strtotime($purchased)));

        Licences::create([
            'user_id' => $user->id,
            'plan_id' => $pid,
            'expired' => $expired
        ]);

        return redirect()->route('users');
    }
}
