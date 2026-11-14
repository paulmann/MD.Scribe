<!--
 * MD.Scribe — Documentation
 * Version: 3.6.3
-->

# MD.Scribe 3

MD.Scribe est une plateforme de documentation Markdown auto-hébergée en PHP 8.5 : une visionneuse
complète CommonMark/GFM avec recherche plein texte, palette de commandes, catalogue de thèmes,
diagrammes Mermaid et mathématiques KaTeX, plus un panneau d'administration doté d'un gestionnaire
de contenu, d'un éditeur, de sauvegardes, d'un programme de mise à jour vérifié et d'une piste
d'audit. Aucune base de données, aucun framework et aucune étape de compilation ne sont nécessaires
sur le serveur, et chaque ressource est livrée localement : l'application entière fonctionne hors
ligne.

<!-- md-i18n:switcher -->
**Autres langues :** [English](README.md) · [Deutsch](README.de.md) · [Español](README.es.md) · Français · [हिन्दी](README.hi.md) · [中文](README.zh.md) · [Русский](README.ru.md)

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![Version](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![Licence : MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-241%20passed-brightgreen)](tests)
[![Requêtes CDN](https://img.shields.io/badge/CDN%20requests-0-success)](#politique-zéro-cdn)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe — plateforme de documentation Markdown PHP 8.5 auto-hébergée, avec visionneuse et panneau d'administration"
     width="100%">

[**Démarrage rapide**](#démarrage-rapide) · [**Tour d'horizon**](#tour-dhorizon-des-fonctionnalités) · [**Configuration**](#configuration--mdini) · [**Documentation**](#documentation) · [**Wiki**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**Journal des modifications**](CHANGELOG.md) · [**Sécurité**](SECURITY.md) · [**Contribution**](CONTRIBUTING.md)

> **État de la documentation.** La version et chaque nombre de ce README ont été mesurés le
> 2026-11-14 sur l'arbre `main` de 3.6.0, avec la commande nommée là où le nombre apparaît.
> Les guides orientés tâches se trouvent dans le
> [wiki](https://github.com/paulmann/MD.Scribe/wiki/Home) ; la documentation de référence se
> trouve dans [`docs/`](docs/installation.md).

## Sommaire

- [Présentation de MD.Scribe](#présentation-de-mdscribe)
- [Démarrage rapide](#démarrage-rapide)
- [Tour d'horizon des fonctionnalités](#tour-dhorizon-des-fonctionnalités)
- [Configuration — `.md.ini`](#configuration--mdini)
- [Contrat d'URL](#contrat-durl)
- [Intégration et rendu en ligne de commande](#intégration-et-rendu-en-ligne-de-commande)
- [Sécurité](#sécurité)
- [En chiffres](#en-chiffres)
- [Développement](#développement)
- [Documentation](#documentation)
- [Langues de la documentation](#langues-de-la-documentation)
- [Mise à niveau depuis la v2](#mise-à-niveau-depuis-la-v2)
- [Contribuer](#contribuer)
- [Licence](#licence)
- [Aider le projet : des preuves, pas de l'engagement](#aider-le-projet--des-preuves-pas-de-lengagement)

---

## Présentation de MD.Scribe

Il suffit de pointer l'application vers un dossier de fichiers `.md` pour obtenir un site de
documentation :

- **`public/index.php`** rend un document (`?file=path/doc.md`), une **bibliothèque**
  interrogeable lorsqu'aucun fichier n'est demandé, ainsi que les pages **Thèmes**,
  **Paramètres** et **Recherche**, `sitemap.xml` et un flux RSS.
- **`public/admin.php`** est le panneau d'administration : tableau de bord, gestionnaire de
  fichiers, éditeur en panneaux divisés avec aperçu en direct, envoi de fichiers, contrôle de
  l'index de recherche, sauvegardes et corbeille, programme de mise à jour automatique, éditeur
  de paramètres, journaux, piste d'audit, contrôles de sécurité et d'état, et outils
  (vérificateur de liens morts, contrôle du cache).
- **`md.php` / `updater.php`** à la racine du projet sont des shims de compatibilité — chaque
  contrat d'URL de la v2 (`?file=`, `&download=1`, `?update=true`, `?restore=latest`, l'API
  machine) continue de fonctionner après une mise à niveau.

**Première visite ?** Commencer par le
[wiki](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
([Home](https://github.com/paulmann/MD.Scribe/wiki/Home)) — il mène de l'archive de version à une
installation vérifiée en quatre commandes.

La mise en forme repose entièrement sur un **système de design à jetons shadcn/ui, compilé
localement en Tailwind CSS v4**, avec modes clair/sombre/système, 12 thèmes sélectionnés et un
mélangeur neutre × accent. Mermaid, KaTeX, Inter, JetBrains Mono et toutes les icônes sont
embarqués — l'application fonctionne entièrement **hors ligne / en réseau isolé**.

---

## Démarrage rapide

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs

# PHP dependencies are vendored in release archives; from a bare clone:
composer install --no-dev --optimize-autoloader

# put your documents in content/ (or set BROWSE_DIR in .md.ini)
cp ~/my-notes/*.md content/

# develop:
php -S localhost:8080 -t public public/router.php
# → http://localhost:8080        (viewer)
# → http://localhost:8080/admin.php   (admin — first-run setup)
```

Déploiements en production (racine web sur `public/`, racine web à la racine du projet, Apache,
nginx, Caddy, Docker, hébergement mutualisé) : **[docs/installation.md](docs/installation.md)**.

Prérequis : **PHP ≥ 8.5** avec `mbstring`, `dom`, `libxml`, `json` (`curl` + `phar` + `zip`
recommandés pour le programme de mise à jour et les archives de sauvegarde).

---

## Tour d'horizon des fonctionnalités

### Expérience de lecture

| Domaine | Fonctionnalités |
| --- | --- |
| Typographie | Échelle typographique fluide, réglages de taille de texte / largeur de page / interligne, options de polices à empattements et système, feuille de style d'impression |
| Navigation | Table des matières automatique avec scrollspy, numéros de titres + ancres au survol, emplacement `[TOC]`, fil d'Ariane, pagination précédent/suivant, barre latérale de dossier, liens d'évitement |
| Découverte | **Recherche plein texte** côté serveur (index inversé, compatible CJK) avec **opérateurs** (`tag:`, `path:`, valeurs entre guillemets), **palette de commandes Ctrl+K** (documents, titres, actions), **index de tags** (`?page=tags`), sitemap.xml, flux RSS |
| Contenu enrichi | **Coloration syntaxique** côté serveur (~190 langages), diagrammes **Mermaid** avec réparation automatique + diagnostics, mathématiques **KaTeX** (y compris mhchem), **alertes** de style GitHub, listes de tâches, notes de bas de page, listes de définitions, `==marks==`, ^super^/~sub~script, plus de 1 900 raccourcis emoji, **lightbox** d'images avec légendes, estimation du temps de lecture |
| Base de connaissances | **Wikilinks** (`[[Page]]`, `[[Page\|Alias]]`, `[[Page#Heading]]`) avec marqueurs de page manquante, **rétroliens** (« Lié depuis »), **tags** de frontmatter avec index public, **historique des révisions** par document avec restauration, **export de site statique** vers un ZIP autonome |
| Glossaire | Toute table à deux colonnes devient une **infobulle** au survol ou au toucher, avec expansion des alias (`Term (ABBR)`) et contenu DOM en direct — zéro duplication |
| Hors ligne | **PWA** installable : manifeste web + service worker — ressources en cache-first, documents en network-first avec la dernière copie lue comme secours, API JSON jamais mises en cache (`PWA_ENABLED`) |
| Thèmes | 12 préréglages + mélangeur neutre × accent × rayon × police, clair/sombre/**détection automatique du système**, persistance par visiteur, **thèmes personnalisés** gérés par l'administration, amorçage sans FOUC avant le premier rendu |

### Prise en charge de Markdown

CommonMark **0.31-complet** via `league/commonmark`, plus GFM (tables avec alignement, barré,
listes de tâches, liens automatiques), notes de bas de page (par référence **et** en ligne),
listes de définitions, syntaxe d'attributs `{#id .class}`, ponctuation intelligente (sur option),
frontmatter YAML (`title`, `description`, `tags`, `author`, `date`, `draft`, `toc`, `numbering`),
réécriture des liens `.md` relatifs avec marqueurs de lien mort, `[[wikilinks]]` avec résolution
d'alias et d'ancre, et les extras hérités de MD.Viewer (chaînes de motifs universelles, tables de
glossaire, règle de nommage `md.md`). Matrice complète :
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Panneau d'administration

| Page | Rôle |
| --- | --- |
| **Tableau de bord** | Statistiques du corpus, fraîcheur de l'index, synthèse d'état, actions rapides, documents récents, fil d'activité |
| **Fichiers** | Arborescence de tout le contenu, filtre, création/renommage/déplacement/suppression (suppression douce vers la **corbeille**), dossiers, envoi multiple (.md + images avec détection du type de contenu), **nouveau à partir d'un modèle** |
| **Éditeur** | Aperçu en direct en panneaux divisés (le *vrai* moteur), **barre d'outils Markdown** (gras, italique, code, lien, wikilink, liste, alerte, table), enregistrement ⌘S avec **détection de conflit sha256**, compteur de mots et de temps de lecture, envoi d'image au curseur, liens profonds `?page=editor&file=…` |
| **Index de recherche** | Statistiques, fraîcheur, reconstruction à la demande |
| **Sauvegardes et corbeille** | Instantanés de contenu en un clic (+ `.md.ini`), restauration avec copie de sécurité automatique avant restauration, téléchargement zip, liste prête pour l'élagage, restauration des fichiers supprimés |
| **Révisions** | Historique d'enregistrement par document (`REVISIONS_MAX` les plus récentes, 20 par défaut), aperçu en lecture seule, restauration qui conserve le contenu courant comme révision `(pre-restore)` |
| **Mises à jour** | Vérification des versions GitHub (cache de 15 min), notes de version, **application vérifiée** (tarball + manifeste sha256 par fichier, sauvegarde au préalable), documentation de l'API machine |
| **Paramètres** | Chaque clé de `.md.ini` sous forme de champ typé (piloté par schéma), régénération de la clé d'API, changement du mot de passe d'administration |
| **Thèmes personnalisés** | Enregistrement des accents de marque (primaires clair + sombre) — immédiatement sélectionnables dans le catalogue public |
| **Journaux et audit** | Journaux applicatifs quotidiens + piste d'audit JSON (qui a fait quoi, quand, d'où) |
| **Sécurité et état** | Plus de 20 contrôles : version et extensions PHP, droits d'écriture, permissions, posture des secrets, OPcache, CSP ; liste de vérification de durcissement |
| **Outils** | **Vérificateur de liens morts / d'ancres mortes / de médias manquants / de documents orphelins**, reconstruction des rétroliens et de l'index de tags, **export de site statique** (ZIP de HTML autonome), vidage du cache, raccourcis vers le flux et le sitemap |

### Politique zéro CDN

Chaque ressource est livrée localement : la sortie Tailwind (compilée au moment du développement,
versionnée), `mermaid.min.js` embarqué, KaTeX (CSS + polices + mhchem), les sous-ensembles woff2
d'Inter et de JetBrains Mono (latin/latin-ext/cyrillique), les icônes lucide compilées dans une
table de sprites PHP, et la base gemoji complète. La Content-Security-Policy l'impose :
`default-src 'self'`.

---

## Configuration — `.md.ini`

Créé automatiquement à côté de la racine du projet au premier chargement. Les clés de niveau
installation sont ajoutées avec des valeurs par défaut sûres à chaque chargement — les mises à
niveau ne demandent jamais d'édition manuelle. Les clés de fonctionnalités de rendu ne sont
délibérément **pas** écrites : leurs valeurs par défaut résident dans `config/defaults.php`, et
les visiteurs peuvent les basculer par navigateur (cookies propriétaires). Ajouter une clé de
fonctionnalité à `.md.ini` la **verrouille**.

```ini
; General
SITE_NAME = MD.Scribe
HOME_DOC  = index.md
BROWSE_DIR =                     ; empty = bundled content/

; Security & admin
ADMIN_ENABLED = true
ADMIN_USERNAME = admin
ADMIN_PASSWORD =                 ; one-shot: hashed on next load, then cleared
ADMIN_PASSWORD_HASH =            ; managed by the panel — don't edit
ADMIN_RATE_LIMIT = 10            ; failed logins / 15 min / IP
API_KEY = mdv_…                  ; machine API credential (auto-generated)
ALLOW_UPDATE = false
ALLOW_RESTORE = false
ALLOW_RAW_HTML = false           ; escape author HTML (recommended)
ALLOW_IFRAMES = false
ALLOW_EXTERNAL_IMAGES = true
SECURITY_HEADERS = true
SECURITY_CSP = true

; Content
DISABLE_UPLOAD = true
DISABLE_CLIPBOARD = false
DISABLE_SAVE_CLIPBOARD_TO_FILE = true
MAX_UPLOAD_MB = 8

; Performance / feeds / appearance / diagnostics — see docs/configuration.md
```

Référence complète des clés (chaque clé, type, valeur par défaut, effet) :
**[docs/configuration.md](docs/configuration.md)**.
La superposition de `.md.ini` par répertoire (le mécanisme `DISABLE_*` de la v2) est conservée :
**[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**.

---

## Contrat d'URL

| URL | Rôle |
| --- | --- |
| `index.php?file=docs/api.md` | rendre un document |
| `index.php?file=docs/api.md&download=1` | téléchargement de la source (octet-stream + nosniff) |
| `index.php?media=docs/img/pic.png` | ressource de contenu (images en ligne ; SVG/zip en pièces jointes) |
| `index.php?page=browser[&dir=docs]` | bibliothèque / explorateur de fichiers |
| `index.php?page=search&q=…` | recherche plein texte |
| `index.php?page=themes` / `page=settings` | catalogue de thèmes / paramètres |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | points d'accès JSON en GET |
| `index.php?api=render\|save\|upload` | points d'accès JSON en POST (CSRF) |
| `admin.php?page=…` / `admin.php?action=…` | interface d'administration / API JSON d'administration |
| `md.php?file=…` | shim de visionneuse **compatible v2** |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | programme de mise à jour **compatible v2** + API machine |

Mise en cache HTTP : les documents et les médias répondent avec `ETag`/`Last-Modified` et
prennent en charge `304 Not Modified` ; le HTML rendu est en plus mis en cache côté serveur, avec
une clé composée du hachage de la source × de l'empreinte des fonctionnalités.

---

## Intégration et rendu en ligne de commande

Le pipeline Markdown est utilisable sans la visionneuse : rendre un README, une note de version ou
un bloc CMS depuis n'importe quel appelant PHP ou script shell, avec la même prise en charge
CommonMark/GFM, les mêmes extensions, la même coloration syntaxique et le même assainisseur.

```php
require '/path/to/MD.Scribe/src/bootstrap.php';

$renderer = new \MdViewer\Standalone\Renderer();
$result = $renderer->renderFile('/srv/repos/ragraf/README.md');
echo $result->html;                 // embed mode: portable code blocks, no numbering
```

```bash
bin/md-scribe-render README.md --html \
  --base-url="https://ragraf.example.com/" \
  --css="https://example.com/readme.css" > readme.html
```

Le mode d'intégration émet `<pre><code class="hljs language-…">` et des titres simples, afin
qu'une feuille de style pour README GitHub (`github-markdown-css` + un thème highlight.js) puisse
mettre la page en forme. Référence complète des options et recettes d'intégration :
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**.

Depuis 3.2.2, le même pipeline est aussi un point d'accès HTTP sans état pour d'autres
applications — Markdown en entrée, fragment HTML en sortie, authentifié par clé, sans session :

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` accepte `auto` (valeur par défaut), `markdown`, `html` (assaini) et `text` (texte
verbatim encapsulé dans `<div class="md-text">`). Contrat, limites et statuts :
**[docs/http-api.md](docs/http-api.md)**.

---

## Sécurité

Défense en profondeur — détails et liste de vérification de durcissement :
**[docs/security.md](docs/security.md)**.

- **Validation des chemins** : 14 couches (longueur, NUL, caractères de contrôle, re-vérification
  après décodage d'URL, chemins absolus, traversée, liste blanche Unicode stricte
  `\p{L}\p{M}\p{Pd}`, profondeur, extension, confinement par realpath, re-vérification sûre
  vis-à-vis des liens symboliques) — un seul validateur pour la visionneuse, les téléchargements,
  les médias, les envois, l'ini par répertoire et l'administration.
- **Assainissement de la sortie** : chaque document rendu passe par un assainisseur à liste
  blanche DOM (gestionnaires d'événements, schémas dangereux, formulaires, foreignObject/`<use>`
  sont tous éliminés) ; le HTML brut de l'auteur est échappé sauf si `ALLOW_RAW_HTML` est actif —
  et assaini même dans ce cas.
- **Transport** : CSP stricte avec nonces par requête, nosniff, politiques frame/referrer/
  permissions, COOP ; cookies de session durcis.
- **Administration** : connexion par password_hash, limitation de débit par IP, CSRF sur chaque
  mutation, régénération de session, piste d'audit, suppressions douces, configuration initiale
  conditionnée à localhost ou à un code à usage unique.
- **Secrets** : `.md.ini` n'est jamais servi (`.htaccess` + dispositions hors racine web) ; l'API
  machine ne répond qu'en JSON.

Signalement d'une vulnérabilité : **[SECURITY.md](SECURITY.md)**.

---

## En chiffres

Mesuré le 2026-11-14 sur l'arbre `main` de 3.6.0 :

| Surface | Valeur | Source de la mesure |
| --- | ---: | --- |
| Fichiers source PHP | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| Fichiers de test | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| Tests PHPUnit | 214 tests, 678 assertions, 1 ignoré | `composer test` |
| Pages de documentation | 12 | `Get-ChildItem docs -Filter *.md` |
| Paquets Composer à l'exécution | 2 (`league/commonmark`, `scrivo/highlight.php`) | `composer.json` → `require` |
| Thèmes sélectionnés | 12 | `config/themes.php` |
| Requêtes CDN à l'exécution | 0 | `default-src 'self'` dans la CSP appliquée |

---

## Développement

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 214 PHPUnit tests, 678 assertions
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

Le pipeline frontend (Tailwind v4 → `app.css`, esbuild → `app.js`/`admin.js`, compilateur de
thèmes → `themes.css`, générateurs d'icônes et d'emoji) ne s'exécute qu'au moment du
**développement** — les ressources compilées sont versionnées, donc les serveurs de production
n'ont pas besoin de Node.js. Architecture, carte des modules, liste de vérification de version :
**[docs/development.md](docs/development.md)**.

### Structure du projet

```text
MD.Scribe/
├── public/              ← recommended docroot
│   ├── index.php        front controller (viewer, library, pages, JSON API)
│   ├── admin.php        admin panel + admin JSON API
│   ├── router.php       php -S dev router
│   └── assets/          css/ js/ vendor/{mermaid,katex,fonts} img/  (all local)
├── src/                 PSR-4 app code (MdViewer\)
│   ├── Core/            App, Config, FeatureFlags, Request/Response, Session,
│   │                    Csrf, Logger, AuditLog, HttpClient, Paths, Version
│   ├── Http/            FrontController, AdminKernel, Controllers/
│   ├── Markdown/        engine, sanitizer, glossary, mermaid repair, cache,
│   │                    Extensions/ (math, sub/sup, emoji, alerts, renderers)
│   ├── Files/           PathValidator, repository, search index, uploader,
│   │                    FileManager
│   ├── Admin/           auth, backups, updater, health, link checker
│   ├── Theme/           theme catalog (shadcn token model)
│   └── View/            template engine, generated icon sprite
├── templates/           layout/ viewer/ browser/ pages/ admin/
├── config/              defaults.php (schema) · themes.php · manifest.json
├── content/             ← your documents live here (default BROWSE_DIR)
├── storage/             cache/ backups/ logs/ index/ state/ themes/  (writable)
├── docs/                deep documentation
├── tests/               PHPUnit (Unit + Feature)
├── tools/               build pipeline (frontend/, build-manifest.php)
├── deploy/              nginx, Caddy, Docker, php.ini
├── md.php               v2-compatible viewer shim
├── updater.php          v2-compatible updater + machine API shim
└── index.php            project-root entry (docroot-at-root installs)
```

---

## Documentation

Deux couches : le **[wiki](https://github.com/paulmann/MD.Scribe/wiki)** est le guide orienté
tâches (le faire tourner, le configurer, l'exploiter), tandis que les fichiers de `docs/` sont la
référence que l'on confronte à l'étiquette de version utilisée.

| Document | Contenu |
| --- | --- |
| **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** | Prise en main, installation, configuration, fonctionnalités Markdown, recherche, guide d'administration, API HTTP, mises à jour, dépannage, développement, journal des modifications — commencer par [Home](https://github.com/paulmann/MD.Scribe/wiki/Home) |
| [docs/installation.md](docs/installation.md) | Toutes les dispositions de déploiement, Apache/nginx/Caddy/Docker, hébergement mutualisé, permissions |
| [docs/configuration.md](docs/configuration.md) | Référence complète de `.md.ini`, fichiers par répertoire, bascules de fonctionnalités |
| [docs/markdown-features.md](docs/markdown-features.md) | Matrice de syntaxe complète avec exemples |
| [docs/admin-guide.md](docs/admin-guide.md) | Chaque page d'administration, flux de travail, API machine |
| [docs/theming.md](docs/theming.md) | Système de thèmes, jetons, thèmes personnalisés, recettes |
| [docs/security.md](docs/security.md) | Modèle de menace, liste de vérification de durcissement, notes sur la CSP |
| [docs/http-api.md](docs/http-api.md) | Points d'accès JSON (public + administration + API machine) |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | Intégration du pipeline Markdown, rendu en CLI et API HTTP de rendu |
| [docs/development.md](docs/development.md) | Architecture, pipeline de compilation, tests, versions |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Problèmes courants et correctifs |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Correspondances et étapes de migration |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | Contexte approfondi du projet pour les agents de codage |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Comment proposer un changement et ce qui doit passer avant |
| [SECURITY.md](SECURITY.md) | Versions prises en charge et signalement privé des vulnérabilités |
| [CHANGELOG.md](CHANGELOG.md) | Chaque version, au format Keep a Changelog |

---

## Langues de la documentation

Le README anglais fait foi. Un README traduit reprend ce fichier section par section et il est mis
à jour dans le même commit dès qu'un titre, une commande ou un nombre change. Le wiki et `docs/`
restent en anglais.

| Langue | Fichier | Remarques |
| --- | --- | --- |
| Anglais | [README.md](README.md) | Canonique |
| Russe | [README.ru.md](README.ru.md) | Traduction de ce fichier |
| Allemand | [README.de.md](README.de.md) | Traduction de ce fichier |
| Chinois (simplifié) | [README.zh.md](README.zh.md) | Traduction de ce fichier |
| Hindi | [README.hi.md](README.hi.md) | Traduction de ce fichier |
| Espagnol | [README.es.md](README.es.md) | Traduction de ce fichier |
| Français | [README.fr.md](README.fr.md) | Traduction de ce fichier |

Une traduction qui prend du retard est un défaut : le signaler avec la langue et la section, ou
ouvrir une demande de fusion qui la corrige.

---

## Mise à niveau depuis la v2

Sans rupture : déployer la v3 à côté des fichiers v2, déplacer les documents (ou pointer
`BROWSE_DIR` dessus), conserver le shim `md.php` à la racine — chaque ancien signet et lien
`?file=` fonctionne. Pas à pas : **[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

---

## Contribuer

Un changement logique par demande de fusion, un test pour tout nouveau comportement, et aucune
nouvelle dépendance d'exécution sans discussion préalable. Les barrières sont `composer test`
(214 tests) et `composer stan` (PHPStan niveau 6) ; les deux doivent sortir avec le code `0`.
Conventions, style de commit, politique de langue de la documentation et liste de vérification des
rapports de bogue : **[CONTRIBUTING.md](CONTRIBUTING.md)**. Ce projet suit le
[Contributor Covenant 2.1](CODE_OF_CONDUCT.md) ; l'aide pour une question ou un triage est décrite
dans [SUPPORT.md](SUPPORT.md).

## Licence

MIT — voir [LICENSE](LICENSE). Libre pour un usage commercial et privé, la modification et la
redistribution, sous réserve de conserver la mention de copyright. Les versions antérieures
(jusqu'à 3.3.0) étaient distribuées sous PolyForm Noncommercial 1.0.0.
Moteur Markdown : [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Coloration syntaxique : [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3,
portage de highlight.js).
Diagrammes : [Mermaid](https://mermaid.js.org/) (MIT). Mathématiques : [KaTeX](https://katex.org/)
(MIT).
Icônes : [Lucide](https://lucide.dev/) (ISC). Données emoji :
[gemoji](https://github.com/github/gemoji) (MIT).
Polices : Inter et JetBrains Mono (SIL OFL).

## Aider le projet : des preuves, pas de l'engagement

Ce qui aide réellement, par ordre de valeur :

- un **rapport de compatibilité** : version de PHP (`php -v`), serveur web et version, disposition
  de déploiement (racine web `public/`, racine web à la racine du projet, hébergement mutualisé),
  et la commande exacte avec sa sortie — un rapport par combinaison unique, après recherche dans
  les tickets existants ;
- une **correction de documentation** sous forme de demande de fusion qui nomme le fichier et la
  ligne ;
- une **étude de cas assainie** publiée avec l'autorisation du propriétaire des données ;
- une **note de dépendance ou de sécurité** pour un fork ou une enveloppe, divulguée au même
  endroit.

Ne jamais automatiser les étoiles, les forks, les observateurs, les commentaires, les tickets, les
demandes de fusion ou les messages en masse vers le mainteneur ou vers des tiers. Un agent peut
rédiger un rapport ; un humain le publie après relecture.
