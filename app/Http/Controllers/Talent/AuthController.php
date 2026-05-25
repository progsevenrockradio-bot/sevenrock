<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use App\Models\User;
use App\Support\TalentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('talentos.register', [
            'plans' => TalentPlan::definitions(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'band_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('talents', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['required', Rule::in(TalentPlan::keys())],
        ]);

        $talent = DB::transaction(function () use ($validated): Talent {
            $user = User::query()->firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['band_name'],
                    'password' => Hash::make($validated['password']),
                ]
            );

            $talent = Talent::query()->create([
                'user_id' => $user->id,
                'band_name' => $validated['band_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'plan' => $validated['plan'],
                'subscription_status' => 'active',
                'payment_customer_id' => null,
                'interacts' => 0,
            ]);

            $talent->subscriptions()->create([
                'plan' => $validated['plan'],
                'amount' => TalentPlan::amount($validated['plan']),
                'start_date' => today(),
                'end_date' => today()->addMonth(),
                'status' => 'active',
            ]);

            return $talent;
        });

        Auth::guard('talent')->login($talent, true);
        $request->session()->regenerate();

        return redirect()->route('talents.dashboard');
    }

    public function showLogin(): View
    {
        return view('talentos.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('talent')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Invalid credentials.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('talents.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('talent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('talents.login');
    }
}
