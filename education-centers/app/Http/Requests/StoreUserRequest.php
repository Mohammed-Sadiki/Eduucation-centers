<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $authUser = $this->user();
        $isSuperAdmin = $authUser->isSuperAdmin();

        $isTeacher = $this->input('role', User::ROLE_TEACHER) === User::ROLE_TEACHER;
        $allowedRoles = $isSuperAdmin
            ? [User::ROLE_SUPER_ADMIN, User::ROLE_CENTER_ADMIN, User::ROLE_SECRETARY, User::ROLE_TEACHER]
            : [User::ROLE_TEACHER];

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive'],
            // Nouveau parcours : le domaine n'est plus une étape de sélection.
            // Champ conservé (métadonnée interne) pour compatibilité avec les
            // comptes historiques et les tests existants, mais plus jamais requis.
            'domain' => ['nullable', 'string', Rule::exists('domains', 'slug')],
            'formation_id' => [
                Rule::requiredIf($isTeacher),
                'nullable',
                'integer',
                Rule::exists('formations', 'id')->where(function ($query) use ($isSuperAdmin, $authUser) {
                    // La formation doit appartenir au centre de rattachement.
                    $centerId = $isSuperAdmin ? $this->input('center_id') : $authUser->center_id;
                    if ($centerId) {
                        $query->where('center_id', $centerId);
                    }
                    // Le domaine n'est plus un filtre de validation (champ interne).
                }),
            ],
            // Compatibilité optionnelle si tableau domains fourni
            'domains' => ['nullable', 'array'],
            'domains.*' => ['string', Rule::exists('domains', 'slug')],
        ];

        // Center_id : obligatoire pour Super Admin créant un professeur
        if ($isSuperAdmin) {
            $rules['center_id'] = [
                'required',
                'integer',
                Rule::exists('centers', 'id'),
            ];
        }
        // Pour Admin Centre : center_id est forcé côté serveur dans le contrôleur (pas dans le formulaire)

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Le nom est obligatoire.'),
            'email.required' => __('L\'adresse e-mail est obligatoire.'),
            'email.unique' => __('Cette adresse e-mail est déjà utilisée.'),
            'password.required' => __('Le mot de passe est obligatoire.'),
            'password.min' => __('Le mot de passe doit comporter au moins 8 caractères.'),
            'password.confirmed' => __('La confirmation du mot de passe ne correspond pas.'),
            'role.required' => __('Le rôle est obligatoire.'),
            'role.in' => __('Ce rôle n\'est pas autorisé.'),
            'status.required' => __('Le statut est obligatoire.'),
            'status.in' => __('Le statut doit être "active" ou "inactive".'),
            'center_id.exists' => __('Le centre sélectionné n\'existe pas.'),
        ];
    }
}
