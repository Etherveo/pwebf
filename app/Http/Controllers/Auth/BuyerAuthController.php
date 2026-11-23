<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterBuyerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Unified login and buyer registration for the two real store actors:
// pembeli and admin_toko, sharing the web guard over users table.
class BuyerAuthController extends Controller
{
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect($this->webHome());
        }

        return view('auth.daftar');
    }

    public function register(RegisterBuyerRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'pembeli',
            'is_active' => true,
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('katalog');
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect($this->webHome());
        }

        $aktor = $request->query('aktor');
        if (! in_array($aktor, ['pembeli', 'admin_toko'], true)) {
            $aktor = 'pembeli';
        }

        return view('auth.masuk', ['aktor' => $aktor]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $aktor = $request->validated('aktor');
        $credentials = $request->only('email', 'password');
        $generic = ['email' => 'Kredensial tidak valid.'];

        // Web actors (pembeli / admin_toko): attempt first, then verify
        // that the account's role matches the selected aktor exactly.
        if (! Auth::guard('web')->attempt($credentials)) {
            return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User || $user->role !== $aktor) {
            Auth::guard('web')->logout();

            return back()->withErrors($generic)->withInput($request->only('email', 'aktor'));
        }

        if ($user->is_active === false) {
            Auth::guard('web')->logout();

            return back()->withErrors(['email' => 'Akun Anda nonaktif. Silakan hubungi Admin Toko.'])->withInput($request->only('email', 'aktor'));
        }

        $request->session()->regenerate();

        return redirect()->intended($aktor === 'admin_toko' ? route('admin.dashboard') : route('katalog'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    }

    private function webHome(): string
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->isAdmin() ? route('admin.dashboard') : route('katalog');
    }
}
