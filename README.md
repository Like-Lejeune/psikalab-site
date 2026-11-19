# Site PsikaLab — psikalab.space

Site vitrine bilingue (FR / EN) de PsikaLab, construit avec [Astro](https://astro.build) et déployé sur Hostinger.

- Pages : À propos, Événements, Nos travaux, Contact (avec l'équipe), Rejoindre le labo, Confidentialité, Conditions d'utilisation, 404.
- URLs : `/fr/...` et `/en/...` ; la racine `/` redirige selon la langue du navigateur.
- Formulaires : `public/api/submit.php` (PHP, tourne sur Hostinger), sans base de données.

---

## 1. Travailler en local

Prérequis : Node.js 22 ou plus.

```bash
npm install
npm run dev        # http://localhost:4321/fr/
npm run build      # génère le site dans dist/
```

Pour tester aussi les formulaires en local (PHP 8.1+) :

```bash
npm run build && php -S localhost:8000 -t dist
```

## 2. Modifier le contenu (sans toucher au design)

| Ce que vous voulez changer | Fichier |
| --- | --- |
| Chiffres clés, email, téléphone, adresse, partenaires, liens GitHub / LinkedIn | `src/data/site.json` |
| Événements (ajouter, modifier, mettre en avant) | `src/data/events.json` |
| Projets de la galerie | `src/data/projects.json` |
| Membres de l'équipe | `src/data/team.json` |
| Tous les textes de l'interface, FR et EN | `src/i18n/ui.ts` |
| Politique de confidentialité / conditions | `src/content/legal/*.fr.md` et `*.en.md` |
| Couleurs, polices, espacements | `src/styles/global.css` (variables en haut) |

Règles simples :
- Chaque texte existe en `fr` et en `en` : modifiez toujours les deux.
- **Événement** : `date` au format `AAAA-MM-JJ` (ou `null` si la date n'est pas fixée). Un événement passé disparaît tout seul à la compilation suivante. `featured: true` le met dans le carrousel. `location` : `yaounde`, `douala`, `abidjan`, `dakar` ou `online`. `type` : `psikalab`, `hackathon` ou `workshop`. `registerUrl` : lien d'inscription (Luma, Google Form…).
- **Projet** : `domain` = `ai`, `fintech`, `cyber` ou `tools`. `image` = chemin d'une image placée dans `public/images/` (ex. `/images/maillon.webp`), sinon un bloc de couleur s'affiche. `demoUrl` = vidéo de démo.
- **Photos de l'équipe / logos partenaires** : déposez-les dans `public/images/` et indiquez le chemin (`/images/yves.webp`).
- Tout ce qui est entre crochets `[...]` est à remplacer avant la mise en ligne.

Ensuite : `git commit` + `git push` sur `main` → le site est en ligne 1 à 2 minutes plus tard.

## 3. Mise en ligne sur Hostinger (à faire une seule fois)

### a. GitHub
1. Créez un dépôt GitHub (privé ou public) et poussez ce projet sur la branche `main`.
2. Onglet **Actions** : le workflow « Build & deploy » se lance, compile le site et crée la branche **`deploy`** (elle ne contient que le site compilé).
3. Vérifiez que la branche `deploy` existe avant de passer à Hostinger.

### b. Hostinger (hPanel)
1. **Websites** → ajoutez / sélectionnez le site `psikalab.space`.
2. Si `public_html` contient des fichiers par défaut (page d'attente, `default.php`), supprimez-les : le dossier doit être vide pour le premier déploiement Git.
3. **Dashboard → Advanced → Git** → **Continue with GitHub**, autorisez l'accès au dépôt.
4. Choisissez le dépôt, la branche **`deploy`**, et le dossier d'installation `public_html` (laisser vide = racine).
5. Vérifiez que le déploiement automatique est activé : chaque mise à jour de `deploy` met le site en ligne. Le bouton **Redeploy** relance à la main.

### c. Domaine et HTTPS
1. Si le domaine est chez Hostinger, il est déjà pointé. Sinon, mettez les serveurs DNS de Hostinger chez votre registrar.
2. **Security → SSL** : installez le certificat gratuit. Le `.htaccess` force ensuite HTTPS et redirige `www` vers `psikalab.space`.

### d. Emails et formulaires
1. **Emails** : créez la boîte `contact@psikalab.space` (et, si vous le souhaitez, `no-reply@psikalab.space`).
2. **Files → File Manager** : à côté de `public_html` (dans `domains/psikalab.space/`), créez `psikalab-config.php` en copiant `server/psikalab-config.sample.php` et ajustez les adresses.
3. Testez le formulaire de contact en ligne. Les envois sont enregistrés dans `domains/psikalab.space/psikalab-data/*.jsonl` (hors du web) et notifiés par email.

## 4. Gérer le contenu depuis le back-office Laravel (optionnel)

Le site peut lire son contenu dans le back-office PsikaLab (projet `psikalab-api`, Laravel + Filament) au lieu des fichiers `src/data/*.json`. C'est réglé par deux variables du dépôt GitHub, dans Settings → Secrets and variables → Actions → Variables :

- `CONTENT_API_URL` = `https://api.psikalab.space/api/content`
- `PUBLIC_FORMS_ENDPOINT` = `https://api.psikalab.space/api/forms`

Sans ces variables, le site fonctionne comme avant, avec les fichiers JSON et `public/api/submit.php`. Avec, chaque modification faite dans l'admin relance la compilation (événement `content-updated`). La mise en place est décrite dans le README de `psikalab-api`.

En local : `CONTENT_API_URL=http://localhost:8000/api/content npm run build`.

## 5. Si le nom de domaine change

Modifiez le domaine dans `astro.config.mjs` (`site`), `public/robots.txt`, `src/data/site.json`, `public/api/submit.php` (`allowed_hosts`) et le fichier `psikalab-config.php` sur le serveur.

## 6. Structure

```
.github/workflows/deploy.yml   compilation + publication sur la branche deploy
public/.htaccess               HTTPS, redirection de langue, cache, sécurité
public/api/submit.php          réception des formulaires
server/psikalab-config.sample.php  modèle de configuration serveur
src/data/*.json                contenu local (événements, projets, équipe, infos)
src/lib/content.ts             lit le contenu : API Laravel si configurée, sinon les JSON
src/i18n/ui.ts                 textes FR / EN
src/content/legal/*.md         pages légales
src/layouts/Base.astro         squelette commun (SEO, header, footer)
src/components/                header, footer, formulaires, page légale
src/pages/[lang]/*.astro       les pages, générées en /fr/ et /en/
```
# psikalab-site
