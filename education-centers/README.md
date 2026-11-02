# 🎓 Education Centers — Plateforme de Gestion Multi-Centres d'Éducation & de Formation

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13" />
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-v4.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS v4" />
  <img src="https://img.shields.io/badge/Alpine.js-v3.x-8BC0D0?style=for-the-badge&logo=alpinedotjs&logoColor=white" alt="Alpine.js" />
  <img src="https://img.shields.io/badge/Tests-490%20Passed%20(100%25)-success?style=for-the-badge&logo=phpunit&logoColor=white" alt="Tests" />
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License" />
</p>

---

## 📌 Présentation Générale

**Education Centers** est une solution web complète, moderne et évolutive conçue pour la gestion administrative, pédagogique, logistique et financière d'établissements d'enseignement, d'écoles privées, d'instituts de langues et d'organismes de formation opérant sur **un ou plusieurs centres** (ex: Berlin, Munich, etc.).

L'application offre une séparation stricte des rôles et des données, un tableau de bord financier de haut niveau, des portails dédiés pour chaque acteur, une génération automatisée de documents officiels PDF (bulletins, certificats, factures) et une interface soignée avec support bilingue/trilingue (Français, Anglais, Arabe avec affichage RTL) ainsi qu'un mode sombre/clair instantané.

---

## ✨ Fonctionnalités Principales

### 🌐 1. Site Vitrine & Authentification Premium
- **Site public immersif** : Présentation des formations, catalogue des centres et formulaire de contact.
- **Background Aurora 3D & Mouse Follow Waves** :
  - Décor géométrique de fond haute fidélité (anneaux 3D, sphères de verre et particules) parfaitement stable et fixe.
  - Couche interactive fluide suivant les mouvements de la souris avec rémanence d'ondes lumineuses et déformations organiques sans aucun blocage d'interactions (`pointer-events: none`).
- **Page de Connexion Redesignée** :
  - Carte 2 colonnes en verre acrylique (*glassmorphism*), illustration académique 3D.
  - Bascule instantanée **Light Mode / Dark Mode**.
  - Sélecteur de langue dynamique (Français, Anglais, Arabe).
  - Affichage/masquage sécurisé du mot de passe et option "Se souvenir de moi".

---

### 👥 2. Espaces Utilisateurs par Rôle

#### 👑 Super Administrateur (Global)
- **Vision multi-centres consolidée** : Accès global à tous les centres avec sélecteur de contexte rapide.
- **Gestion des centres** : Création et configuration des centres (adresses, devises, fuseaux horaires, directeurs).
- **Domaines de formation** : Définition des filières, programmes et cursus transversaux.
- **Gestion des stagiaires & conventions** : Suivi avancé des parcours de stage et conventions d'entreprises.
- **Pilotage financier global** : Synthèse du chiffre d'affaires, taux de recouvrement, encaissements et impayés consolidés.
- **Journal d'audit de sécurité** : Traçabilité des actions sensibles et connexions.

#### 🏢 Administrateur de Centre
- **Gestion pédagogique locale** : Formations, classes, salles (capacités et équipements), sessions et affectation des professeurs.
- **Inscriptions & Apprenants** : Traitement des dossiers d'inscription, validation des statuts et pièces justificatives.
- **Emplois du temps & Planning** : Création et visualisation des plannings hebdomadaires avec détection des conflits de salles.
- **Suivi des présences** : Feuilles d'émargement quotidiennes, statistiques d'assiduité et alertes d'absentéisme.
- **Gestion financière du centre** :
  - Facturation automatique et manuelle des frais de scolarité.
  - Enregistrement des règlements multi-modes (Espèces, Virement bancaire, Carte bancaire, Chèque).
  - Émission de reçus et factures au format PDF.
  - Gestion des salaires des professeurs et calcul des rémunérations horaires.

