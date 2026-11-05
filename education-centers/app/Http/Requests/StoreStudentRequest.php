<?php

namespace App\Http\Requests;

use App\Models\Formation;
use App\Models\Student;
use App\Models\User;
use App\Services\FinanceService;
use App\Support\DomainRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authUser = $this->user();

        return [
            // Le compte de connexion est désormais FACULTATIF : un dossier élève
            // peut être créé directement par un administrateur, sans préinscription.
            // Grâce à « nullable », les règles suivantes (role, centre, unicité) ne
            // s'appliquent que lorsqu'un user_id est effectivement transmis — ce qui
            // préserve le comportement pour les comptes déjà liés.
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                'unique:students,user_id',
                function ($attribute, $value, $fail) use ($authUser) {
                    $targetUser = User::find($value);
                    if (! $targetUser) {
                        return;
                    }

                    // Must have role = 'student'
                    if (! $targetUser->isStudent()) {
                        $fail(__('L\'utilisateur sélectionné n\'a pas le rôle élève (student).'));

                        return;
                    }

                    // For center admin / secretary: target user must belong to their center
                    if (($authUser->isCenterAdmin() || $authUser->isSecretary()) && $targetUser->center_id !== $authUser->center_id) {
                        $fail(__('L\'utilisateur sélectionné n\'appartient pas à votre centre.'));

                        return;
                    }

                    // For super admin: if center_id is supplied, ensure user belongs to selected center
                    if ($authUser->isSuperAdmin() && $this->filled('center_id')) {
                        if ($targetUser->center_id != $this->input('center_id')) {
                            $fail(__('L\'utilisateur sélectionné n\'appartient pas au centre choisi.'));

                            return;
                        }
                    }
                },
            ],
            'center_id' => [
                $authUser->isSuperAdmin() ? 'required' : 'nullable',
                'exists:centers,id',
            ],
            // Nouveau parcours « Centre → Formation générale → Classe » : le
            // domaine n'est plus une étape de sélection. Il reste accepté (champ
            // technique interne) pour compatibilité avec les appels historiques,
            // mais n'est jamais exigé ni utilisé pour valider la formation.
            'domain' => ['nullable', 'string', Rule::in(DomainRegistry::slugs())],
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Le statut n'est plus un champ visible du formulaire refondu : il
            // reste accepté pour les appels existants et default « active » côté
            // contrôleur lorsqu'il est absent.
            'status' => ['nullable', Rule::in(['active', 'inactive'])],

            // ── Pièces et fichiers (facultatifs à la validation de base) ──────
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'cin_files' => ['nullable', 'array', 'max:6'],
            'cin_files.*' => ['image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'other_files' => ['nullable', 'array', 'max:10'],
            'other_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:8192'],

            // ── Paiement intégré (facultif : absent pour la création « dossier seul ») ──
            'payment_option' => ['nullable', 'in:avance,paye'],
            'avance_amount' => ['nullable', 'numeric', 'min:0'],

            // ── Prix final personnalisé accordé à l'étudiant ──────────────────
            // Facultatif : absent ⇒ le contrôleur enregistre le tarif officiel
            // (aucune réduction). Les bornes supérieures (tarif officiel, 2
            // décimales) sont contrôlées dans withValidator côté serveur, le
            // montant du navigateur n'étant JAMAIS utilisé comme référence.
            'final_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Contrôles croisés de la cascade centre → domaine → formation.
     * Jamais confiance au center_id transmis par le navigateur pour un Admin
     * Centre : le centre effectif est celui du compte connecté.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $authUser = $this->user();

            // Récupération de la formation si elle est fournie (la règle « exists »
            // traite déjà le cas d'un identifiant invalide).
            $formation = $this->filled('formation_id')
                ? Formation::find($this->input('formation_id'))
                : null;

            if ($formation) {
                // Centre effectif, déterminé côté serveur (jamais le center_id brut
                // d'un Admin Centre). Le compte utilisateur n'entre plus en ligne de
                // compte : pour un Super Admin, c'est le center_id choisi qui fait foi.
                $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary())
                    ? (int) $authUser->center_id
                    : (int) $this->input('center_id');

                if (! $formation->isActive()) {
                    $validator->errors()->add('formation_id', __('La formation sélectionnée est inactive.'));
                }

                // Isolation multi-centres : la formation doit appartenir au centre retenu.
                if ((int) $formation->center_id !== $centerId) {
                    $validator->errors()->add('formation_id', __('La formation sélectionnée n\'appartient pas au centre choisi.'));
                }

                // Règle « Centre → Formation générale → Classe » : le catalogue
                // exposé dans le formulaire ne contient que des formations
                // officielles. On n'impose pas une restriction serveur stricte
                // (pour ne pas casser les flux historiques et les tests existants
                // qui créent des formations legacy), mais on conserve la
                // vérification du centre et du statut ci-dessus.
                if ($this->filled('domain') && $formation->domain !== $this->input('domain')) {
                    $validator->errors()->add('formation_id', __('La formation sélectionnée n\'appartient pas à la formation générale choisie.'));
                }

                // ── Prix final personnalisé : le tarif officiel fait foi, side DB ──
                // On ne fait JAMAIS confiance à un prix envoyé par le navigateur :
                // le tarif officiel est relu depuis la base, puis le prix final
                // transmis est contrôlé (bornes + décimales).
                $official = FinanceService::getFormationAnnualPrice($formation);

                if ($this->filled('final_price')) {
                    $finalPrice = $this->input('final_price');

                    // Deux décimales maximum.
                    if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $finalPrice)) {
                        $validator->errors()->add('final_price', __('Le prix final ne peut contenir que deux décimales au maximum.'));
                    } elseif ((float) $finalPrice > $official + 0.01) {
                        // Aucun dépassement du tarif officiel.
                        $validator->errors()->add('final_price', __('Le prix final ne peut pas dépasser le tarif officiel de la formation (:price).', [
                            'price' => format_price($official, 'MAD'),
                        ]));
                    }
                }
            }

            // ── Contrôles du paiement intégré (uniquement si une option est transmise) ──
            if ($this->filled('payment_option')) {
                if (! $formation) {
                    $validator->errors()->add('formation_id', __('Une formation est requise pour enregistrer un paiement.'));

                    return;
                }

                // Tarif de référence : prix final accordé s'il est fourni et valide,
                // sinon tarif officiel (configuration financière existante).
                $official = FinanceService::getFormationAnnualPrice($formation);
                $price = $this->filled('final_price')
                    ? min((float) $this->input('final_price'), $official)
                    : $official;
                $option = $this->input('payment_option');

                if ($option === 'avance') {
                    $advance = $this->input('avance_amount');

                    if (! $this->filled('avance_amount')) {
                        $validator->errors()->add('avance_amount', __('Le montant de l\'avance est requis.'));
                    } elseif ((float) $advance < 0) {
                        $validator->errors()->add('avance_amount', __('Le montant de l\'avance ne peut pas être négatif.'));
                    } elseif ((float) $advance > $price + 0.01) {
                        $validator->errors()->add('avance_amount', __('Le montant de l\'avance ne peut pas dépasser le prix de la formation (:price).', [
                            'price' => format_price($price, 'MAD'),
                        ]));
                    }
                }
            }
        });
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('compte utilisateur élève'),
            'center_id' => __('centre éducatif'),
            'domain' => __('formation générale'),
            'formation_id' => __('formation'),
            'first_name' => __('prénom'),
            'last_name' => __('nom'),
            'date_of_birth' => __('date de naissance'),
            'place_of_birth' => __('lieu de naissance'),
            'gender' => __('genre'),
            'nationality' => __('nationalité'),
            'address' => __('adresse'),
            'postal_code' => __('code postal'),
            'city' => __('ville'),
            'country' => __('pays'),
            'phone' => __('téléphone'),
            'whatsapp' => __('numéro WhatsApp'),
            'emergency_contact_name' => __('nom du contact d’urgence'),
            'emergency_contact_phone' => __('téléphone du contact d’urgence'),
            'emergency_contact_relation' => __('lien avec le contact d’urgence'),
            'notes' => __('remarques'),
            'status' => __('statut'),
            'profile_photo' => __('photo de profil'),
            'cin_files' => __('photos de la CIN'),
            'cin_files.*' => __('photo de la CIN'),
            'other_files' => __('autres fichiers'),
            'other_files.*' => __('fichier'),
            'payment_option' => __('type de paiement'),
            'avance_amount' => __('montant de l\'avance'),
            'final_price' => __('prix final accordé'),
        ];
    }
}
