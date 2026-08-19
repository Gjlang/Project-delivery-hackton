<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Assistant\AssistantMemoryService;
use App\Services\CompanyRules\CompanyRuleReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function promptLogin()
    {
        return view('auth.login');
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(AssistantMemoryService $memory, CompanyRuleReadinessService $readiness): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // The session's CSRF state didn't match what Google sent back --
            // usually a stale/duplicate login attempt (two tabs, a prefetched
            // link, or a server restart between redirect and callback), not
            // an actual attack in local dev. Send them back to try again
            // instead of a hard 500.
            Log::warning('Google login: state mismatch, redirecting back to login', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('status', 'Your sign-in attempt expired -- please try again.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // An account created before Google Sign-In existed (seeding,
            // testing) still logs in cleanly by linking on email match
            // instead of creating a duplicate row.
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update(['google_id' => $googleUser->getId(), 'avatar' => $googleUser->getAvatar()]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => null,
                ]);
            }
        }

        $memory->ensureThreadForUser($user);

        Auth::login($user, remember: true);

        // First-run users (no company rules configured yet) land on the
        // merged Create-Project workspace, which folds the rule-upload panel
        // in directly. Returning users with rules already set up skip
        // straight to the dashboard.
        $destination = $readiness->isBlocking($user->company_id)
            ? route('projects.new')
            : route('dashboard');

        return redirect()->intended($destination);
    }
}
