{{--
    Validation côté client des formulaires stagiaires (création et édition).

    Règle reflétée du serveur : toutes les informations affichées sont
    obligatoires, à l'exception de la section « Autres fichiers » (fichiers
    multiples facultatifs, mais format et taille vérifiés s'ils sont fournis).

    Le contrôle ne se limite pas aux attributs HTML `required` : formats e-mail,
    téléphone, montant, date, extensions et taille de fichier sont vérifiés, et
    la soumission est bloquée tant qu'une erreur reste affichée.
--}}
<script>
(function () {
    'use strict';

    const MESSAGES = {
        requiredField: @js(__('Ce champ est obligatoire.')),
        missingFile: @js(__('Veuillez sélectionner un fichier.')),
        invalidEmail: @js(__('Cette adresse e-mail est invalide.')),
        invalidPhone: @js(__('Ce numéro de téléphone est invalide.')),
        invalidNumber: @js(__('Ce montant doit être un nombre valide.')),
        invalidDate: @js(__('Cette date est invalide.')),
        badFileFormat: @js(__('Format de fichier non autorisé.')),
        tooLargeFile: @js(__('Fichier trop volumineux : taille maximale dépassée.')),
        incompleteRow: @js(__('Cette ligne facultative est commencée mais incomplète : tous ses champs sont nécessaires.')),
        summary: @js(__('Veuillez corriger les champs en rouge avant d\'enregistrer le stagiaire.')),
    };

    const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const PHONE_PATTERN = /^[0-9+().\s-]+$/;
    const NUMBER_PATTERN = /^-?\d{1,9}([.,]\d{1,2})?$/;
    const reduceMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const valueOf = (field) => (field.value ? String(field.value).trim() : '');

    /**
     * Contrôle un champ : `required` force la présence, le format est vérifié
     * dès qu'une valeur est présente. Les fichiers multiples (« Autres fichiers »)
     * sont contrôlés un à un.
     */
    function checkField(field, required) {
        if (field.type === 'file') {
            const files = Array.from(field.files || []);

            if (required && files.length === 0) {
                return MESSAGES.missingFile;
            }
            if (files.length === 0) {
                return null;
            }

            const accepted = (field.getAttribute('accept') || '')
                .split(',').map((extension) => extension.trim().toLowerCase()).filter(Boolean);

            const maxKb = parseInt(field.getAttribute('data-max-kb') || '0', 10);

            for (const file of files) {
                if (accepted.length && !accepted.some((extension) => file.name.toLowerCase().endsWith(extension))) {
                    return MESSAGES.badFileFormat;
                }

                if (maxKb > 0 && file.size > maxKb * 1024) {
                    return MESSAGES.tooLargeFile;
                }
            }

            return null;
        }

        const value = valueOf(field);

        if (required && value === '') {
            return MESSAGES.requiredField;
        }
        if (value === '') {
            return null;
        }
        if (field.type === 'email' && !EMAIL_PATTERN.test(value)) {
            return MESSAGES.invalidEmail;
        }
        if (field.type === 'tel' && !PHONE_PATTERN.test(value)) {
            return MESSAGES.invalidPhone;
        }
        if (field.type === 'number' && !NUMBER_PATTERN.test(value)) {
            return MESSAGES.invalidNumber;
        }
        if (field.hasAttribute('data-numeric') && !/^\d{4}$/.test(value)) {
            return MESSAGES.invalidNumber;
        }
        if (field.type === 'date' && Number.isNaN(Date.parse(value))) {
            return MESSAGES.invalidDate;
        }

        return null;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');

        const holder = field.closest('div') || field.parentElement;

        if (holder) {
            holder.querySelectorAll(':scope > [data-stagiaire-error]').forEach((node) => node.remove());
        }
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        const holder = field.closest('div') || field.parentElement;

        if (!holder) {
            return;
        }

        const error = document.createElement('p');
        error.className = 'ec-error text-sm';
        error.dataset.stagiaireError = '1';
        error.textContent = message;
        holder.appendChild(error);
    }

    function clearFormErrors(form) {
        form.querySelectorAll('[data-stagiaire-error]').forEach((node) => node.remove());
        form.querySelectorAll('.is-invalid').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });
        const summary = form.querySelector('#stagiaire-form-summary');

        if (summary) {
            summary.remove();
        }
    }

    function showSummary(form) {
        const box = document.createElement('div');
        box.id = 'stagiaire-form-summary';
        box.setAttribute('role', 'alert');
        box.className = 'mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300';
        box.textContent = MESSAGES.summary;
        form.insertBefore(box, form.firstChild);
    }

    /**
     * Une ligne facultative est « commencée » dès qu'un champ utile (hors type
     * présélectionné) est renseigné ou qu'un fichier y a été déposé.
     */
    function rowIsStarted(fields) {
        return fields.some((field) => {
            if (field.hasAttribute('data-row-preset')) {
                return false;
            }

            return field.type === 'file' ? field.files.length > 0 : valueOf(field) !== '';
        });
    }

    function validateOptionalRows(form, report) {
        const groups = new Map();

        form.querySelectorAll('[data-row-group]').forEach((row) => {
            const name = row.getAttribute('data-row-group') || 'rows';

            if (!groups.has(name)) {
                groups.set(name, []);
            }

            groups.get(name).push(row);
        });

        groups.forEach((rows) => {
            rows.forEach((row) => {
                const fields = Array.from(row.querySelectorAll('[data-row-field]'));

                if (fields.length === 0 || !rowIsStarted(fields)) {
                    // Ligne vide par défaut : ignorée, elle ne bloque pas la soumission.
                    return;
                }

                fields.forEach((field) => {
                    const empty = field.type === 'file'
                        ? field.files.length === 0
                        : valueOf(field) === '';

                    if (empty) {
                        if (!field.hasAttribute('data-row-preset')) {
                            report(field, MESSAGES.incompleteRow);
                        }

                        return;
                    }

                    const error = checkField(field, false);

                    if (error) {
                        report(field, error);
                    }
                });
            });
        });
    }

    function validateForm(form) {
        clearFormErrors(form);

        let firstInvalid = null;

        const report = (field, message) => {
            showFieldError(field, message);

            if (!firstInvalid) {
                firstInvalid = field;
            }
        };

        form.querySelectorAll('[data-required]').forEach((field) => {
            if (field.disabled || field.type === 'hidden') {
                return;
            }

            const error = checkField(field, true);

            if (error) {
                report(field, error);
            }
        });

        // Fichiers facultatifs (« Autres fichiers ») : format et taille vérifiés
        // uniquement si des fichiers ont été sélectionnés.
        form.querySelectorAll('[data-optional-files]').forEach((field) => {
            const error = checkField(field, false);

            if (error) {
                report(field, error);
            }
        });

        validateOptionalRows(form, report);

        if (firstInvalid) {
            showSummary(form);
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        }

        return firstInvalid === null;
    }

    document.querySelectorAll('form[data-stagiaire-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!validateForm(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();

                return;
            }

            clearFormErrors(form);
        });

        // L'erreur d'un champ disparaît dès que l'utilisateur le corrige.
        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-required], [data-row-field], [data-optional-files]')) {
                clearFieldError(event.target);
            }
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-required], [data-row-field], [data-optional-files]')) {
                clearFieldError(event.target);
            }
        });
    });
})();
</script>
