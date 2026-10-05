<?php

declare(strict_types=1);

namespace App\Http\Controllers\Talent;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use App\Models\TalentReferral;
use App\Models\User;
use App\Mail\WelcomeTalentMail;
use App\Mail\TalentPendingApprovalMail;
use App\Support\TalentPlan;
use App\Services\TalentReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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
            'band_name'            => ['required', 'string', 'max:255', Rule::unique('talents', 'band_name')],
            'email'                => ['required', 'email', 'max:255', Rule::unique('talents', 'email')],
            'password'             => ['required', 'string', 'min:8', 'confirmed'],
            'plan'                 => ['required', Rule::in(TalentPlan::keys())],
            'referral_code'        => ['nullable', 'string', 'max:32'],
            'country'              => ['nullable', 'string', 'max:100'],
            'contact_phone'        => ['nullable', 'string', 'max:50'],
            'facebook_screenshot'  => ['nullable'],
            'instagram_screenshot' => ['nullable'],
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

        // Procesar capturas de Facebook e Instagram
        $facebookScreenshot = null;
        if ($request->hasFile('facebook_screenshot')) {
            $facebookScreenshot = $request->file('facebook_screenshot')->store('talents/screenshots', 'public');
        } elseif ($request->filled('facebook_screenshot') && is_string($request->input('facebook_screenshot'))) {
            $facebookScreenshot = $request->input('facebook_screenshot');
        }

        $instagramScreenshot = null;
        if ($request->hasFile('instagram_screenshot')) {
            $instagramScreenshot = $request->file('instagram_screenshot')->store('talents/screenshots', 'public');
        } elseif ($request->filled('instagram_screenshot') && is_string($request->input('instagram_screenshot'))) {
            $instagramScreenshot = $request->input('instagram_screenshot');
        }

        $talent = DB::transaction(function () use ($validated, $bandName, $referralCode, $facebookScreenshot, $instagramScreenshot): Talent {
            $user = User::query()->firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $bandName,
                    'password' => Hash::make($validated['password']),
                ]
            );

            $talent = Talent::query()->create([
                'user_id'              => $user->id,
                'band_name'            => $bandName,
                'email'                => $validated['email'],
                'password'             => $validated['password'],
                'plan'                 => $validated['plan'],
                'subscription_status'  => $validated['plan'] === 'free' ? 'pending' : 'inactive',
                'payment_customer_id'  => null,
                'payment_provider'     => null,
                'interacts'            => 0,
                'is_featured'          => false,
                'email_verified_at'    => null,
                'referred_by_code'     => $referralCode ?: null,
                'country'              => $validated['country'] ?? null,
                'contact_phone'        => $validated['contact_phone'] ?? null,
                'facebook_screenshot'  => $facebookScreenshot,
                'instagram_screenshot' => $instagramScreenshot,
            ]);

            $talent->subscriptions()->create([
                'plan'             => $validated['plan'],
                'amount'           => TalentPlan::amount($validated['plan']),
                'start_date'       => today(),
                'end_date'         => today()->addDays(Talent::FREE_DURATION_DAYS),
                'status'           => 'pending',
                'currency'         => 'EUR',
                'payment_provider' => 'manual',
            ]);

            return $talent;
        });

        // Registrar referido (pending hasta que el admin apruebe)
        if ($referralCode !== '') {
            app(TalentReferralService::class)->registerReferral($talent, $referralCode);

            // Si el código de referido fue válido, el nuevo talento recibe 60 días (45 + 15 bono)
            $isReferred = TalentReferral::query()->where('referred_talent_id', $talent->id)->exists();
            if ($isReferred && $validated['plan'] === 'free') {
                $talent->subscriptions()->latest()->first()?->update([
                    'end_date' => today()->addDays(Talent::FREE_DURATION_DAYS + Talent::REFERRAL_BONUS_DAYS),
                ]);
            }
        }

        Auth::guard('talent')->login($talent, true);
        $request->session()->regenerate();

        app(\App\Services\ModerationService::class)->registerIfRequired('talent_registration', [
            'subject_type'    => Talent::class,
            'subject_id'      => $talent->id,
            'title'           => "Registro de Talento: {$talent->band_name}",
            'summary'         => "Plan: {$talent->plan}" . ($referralCode ? " | Código referido: {$referralCode}" : ''),
            'submitter_name'  => $talent->band_name,
            'submitter_email' => $talent->email,
        ]);

        if (filled($talent->email)) {
            Mail::to($talent->email)->queue(new WelcomeTalentMail($talent));
        }

        // Tarea 1 / Regla 10: Enviar aviso al admin con enlaces de aprobación si cuenta con capturas
        $hasScreenshots = filled($talent->facebook_screenshot) || filled($talent->instagram_screenshot);
        if ($hasScreenshots) {
            try {
                Mail::to('prog.sevenrockradio@gmail.com')->send(new TalentPendingApprovalMail($talent));
            } catch (Throwable $e) {
                Log::error("AuthController@register: error enviando TalentPendingApprovalMail: " . $e->getMessage());
            }
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