#### 👨‍🏫 Portail Professeur
- **Tableau de bord enseignant** : Prochains cours, cours du jour et alertes pédagogiques.
- **Appel & Présences en direct** : Saisie rapide de l'appel pour chaque séance (Présent, Absent justifié, Absent injustifié, Retard).
- **Carnet de notes & Évaluations** : Création d'évaluations (contrôles continus, partiels), saisie des notes avec calcul automatique des moyennes.
- **Emploi du temps personnalisé** : Vue calendrier filtrée sur les cours assignés.
- **Fiches de rémunération** : Consultation de l'historique des heures effectuées et des salaires validés.
- **Messagerie interne** : Échanges directs avec les étudiants et l'administration.

#### 🎓 Portail Étudiant / Stagiaire
- **Espace apprenant complet** : Vue d'ensemble de la scolarité, progression des cours et événements à venir.
- **Planning & Assiduité** : Emploi du temps hebdomadaire, historique des présences et justificatifs d'absence.
- **Résultats & Bulletins** : Consultation des notes obtenues, moyennes par matière et téléchargement du bulletin scolaire officiel.
- **Certificats & Attestations** : Génération et téléchargement en 1 clic d'attestations de scolarité et certificats de fin d'études avec code de vérification.
- **Factures & Paiements** : Consultation du solde, détail des échéances, historique des paiements et reçus PDF.
- **Espace documentaire & Messagerie** : Supports de cours partagés et communication avec les enseignants.

---

### 🌍 3. Internationalisation & Accessibilité (i18n & a11y)
- **Support multilingue natif** :
  - 🇫🇷 Français (`fr`)
  - 🇬🇧 Anglais (`en`)
  - 🇸🇦 Arabe (`ar` avec bascule automatique du sens de lecture en **RTL** — *Right-to-Left*).
- **Sélecteur de langue instantané** mémorisant la préférence en session et profil utilisateur.
- **Thème Sombre / Clair persistant** via LocalStorage et classe `.dark`.
- Respect des normes d'accessibilité : support de `prefers-reduced-motion` et navigation clavier.

---

## 🛠️ Stack Technique

| Domaine | Technologies |
|---|---|
| **Backend** | **PHP 8.3+**, **Laravel 13.x**, Eloquent ORM, Seeders & Factories |
| **Frontend** | **Blade Templates**, **Tailwind CSS v4**, **Alpine.js 3**, Vanilla JS Canvas |
| **Build Tools** | **Vite 8**, PostCSS |
| **Base de Données** | **MySQL 8.0+** / **MariaDB** (Production & Dév) / **SQLite** (In-Memory Tests) |
| **Génération PDF** | `barryvdh/laravel-dompdf`, `mpdf/mpdf` |
| **Qualité & Tests** | **PHPUnit 12**, **490 tests automatisés** (Feature & Unit) |

---

## 📋 Prérequis

Avant de configurer le projet, vérifiez les prérequis suivants :

- **PHP 8.3 ou supérieur**
  - Extensions PHP requises : `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`, `fileinfo`
- **Composer (v2.x)**
- **Node.js (v18.x+, recommandé v20+) & NPM**
- **MySQL (v8.0+)** ou **MariaDB** (ou SQLite)
- **Git**

---

## 🚀 Guide d'Installation Pas à Pas

### 1. Cloner le dépôt et entrer dans le dossier

```bash
git clone https://github.com/Mohammed-Sadiki/education-centers.git
cd education-centers
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer les variables d'environnement (`.env`)

Copiez le fichier d'exemple :

- **Windows (PowerShell) :**
  ```powershell
  copy .env.example .env
  ```
- **Linux / macOS :**
  ```bash
  cp .env.example .env
  ```

Générez la clé applicative Laravel :

```bash
php artisan key:generate
```

### 4. Configurer la Base de Données

Créez une base de données vide (ex: `education_centers`) dans votre serveur MySQL :

```sql
CREATE DATABASE education_centers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ajustez ensuite les paramètres de connexion dans votre fichier `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=education_centers
DB_USERNAME=root
DB_PASSWORD=
```

*(Si vous préférez exécuter en mode autonome avec SQLite sans serveur MySQL, spécifiez `DB_CONNECTION=sqlite` et créez le fichier `database/database.sqlite`).*

### 5. Exécuter les migrations et créer les comptes de connexion Demo

**Recommandé pour un clone / `git pull`** — ne crée QUE les comptes de connexion
de démonstration (aucune donnée métier) :

