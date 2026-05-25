<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use App\Models\User;
use App\Mail\WelcomeTalentMail;
use App\Support\TalentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegisterForm(): View
    {
        return view('talentos.auth.register', [
            'plans' => TalentPlan::definitions(),
        ]);
    }

    public function showRegister(): View
    {
        return $this->showRegisterForm();
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'band_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('talents', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['required', Rule::in(TalentPlan::keys())],
        ]);

        $bandName = trim((string) ($validated['name'] ?? ''));
        $bandName = $bandName !== '' ? $bandName : trim((string) ($request->input('band_name', '')));
        if ($bandName === '') {
            return back()->withInput()->withErrors([
                'name' => 'El nombre de la banda es obligatorio.',
            ]);
        }

        $talent = DB::transaction(function () use ($validated, $bandName): Talent {
            $user = User::query()->firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $bandName,
                    'password' => Hash::make($validated['password']),
                ]
            );

            $talent = Talent::query()->create([
                'user_id' => $user->id,
                'band_name' => $bandName,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'plan' => $validated['plan'],
                'subscription_status' => $validated['plan'] === 'free' ? 'active' : 'inactive',
                'payment_customer_id' => null,
                'payment_provider' => null,
                'interacts' => 0,
                'is_featured' => false,
                'email_verified_at' => null,
            ]);

            $talent->subscriptions()->create([
                'plan' => $validated['plan'],
                'amount' => TalentPlan::amount($validated['plan']),
                'start_date' => today(),
                'end_date' => today()->addMonth(),
                'status' => $validated['plan'] === 'free' ? 'active' : 'pending',
                'currency' => 'EUR',
                'payment_provider' => 'manual',
            ]);

            return $talent;
        });

        Auth::guard('talent')->login($talent, true);
        $request->session()->regenerate();

        if (filled($talent->email)) {
            Mail::to($talent->email)->queue(new WelcomeTalentMail($talent));
        }

        return redirect()->route('talents.dashboard')->with('status', $validated['plan'] === 'free'
            ? 'Cuenta creada.'
            : 'Cuenta creada. Completa el pago para activar tu suscripción.');
    }

    public function showLoginForm(): View
    {
        return view('talentos.auth.login');
    }

    public function showLogin(): View
    {
        return $this->showLoginForm();
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('talent')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Credenciales inválidas.',
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
