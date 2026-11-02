<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireTwoFactor;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(private Google2FA $google2fa) {}

    /** Enrolment: show a QR code for a fresh (unconfirmed) secret. */
    public function setup(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey(32)])->save();
        }

        $otpauth = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($otpauth);

        return Inertia::render('auth/TwoFactorSetup', [
            'qrSvg' => $svg,
            'secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')),
            'role' => $user->roleInCurrentCompany()?->label(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $request->string('code'), 1)) {
            throw ValidationException::withMessages(['code' => 'That code did not match. Check the time on your phone and try the newest code.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();
        $request->session()->put(RequireTwoFactor::SESSION_KEY, $user->id);

        return redirect()->route('two-factor.recovery-codes')->with('recoveryCodes', $codes);
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recoveryCodes');

        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/TwoFactorRecoveryCodes', ['codes' => $codes]);
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get(RequireTwoFactor::SESSION_KEY) === $user->id) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/TwoFactorChallenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'string'],
        ]);
        $user = $request->user();

        if (! empty($data['code'])) {
            $ok = $this->google2fa->verifyKey($user->two_factor_secret, $data['code'], 1);
        } else {
            $codes = $user->two_factor_recovery_codes ?? [];
            $given = Str::lower(trim($data['recovery_code']));
            $ok = in_array($given, $codes, true);

            if ($ok) {
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$given]))])->save();
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages([empty($data['code']) ? 'recovery_code' : 'code' => 'That code is not valid.']);
        }

        $request->session()->put(RequireTwoFactor::SESSION_KEY, $user->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