```bash
php artisan migrate
php artisan db:seed --class=DemoAccountsSeeder
```

> ⚠️ À lancer **explicitement** sur une base de développement ou de démonstration
> uniquement. Le seeder est bloqué en production, idempotent (rejouable sans
> doublon) et ne modifie **jamais** les comptes réels ni les centres existants.

<details>
<summary>Alternative : jeu de démonstration complet (données métier incluses)</summary>

```bash
php artisan migrate --seed
```

Cette commande injecte également les centres, filières, utilisateurs et données
de démonstration complètes. À éviter si vous souhaitez saisir vous-même vos
vraies données depuis l'interface.

</details>

### 6. Créer le lien symbolique pour les uploads

Indispensable pour l'affichage des avatars, des documents administratifs et des justificatifs d'absence :

```bash
php artisan storage:link
```

### 7. Installer les dépendances Front-end et compiler

```bash
npm install
npm run build
```

Pour activer le serveur de développement Vite avec rechargement instantané (HMR) :
```bash
npm run dev
```

### 8. Lancer le serveur local Laravel

```bash
php artisan serve
```

> 💡 **Raccourci Windows :** Vous pouvez lancer le serveur en double-cliquant sur le script [`serve.bat`](file:///c:/Users/MSI/Desktop/projet%20final/education-centers/serve.bat) situé à la racine du projet.

Accédez ensuite à l'application dans votre navigateur : **`http://localhost:8000`** ou **`http://127.0.0.1:8000`**.

---

## 🔑 Comptes de Connexion de Démonstration (`DemoAccountsSeeder`)

Ces comptes sont créés **uniquement** par `php artisan db:seed --class=DemoAccountsSeeder`.
Ils vivent dans l'espace d'e-mails réservé `@education-centers.test` et n'entrent
jamais en collision avec des comptes réels. **Aucune donnée métier** (étudiant, stagiaire,
paiement, facture, dépense, salaire, inscription, classe, emploi du temps) n'est
créée : chaque développeur remplit ensuite son propre contenu depuis l'application.

Le mot de passe commun est lu depuis la variable d'environnement **non versionnée**
`DEMO_DEFAULT_PASSWORD` (fichier `.env`, voir `.env.example`) — jamais réinitialisé
si le compte existe déjà :

```env
DEMO_DEFAULT_PASSWORD=DemoEducation2026!
```

### Liste des e-mails Demo (29 comptes)

| Rôle | Adresse E-mail | Centre |
|---|---|---|
| 👑 Super Admin | `superadmin.demo@education-centers.test` | *Tous (Global)* |
| 🏢 Admin Centre | `admin.tanger.demo@education-centers.test` | Tanger |
| 🏢 Admin Centre | `admin.agadir.demo@education-centers.test` | Agadir |
| 🏢 Admin Centre | `admin.essaouira.demo@education-centers.test` | Essaouira |
| 🏢 Admin Centre | `admin.tetouane.demo@education-centers.test` | Tétouane |
| 👨‍🏫 Professeurs (6/centre) | `prof.demo.{centre}.{domaine}@education-centers.test` | chacun son centre |

`{centre}` ∈ `tanger`, `agadir`, `essaouira`, `tetouane` —
`{domaine}` ∈ `sante`, `informatique`, `hotellerie`, `maconnerie`, `design-interieur`, `design-graphique`.

Exemple complet pour Tanger : `prof.demo.tanger.sante@education-centers.test`,
`prof.demo.tanger.informatique@education-centers.test`, etc.

### Isolation vérifiée

- Un Admin Centre Demo ne voit que les professeurs et données **de son centre**.
- Le Super Admin Demo n'est rattaché à aucun centre (accès global).
- Un Professeur Demo est rattaché à son centre et à son domaine d'enseignement.

### Installation complète pour un collègue

```bash
git clone <url-du-depot>
cd education-centers
composer install
npm install
cp .env.example .env        # puis ajuster DB_* et DEMO_DEFAULT_PASSWORD
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan db:seed --class=DemoAccountsSeeder
npm run build               # ou : npm run dev
php artisan serve --port=8000
```

Puis se connecter sur **http://localhost:8000** avec l'un des comptes Demo et le
mot de passe `DEMO_DEFAULT_PASSWORD`.

> ✅ Ces garanties sont couvertes par des tests automatisés :
> `php artisan test --filter=DemoAccountsSeederTest`

<details>
<summary>Comptes de l'ancien jeu de démonstration complet (<code>php artisan migrate --seed</code>) — données métier incluses</summary>

Ils utilisent le mot de passe `EducationAdmin2026!` et incluent des données métier :

| Rôle | Adresse E-mail | Centre Associé |
|---|---|---|
| 👑 **Super Admin** | `admin@education-centers.de` | *Tous (Global)* |
| 🏢 **Admin Centre Berlin** | `admin.berlin@education-centers.de` | Berlin |
| 🏢 **Admin Centre Munich** | `admin.munich@education-centers.de` | Munich |
| 🎓 **Élève Berlin** | `eleve.berlin@education-centers.de` | Berlin |
| 🎓 **Élève Munich** | `eleve.munich@education-centers.de` | Munich |

</details>

---

## 🧪 Tests Automatisés & Assurance Qualité

Le projet intègre une suite de tests rigoureuse exécutée automatiquement sur une base SQLite en mémoire pour garantir une isolation totale et une vitesse d'exécution optimale.

### Exécuter l'ensemble des tests

```bash
php artisan test
```

**Résultat :**
```text
Tests:    490 passed (1741 assertions)
Duration: ~20s
Status:   100% GREEN
```

### Exécuter des suites ciblées

- **Authentification & Rôles :**
  ```bash
  php artisan test --filter=Auth
  ```
- **Module Financier & Facturation :**
  ```bash
  php artisan test --filter=Finance
  ```
- **Gestion des Stagiaires :**
  ```bash
  php artisan test --filter=Stagiaire
  ```
- **Portails Enseignants & Étudiants :**
  ```bash
  php artisan test --filter=Portal
  ```

---

## 📂 Architecture & Organisation du Code

```text
education-centers/
├── app/
│   ├── Helpers/                 # Fonctions utilitaires (devise, locale, labels, contexte)
│   ├── Http/
│   │   ├── Controllers/         # Contrôleurs métiers (32 contrôleurs spécialisés)
│   │   ├── Middleware/          # Contrôle d'accès RBAC, sélection de centre, locale
│   │   └── Requests/            # Form Requests validant les données entrantes
│   ├── Models/                  # Modèles Eloquent (User, Center, Student, Teacher, etc.)
│   └── Policies/                # Autorisations et politiques d'accès
├── database/
│   ├── factories/               # Générateurs de données factices pour les tests
│   ├── migrations/              # Schémas et structures des tables
│   └── seeders/                 # Données de démonstration réalistes
├── lang/                        # Dictionnaires de traduction (fr, en, ar - RTL)
├── public/                      # Assets compilés, point d'entrée HTTP (index.php)
├── resources/
│   ├── css/                     # Tailwind CSS v4 et règles personnalisées
│   ├── js/                      # Alpine.js, composants canvas (Aurora Waves 3D)
│   └── views/                   # Vues Blade (auth, public, admin, teacher, student)
├── routes/
│   ├── web.php                  # Déclaration des routes publiques et applicatives
│   └── console.php              # Commandes console Artisan
├── storage/                     # Fichiers générés, logs, uploads utilisateurs
└── tests/
    ├── Feature/                 # Tests fonctionnels des parcours utilisateurs
    └── Unit/                    # Tests unitaires des helpers et modèles
```

---

## 🔧 Commandes Utiles au Quotidien

```bash
# Lancer les workers / dev server combiné
composer run dev

# Recompiler les assets pour la production
npm run build

# Vider tous les caches applicatifs (vue, config, routes)
php artisan optimize:clear

# Lister toutes les routes de l'application
php artisan route:list

# Vérifier le formatage du code avec Laravel Pint
./vendor/bin/pint --test
```

---

## 📄 Licence

Ce projet est sous licence **MIT**. Vous êtes libre de l'utiliser, le modifier et le distribuer dans le respect des termes de la licence.
