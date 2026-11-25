# SmartKiné — Plateforme de gestion d'un centre de kinésithérapie

Projet d'intégration **MDW 3 — ISET Sousse — AU 2026-2027** · Méthode **Agile Scrum (3 sprints)**
Équipe 2 : Kamel Brigui (chef d'équipe), Islem Dardouri, Melek Chabbi, Fadwa Ezzar — Encadrant : M. Chiheb Chaieb

| Partie | Technologie |
|---|---|
| Backend (API REST) | PHP 8.2+ · Symfony 7.4 · Doctrine ORM · LexikJWT · Symfony Mailer |
| Frontend | Angular 22 · Bootstrap 5 · Bootstrap Icons (responsive mobile-first, installable PWA) |
| Base de données | MySQL 8 (13 tables) |
| Outils | Git / GitHub (1 branche par sprint), XAMPP, Postman / Newman, Mailpit |

---

## 1. Installation (Windows + XAMPP)

**Prérequis :** XAMPP avec **MySQL démarré**, PHP 8.2 ou plus — celui de XAMPP convient (extensions `sodium`, `pdo_mysql`, `intl` activées dans `php.ini`), Composer, Node.js 22.22 ou plus (LTS conseillée), Git.

### Méthode rapide (2 commandes)

Dans PowerShell, à la racine du projet :

```powershell
powershell -ExecutionPolicy Bypass -File .\installer.ps1   # une seule fois
powershell -ExecutionPolicy Bypass -File .\demarrer.ps1    # à chaque fois
```

`installer.ps1` vérifie les outils, complète le `.env`, installe Composer et npm, génère les clés JWT, crée la base et les tables, puis propose les données de démo.
`demarrer.ps1` ouvre l'API (port 8000), Angular (port 4200), Mailpit s'il est présent, puis le navigateur.

### Méthode manuelle

```powershell
cd backend
copy .env.example .env      # si vous avez déjà un .env : ajoutez-y les variables manquantes de .env.example
composer install
$env:OPENSSL_CONF = "C:\xampp\php\extras\ssl\openssl.cnf"   # XAMPP : nécessaire pour générer les clés
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
php bin/console app:demo     # données de démonstration
php -S 127.0.0.1:8000 -t public
```

Dans un deuxième terminal :

```powershell
cd frontend
npm install
npm start                    # http://localhost:4200
```

Pas de données de démo ? Créez au moins le premier gérant :
`php bin/console app:create-gerant gerant@smartkine.tn Gerant123 Kamel Brigui`

### E-mails de test (facultatif)

XAMPP n'a pas de boîte e-mail de test. Téléchargez **Mailpit** pour Windows ([github.com/axllent/mailpit/releases](https://github.com/axllent/mailpit/releases), fichier `mailpit-windows-amd64.zip`). Placez `mailpit.exe` dans un dossier `tools/` à la racine du projet : `demarrer.ps1` le lance tout seul.
Les e-mails (confirmation de RDV, acceptation, validation du compte) s'affichent sur **http://localhost:8025**. Sans Mailpit, l'application fonctionne : elle indique seulement que l'e-mail n'a pas pu être envoyé.

### Tester toute l'API (80 requêtes, 120 vérifications)

Sur une base chargée avec `app:demo` :

- **Postman** : *Import* → `tests/SmartKine.postman_collection.json` → *Run collection* ;
- **ligne de commande** : `npx newman run tests/SmartKine.postman_collection.json`.

La collection rejoue tout le parcours (visiteur → gérant → kiné → patient → facturation → messagerie). Elle vérifie aussi les **droits de chaque rôle** : un patient qui appelle l'espace gérant reçoit 403, un kiné qui ouvre le dossier d'un patient qui n'est pas le sien reçoit 404, un token falsifié reçoit 401.

### Comptes de démonstration (`app:demo`)

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Gérant | gerant@smartkine.tn | Gerant123 |
| Kiné | melek@smartkine.tn | Kine12345 |
| Kiné | fadwa@smartkine.tn | Kine12345 |
| Patient | sami@test.tn | Patient123 |
| Patient (inscription en attente) | hedi@test.tn | Patient123 |

### Publier sur GitHub

```powershell
git push origin sprint-1 sprint-2 sprint-3
git checkout main
git merge sprint-3
git push origin main
```

---

## 2. Parcours principal (à montrer en soutenance)

1. **Visiteur** → *Prendre rendez-vous* → reçoit l'e-mail de confirmation (Mailpit, si installé).
2. **Gérant** → *Demandes de RDV* → accepte (e-mail envoyé avec le lien d'inscription).
3. **Visiteur** → *Créer mon compte* (même e-mail) → compte « en attente ».
4. **Gérant** → *Inscriptions* → valide · *Patients & affectations* → affecte un kiné.
5. **Kiné** → *Mes patients* → dossier médical, plan de rééducation, planification d'une série de séances.
6. **Patient** → *Mon planning* → demande un report (date proposée) → **Kiné** → *Demandes patients* → accepte.
7. **Gérant** → *Factures* → établit la facture depuis les séances → encaisse un paiement partiel.
8. **Patient** → *Mes factures* (reste à payer) · notifications 🔔 · messagerie avec son kiné.

---

## 3. Bilan par sprint

### Sprint 1 (09/11 → 15/11) — branche `sprint-1`

**Corrections du code existant**

| Problème | Correction |
|---|---|
| `getRoles()` ajoutait `ROLE_PATIENT` à **tous** les utilisateurs → contrôle d'accès cassé | Rôle déterminé par la classe (Gérant / Kiné / Patient) |
| Le formulaire de connexion Angular faisait un `console.log` sans appeler l'API | Appel de `/api/login`, stockage du JWT, redirection selon le rôle |
| Lien « Créer un compte » vers `/register` inexistant | Page d'inscription ajoutée (`/inscription`) |
| L'inscription créait un patient actif sans validation | Compte « en attente » + validation par le gérant + lien avec la demande de RDV acceptée |
| `access_control` : un seul niveau « connecté » | RBAC complet : `/api/gerant`, `/api/kine`, `/api/patient` |

**Livré :** modèle de données complet (héritage *Utilisateur → Gérant / Kiné / Patient*), migration MySQL,
US-01, US-02, US-03, US-04, US-05, US-13, US-21, US-22, US-23.

### Sprint 2 (16/11 → 22/11) — branche `sprint-2`

US-06, US-07, US-08, US-09, US-14, US-15, US-16, US-17, US-18, US-19, US-24, US-30
(affectation, agenda hebdomadaire avec séries de séances et détection des conflits de créneaux,
reports / annulations, dossier médical réservé au kiné traitant et au gérant, banque d'exercices avec images, plans de rééducation).

### Sprint 3 (23/11 → 30/11) — branche `sprint-3`

US-10, US-11, US-12, US-20, US-25, US-26, US-27, US-28, US-29
(facturation à partir des séances, paiements partiels et reste à payer calculé automatiquement, messagerie interne et diffusion,
notifications, tableau de bord statistique, optimisation mobile + manifeste PWA, commande de données de démo).

**→ Les 30 user stories du backlog sont couvertes et vérifiées par la collection Postman (`tests/`).**

### Règles de gestion retenues

- Prix d'une séance = somme des coûts de ses exercices (fixés par le gérant), sinon forfait de **40 DT**.
- Une séance n'est facturée qu'une fois ; une facture sans paiement peut être supprimée.
- Un paiement ne peut pas dépasser le reste à payer.
- Le patient peut demander un report ou une annulation uniquement pour une séance **planifiée** et **future**.
- Messagerie : patient ↔ gérant et son kiné ; kiné ↔ gérant, kinés et ses patients ; gérant ↔ tout le monde.

---

## 4. Écart avec le cahier des spécifications

Le cahier prévoyait **Symfony + Twig** (monolithe). L'équipe a démarré avec **Symfony (API REST + JWT) + Angular** :
cette architecture a été conservée. Le cahier a été mis à jour en conséquence :
**[docs/SmartKineCahierSpecifications-v3.pdf](docs/SmartKineCahierSpecifications-v3.pdf)** (stack, sprints, diagramme de classes, tableau des écarts avec la v2).

---

## 5. Structure

```
backend/
  src/Entity/            13 entités (User, Gerant, Kine, Patient, DemandeRdv, DossierMedical, Exercice,
                         PlanReeducation, Seance, Facture, Paiement, Message, Notification)
  src/Controller/Api/    Public, Register, Me, Exercice, Message, Notification
                         Gerant/ (DemandeRdv, Inscription, Utilisateur, Affectation, Facture, Statistiques)
                         Kine/   (Patient, Seance, Plan) · Patient/ (EspacePatient, FacturePatient)
  src/Security/          UserChecker (compte désactivé / non validé)
  src/Service/           ApiSerializer, MailService, Notifier
  src/Command/           app:create-gerant, app:demo
frontend/src/app/
  core/                  Api, intercepteur JWT, guards par rôle, modèles, notifications
  layout/                Vitrine publique + espace connecté (menu latéral repliable sur mobile)
  pages/public|gerant|kine|patient|shared-pages
```
