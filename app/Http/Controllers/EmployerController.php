<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employer;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\File;
use Auth;
use Arr;
use App\Models\User;
use Storage;


class EmployerController extends Controller
{
    public function index(Employer $employer)
    {
        return view('results', ['jobs' => $employer->jobs()->with(['employer', 'tags'])->paginate(12)]);
    }

    public function create()
    {
        return view('auth.register');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = request()->validate([
            'name' => 'required',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'employer' => 'required|min:5',
            'logo' => ['required', 'mimes:png,jpg,webp,svg', 'dimensions:min_width=100,min_height=100', 'max:2048'],
        ]);

        $logoPath = request()->logo->store('logos');

        $user = User::create(Arr::only($validated, ['name', 'email', 'password']));

        $user->employer()->create([
            'name' => $validated['employer'],
            'logo' => basename($logoPath)
        ]);

        Auth::login($user);

        return redirect('/');
    }

    public function edit(Employer $employer)
    {
        if (!session()->has('referral_url')) {
            session()->put('referral_url', url()->previous());
        }

        return view('auth.edit-employer', ['employer' => $employer]);
    }

    public function update(Request $request, Employer $employer)
    {
        $validationArray = [
            'name' => 'required',
            'email' => 'required|email|max:254|unique:users,email,' . $employer->user->id,
            'employer' => 'required|min:5',
        ];
    
        if (!empty($request->password) || !empty($request->password_confirmation)) {
            $validationArray['password'] = ['required', 'confirmed', Password::defaults()];
        }
    
        if ($request->hasFile('logo')) {
            $validationArray['logo'] = ['required', 'mimes:png,jpg,webp,svg', 'dimensions:min_width=100,min_height=100', 'max:2048'];
        }
    
        $validatedUser = $request->validate($validationArray);
    
        $userFields = [
            'name' => $request->name,
            'email' => $request->email,
        ];
    
        $employerFields['name'] = $request->employer;
    
        if ($request->hasFile('logo')) {
            $logoPath = $request->logo->store('logos');
            Storage::delete(['file', 'logos/' . $employer->logo]);
            $employerFields['logo'] = basename($logoPath);
        }
    
        if (!empty($request->password)) {
            $userFields['password'] = bcrypt($request->password);
        }
    
        $employer->update($employerFields);
        $employer->user->update($userFields);
    
        $referralUrl = session()->get('referral_url');
        session()->forget('referral_url');
    
        return redirect($referralUrl)->with('success', 'Update successful!');
    }
}