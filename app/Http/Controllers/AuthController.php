<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (User::count() === 0) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (! $request->user()->is_active) {
                Auth::logout();

                return back()->withErrors(['email' => 'This account is inactive.']);
            }

            Audit::record('login');

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'The email or password is incorrect.']);
    }

    public function setupForm(): View|RedirectResponse
    {
        if (User::count() > 0) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function setup(Request $request): RedirectResponse
    {
        abort_if(User::count() > 0, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data + [
            'role' => 'admin',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        Audit::record('setup_admin_created', $user, null, $user->only(['id', 'email', 'role']));

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Audit::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
