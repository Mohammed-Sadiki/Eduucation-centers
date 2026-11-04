@props(['f'])

{{--
    Coquille unique du formulaire élève, partagée par « Ajouter un élève »
    (référence visuelle) et « Modifier le dossier élève » : même conteneur,
    même en-tête, même bandeau de centre, même grille deux colonnes, mêmes
    boutons, même composant Alpine. Seules les valeurs préremplies et
    l'opération transmise changent selon le mode.
--}}
@php
    $mode = $f['mode'] ?? 'create';
    $student = $f['student'] ?? null;
    $isCenterAdmin = $f['isCenterAdmin'] ?? false;
    $forcedCenter = $f['forcedCenter'] ?? null;
    $currencySymbol = $f['currencySymbol'] ?? config('currency.symbol', 'DH');
    $formationsData = $f['formations'] ?? [];

    // Formation retenue à l'affichage : old() d'abord (erreur de validation),
    // sinon la pré-sélection (création : ?formation_id= / modification :
    // formation de l'inscription enregistrée).
    $preselected = $f['preselectedFormationId'] ?? null;
    $initialFormationId = (string) old('formation_id', (string) ($preselected ?? ''));

    // Prix final : saisie conservée après erreur, sinon valeur déjà accordée
    // (modification) ; vide en création, l'Alpine le calera sur le tarif officiel.
    $initialFinalPrice = (string) old('final_price', $f['defaultFinalPrice'] ?? '');

    // Centre verrouillé : en modification, le catalogue proposé côté client est
    // restreint au centre du dossier (aucune réaffectation possible).
    $lockedCenterId = $mode === 'edit' ? (int) ($student?->center_id ?? 0) ?: null : null;

    $bannerMessage = $f['bannerMessage'] ?? __(
        $mode === 'edit'
            ? 'Le centre du dossier ne change pas : inscriptions, paiements et documents lui restent rattachés.'
            : 'Le centre est déterminé automatiquement à partir de votre compte et ne peut pas être modifié.'
    );

    // Nom du centre affiché dans le bandeau (modification : centre du dossier).
    $centerName = $f['centerName'] ?? ($forcedCenter?->translated('name') ?? $forcedCenter?->name);
@endphp

