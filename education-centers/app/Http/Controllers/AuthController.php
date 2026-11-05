<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Affiche le formulaire de connexion.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            if (Auth::user()->isSuperAdmin() || Auth::user()->isCenterAdmin() || Auth::user()->isSecretary()) {
                return redirect()->route('admin.home');
            }

            return redirect()->route('dashboard');
        }

        $stats = [
            'centers' => Center::where('status', 'active')->count(),
            'students' => Student::where('status', 'active')->count(),
            'formations' => Formation::query()->current()->where('status', 'active')->count(),
        ];

        return view('auth.login', compact('stats'));
    }

    /**
     * Traite la tentative de connexion de l'utilisateur.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => __('L\'adresse e-mail est obligatoire.'),
            'email.email' => __('Veuillez saisir une adresse e-mail valide.'),
            'password.required' => __('Le mot de passe est obligatoire.'),
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Règle stricte : seuls les Super Admin, Admins Centre et Professeurs
            // peuvent se connecter à l'application. Les élèves n'ont pas d'accès utilisateur.
            if ($user->role === User::ROLE_STUDENT) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => __('L\'accès à l\'application est réservé à l\'administration et au corps enseignant.'),
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            if ($request->session()->pull('locale_explicit', false)) {
                $locale = $request->session()->get('locale');

                if (is_string($locale) && in_array($locale, LocaleController::SUPPORTED_LOCALES, true)) {
                    $user->forceFill(['locale' => $locale])->saveQuietly();
                }
            }

            // Ne jamais restaurer une URL interne (polling, endpoint JSON, page
            // interdite au rôle) comme destination post-connexion.
            $request->session()->forget('url.intended');

            if ($user->isSuperAdmin() || $user->isCenterAdmin() || $user->isSecretary()) {
                return redirect()->route('admin.home')
                    ->with('success', __('Connexion réussie. Bienvenue !'));
            }

            return redirect()->route('dashboard')
                ->with('success', __('Connexion réussie. Bienvenue !'));
        }

        return back()->withErrors([
            'email' => __('Ces identifiants ne correspondent à aucun compte actif.'),
        ])->onlyInput('email');
    }

    /**
     * Déconnecte l'utilisateur et invalide sa session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', __('Vous avez été déconnecté avec succès.'));
    }
}
