<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use App\Models\User;
use App\Mail\WelcomeTalentMail;
use App\Support\TalentPlan;
use App\Services\TalentReferralService;
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
        $rawBandName = trim((string) ($request->input('band_name') ?? $request->input('name', '')));
        $request->merge(['band_name' => $rawBandName]);

        $validated = $request->validate([
            'band_name'     => ['required', 'string', 'max:255', Rule::unique('talents', 'band_name')],
            'email'         => ['required', 'email', 'max:255', Rule::unique('talents', 'email')],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'plan'          => ['required', Rule::in(TalentPlan::keys())],
            'referral_code' => ['nullable', 'string', 'max:32'],
        ], [
            'band_name.required' => 'El nombre de la banda es obligatorio.',
            'band_name.unique'   => 'Este nombre de banda ya está registrado en Seven Rock Radio. Por favor, elige otro nombre o contacta con soporte.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.unique'       => 'Este correo electrónico ya tiene una cuenta registrada.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $bandName = $validated['band_name'];
        $referralCode = trim((string) ($validated['referral_code'] ?? ''));

        $talent = DB::transaction(function () use ($validated, $bandName, $referralCode): Talent {
            $user = User::query()->firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $bandName,
                    'password' => Hash::make($validated['password']),
                ]
            );

            $talent = Talent::query()->create([
                'user_id'             => $user->id,
                'band_name'           => $bandName,
                'email'               => $validated['email'],
                'password'            => $validated['password'],
                'plan'                => $validated['plan'],
                'subscription_status' => $validated['plan'] === 'free' ? 'pending' : 'inactive',
                'payment_customer_id' => null,
                'payment_provider'    => null,
                'interacts'           => 0,
                'is_featured'         => false,
                'email_verified_at'   => null,
                'referred_by_code'    => $referralCode ?: null,
            ]);

            $talent->subscriptions()->create([
                'plan'             => $validated['plan'],
                'amount'           => TalentPlan::amount($validated['plan']),
                'start_date'       => today(),
                'end_date'         => today()->addMonth(),
                'status'           => 'pending',
                'currency'         => 'EUR',
                'payment_provider' => 'manual',
            ]);

            return $talent;
        });

        // Registrar referido (pending hasta que el admin apruebe)
        if ($referralCode !== '') {
            app(TalentReferralService::class)->registerReferral($talent, $referralCode);
        }

        Auth::guard('talent')->login($talent, true);
        $request->session()->regenerate();

        app(\App\Services\ModerationService::class)->registerIfRequired('talent_registration', [
            'subject_type'   => Talent::class,
            'subject_id'     => $talent->id,
            'title'          => "Registro de Talento: {$talent->band_name}",
            'summary'        => "Plan: {$talent->plan}" . ($referralCode ? " | Código referido: {$referralCode}" : ''),
            'submitter_name'  => $talent->band_name,
            'submitter_email' => $talent->email,
        ]);

        if (filled($talent->email)) {
            Mail::to($talent->email)->queue(new WelcomeTalentMail($talent));
        }

        return redirect()->route('talents.dashboard')->with('status', $validated['plan'] === 'free'
            ? 'Cuenta creada. Pendiente de aprobación por el equipo de Seven Rock Radio.'
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