<div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6"
     x-data="enrollmentFormApp({
         formations: @js($formationsData),
         isCenterAdmin: @js($isCenterAdmin),
         forcedCenterId: @js($f['forcedCenterId'] ?? null),
         lockedCenterId: @js($lockedCenterId),
         currencySymbol: @js($currencySymbol),
         formationId: @js($initialFormationId),
         finalPriceInput: @js($initialFinalPrice),
         paymentOption: @js(old('payment_option', '')),
         avanceAmount: @js((float) old('avance_amount', 0)),
         paidByFormation: @js($f['paidByFormation'] ?? []),
     })">

    {{-- Titre de page --}}
    <x-ui.page-header
        :title="$f['title']"
        :description="$f['headerDescription']" />

    {{-- Bandeau centre automatique (Admin Centre) : le centre n'est pas modifiable --}}
    @if($isCenterAdmin || $mode === 'edit')
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-base text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
            <x-icon name="building" class="h-6 w-6 shrink-0" />
            <div>
                {{ __('Centre') }} : <strong>{{ $centerName }}</strong>
                — {{ $bannerMessage }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $f['action'] }}" enctype="multipart/form-data"
          id="{{ $f['formId'] ?? 'form-create-student' }}">
        @csrf
        @if(($f['method'] ?? 'POST') !== 'POST')
            @method($f['method'])
        @endif

        <x-ui.form-grid>
            {{ $slot }}

            {{-- Actions --}}
            <div class="flex flex-wrap items-center justify-end gap-3 pb-6 lg:col-span-2">
                <a href="{{ $f['cancelUrl'] }}" class="ec-btn-secondary text-base">{{ __('Annuler') }}</a>
                <button type="submit" class="ec-btn-primary px-6 text-base" id="{{ $f['submitId'] ?? 'btn-submit-student' }}">
                    <x-icon name="check" class="h-5 w-5" />
                    {{ $f['submitLabel'] }}
                </button>
            </div>
        </x-ui.form-grid>
    </form>
</div>

@push('scripts')
<script>
    function enrollmentFormApp(config) {
        return {
            formations: config.formations,
            isCenterAdmin: config.isCenterAdmin,
            forcedCenterId: config.forcedCenterId,
            // En modification : centre du dossier, jamais modifiable côté client.
            lockedCenterId: config.lockedCenterId || null,
            currencySymbol: config.currencySymbol || 'DH',

            centerId: document.getElementById('center_id')?.value
                || (config.lockedCenterId
                    ? String(config.lockedCenterId)
                    : (config.isCenterAdmin ? String(config.forcedCenterId) : '')),

            // Valeurs initiales : champ vide en création, valeur enregistrée ou
            // saisie conservée (erreur de validation) en modification.
            formationId: config.formationId || '',
            visibleFormationCount: 0,

            // Prix final accordé : initialisé sur le tarif officiel de la formation
            // retenue (ou valeur conservée après échec de validation).
            finalPriceInput: config.finalPriceInput ?? '',

            paymentOption: config.paymentOption || '',
            avanceAmount: Number(config.avanceAmount) || 0,

            // Versements déjà encaissés, par formation (0 en création) : le solde
            // affiché tient compte de l'historique sans jamais le réinitialiser.
            paidByFormation: config.paidByFormation || {},

            profileName: '',
            profilePreview: '',
            cinFiles: [],
            otherFiles: [],

            get effectiveCenterId() {
                if (this.lockedCenterId) return String(this.lockedCenterId);

                return this.isCenterAdmin ? String(this.forcedCenterId) : String(this.centerId || '');
            },
            get availableFormations() {
                // Nouveau parcours : le select propose directement les six
                // formations générales officielles du centre (aucune étape Domaine).
                const center = this.effectiveCenterId;
                if (center === '') return this.formations;
                return this.formations.filter(f => String(f.center_id) === center);
            },
            get selectedFormation() {
                return this.formations.find(f => String(f.id) === String(this.formationId)) || null;
            },
            get selectedClasses() {
                return this.selectedFormation ? (this.selectedFormation.classes || []) : [];
            },
            get formationPrice() {
                return this.selectedFormation ? Number(this.selectedFormation.price) : 0;
            },
            get paidAmount() {
                if (!this.formationId) return 0;

                return Number(this.paidByFormation[String(this.formationId)] || 0);
            },
            // Prix final réellement dû : borné à [0, tarif officiel]. Une valeur
            // vide ou invalide retombe sur le tarif officiel (aucune réduction).
            get finalPayable() {
                const raw = this.finalPriceInput;
                if (raw === '' || raw === null || raw === undefined) return this.formationPrice;
                const n = Number(raw);
                if (Number.isNaN(n)) return this.formationPrice;
                return Math.max(0, Math.min(n, this.formationPrice));
            },
            get discount() {
                return Math.max(0, this.formationPrice - this.finalPayable);
            },
            get remaining() {
                // Solde réellement dû : le prix final diminue des versements déjà
                // enregistrés (néant en création).
                const base = Math.max(0, this.finalPayable - this.paidAmount);
                if (this.paymentOption === 'paye') return 0;
                if (this.paymentOption === 'avance') return Math.max(0, base - (Number(this.avanceAmount) || 0));
                return base;
            },

            money(value) {
                const n = Number(value) || 0;
                return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + this.currencySymbol;
            },

            init() {
                const centerSelect = document.getElementById('center_id');
                centerSelect?.addEventListener('change', () => { this.centerId = centerSelect.value; this.applyCascade(); });
                if (this.isCenterAdmin && centerSelect) this.centerId = this.forcedCenterId;
                this.applyCascade();

                // Le prix final suit automatiquement le tarif officiel de la formation
                // choisie : on ne conserve jamais le prix personnalisé de la formation
                // précédente (§4).
                this.$watch('formationId', () => {
                    this.finalPriceInput = this.selectedFormation ? Number(this.selectedFormation.price) : '';
                });

                // Première synchronisation (formation pré-sélectionnée depuis la carte
                // ou après échec de validation) : ne jamais écraser une saisie déjà conservée.
                if (this.selectedFormation && (this.finalPriceInput === '' || this.finalPriceInput === null)) {
                    this.finalPriceInput = Number(this.selectedFormation.price);
                }
            },

            // Bornage immédiat de la saisie : jamais négatif, jamais supérieur au
            // tarif officiel (le backend revalide de toute façon ces bornes).
            clampFinalPrice() {
                if (this.finalPriceInput === '' || this.finalPriceInput === null) return;
                const n = Number(this.finalPriceInput);
                if (Number.isNaN(n)) return;
                this.finalPriceInput = Math.max(0, Math.min(n, this.formationPrice));
            },

            // Filtre par centre uniquement (Super Admin multi-centres). L'Admin
            // Centre reçoit déjà un catalogue filtré serveur.
            applyCascade() {
                const center = this.effectiveCenterId;
                const formationSelect = document.getElementById('formation_id');
                if (!formationSelect) return;
                let visible = 0;
                formationSelect.querySelectorAll('option[data-center-id]').forEach((option) => {
                    const match = center === '' || option.dataset.centerId === center;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match) visible++;
                });
                this.visibleFormationCount = visible;

                // Réinitialiser la formation si elle ne fait plus partie du
                // catalogue après changement de centre.
                const selected = formationSelect.selectedOptions[0];
                if (selected && selected.dataset.centerId && (selected.hidden || selected.disabled)) {
                    formationSelect.value = '';
                    this.formationId = '';
                }
            },

            onProfileChange(event) {
                const file = event.target.files[0];
                if (!file) { this.profileName = ''; this.profilePreview = ''; return; }
                this.profileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => { this.profilePreview = e.target.result; };
                reader.readAsDataURL(file);
            },

            addFiles(kind, event) {
                const input = event.target;
                const dt = new DataTransfer();
                this[kind + 'Files'].forEach(f => dt.items.add(f));
                Array.from(input.files).forEach(f => dt.items.add(f));
                this[kind + 'Files'] = Array.from(dt.files);
                input.files = dt.files;
                input.value = input.files.length ? input.value : '';
            },

            removeFile(kind, index) {
                const dt = new DataTransfer();
                this[kind + 'Files'].forEach((f, i) => { if (i !== index) dt.items.add(f); });
                this[kind + 'Files'] = Array.from(dt.files);
                const ref = kind === 'cin' ? this.$refs.cinInput : this.$refs.otherInput;
                if (ref) ref.files = dt.files;
            },
        };
    }
</script>
@endpush
