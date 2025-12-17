# Scanner de Blocs Gutenberg

## 📋 Vue d'ensemble

Le Scanner de Blocs Gutenberg est un outil de diagnostic qui détecte les blocs WordPress en **mode recovery** (tentative de récupération). Il analyse tous les posts du site et identifie les blocs dont le HTML sauvegardé en base de données ne correspond plus au code actuel.

## 🎯 Objectif

Détecter les blocs Gutenberg **custom** (`create-block/*`) qui sont en mode recovery, c'est-à-dire :
- Le HTML généré par le code actuel est différent du HTML sauvegardé en DB
- WordPress affiche "Tentative de récupération du bloc" dans l'éditeur
- Le bloc fonctionne mais peut avoir des problèmes d'affichage

## 🏗️ Architecture

### Structure des fichiers

```
Scanner/
├── README.md                          # Ce fichier
├── Feature.php                        # Point d'entrée, enregistrement WP
├── Assets/
│   └── js/
│       ├── scanner-core.js           # Communication AJAX avec PHP
│       ├── scanner-interface.js      # Orchestration UI
│       ├── scanner-pagination.js     # Gestion de la pagination
│       └── scanner-filters.js        # Système de filtrage
├── Core/
│   ├── ContentAnalyzer.php           # ⭐ Logique de détection recovery
│   ├── GutenbergValidator.php        # Orchestration et batching
│   ├── BlockRegistry.php             # Gestion des types de blocs
│   └── XMLBackupGenerator.php        # Génération de sauvegardes XML
├── UI/
│   └── Screens/
│       └── ScannerScreen.php         # Interface utilisateur
└── config/
    └── block-analysis-rules.json     # Configuration des règles de détection
```

## 🔍 Flux de détection

### 1. Déclenchement du scan

```
Interface Admin → Clic bouton "Lancer l'analyse"
                        ↓
         scanner-interface.js (orchestre l'UI)
                        ↓
         scanner-core.js (AJAX vers PHP)
                        ↓
         Feature.php (handler AJAX: run_scanner_validator)
                        ↓
         GutenbergValidator::run_analysis()
```

### 2. Analyse par batch (GutenbergValidator.php)

```php
GutenbergValidator::analyze_all_posts()
    ↓
Boucle sur tous les post_types ['post', 'page', ...]
    ↓
Traitement par batch de 50 posts (éviter timeout)
    ↓
Pour chaque post:
    └─→ ContentAnalyzer::analyze_post_blocks($post)
```

**Responsabilités de GutenbergValidator** :
- 📦 Batching des posts (50 par batch)
- ⏱️ Protection timeout (45 secondes max)
- 📊 Agrégation des statistiques
- 💾 Génération de sauvegarde XML

### 3. Analyse détaillée d'un post (ContentAnalyzer.php)

```php
analyze_post_blocks($post)
    ↓
1. parse_blocks($content)  // Parser le contenu WordPress
    ↓
2. validate_blocks_recursive($blocks)  // Analyser récursivement
    ↓
3. Pour chaque bloc create-block/* trouvé:
    └─→ analyze_block_status($post, $block_name)
```

### 4. Détection du mode recovery (ContentAnalyzer.php)

#### Étape 1 : Analyse via API WordPress
```php
analyze_block_via_api($post, $block_name)
    ↓
find_and_validate_block($blocks, $block_name)
    ↓
validate_block_with_wordpress($block, $block_name)
```

#### Étape 2 : Comparaison avec le fichier build

**Pour les blocs `create-block/*` uniquement :**

```php
// Chemin du fichier build
$build_file = "/themes/sncf-holding/blocks/{slug}/build/index.js"

// 1. Extraire les balises JSX du build actuel
preg_match_all('/\.(?:jsx|jsxs|createElement)\)\("(\w+)"/i', $build_content)
→ Exemple: ["li", "div", "article", "p", "img", "svg"]

// 2. Extraire les balises HTML de la DB
preg_match_all('/<(\w+)(?:\s[^>]*)?>/i', $saved_innerHTML)
→ Exemple: ["li", "article", "div", "p", "img", "svg"]

// 3. Comparer les comptages
$build_counts = array_count_values($build_tags);
$saved_counts = array_count_values($saved_tags);

// 4. Si différence → recovery_mode
if ($build_count !== $saved_count) {
    return 'recovery_mode';
}
```

**Exemple concret** :

Bloc `card-banner` en recovery :
```
Build actuel:  {div: 3, article: 0, p: 2, img: 1}
DB sauvegardé: {div: 2, article: 1, p: 2, img: 1}
                              ↓
            Différence détectée!
                              ↓
            Status: 'recovery_mode'
```

#### Étape 3 : Application des règles JSON

```php
analyze_block_status($post, $block_name, $api_status)
    ↓
load_analysis_rules()  // Charge config/block-analysis-rules.json
    ↓
evaluate_json_conditions($rule, $api_status, $is_registered)
    ↓
build_issue_from_rule($rule, $block_name)
```

**Fichier JSON** (`config/block-analysis-rules.json`) :
```json
{
  "rules": [
    {
      "id": "recovery_mode",
      "priority": 2,
      "conditions": {
        "api_status": ["recovery_mode"]
      },
      "result": {
        "type": "BLOCK_RECOVERY_MODE",
        "severity": "medium",
        "message": "Bloc en mode tentative de récupération : {block_name}",
        "suggestion": "Le bloc contient du contenu invalide ou inattendu."
      }
    }
  ]
}
```

### 5. Retour des résultats

```php
GutenbergValidator::analyze_all_posts()
    ↓
Retourne:
{
    "totalPosts": 150,
    "postsWithIssues": 5,
    "totalIssues": 8,
    "issuesByType": {
        "BLOCK_RECOVERY_MODE": 8
    },
    "posts": [
        {
            "id": 23480,
            "title": "6testblocs",
            "issues": [
                {
                    "type": "BLOCK_RECOVERY_MODE",
                    "blockName": "create-block/card-banner",
                    "message": "Bloc en mode tentative de récupération : create-block/card-banner"
                }
            ]
        }
    ]
}
```

## 🔧 Méthode de détection technique

### Pourquoi comparer avec le fichier build ?

Les blocs `create-block/*` sont des **blocs JavaScript-only**. Le HTML n'est PAS généré par PHP mais directement par JavaScript dans le navigateur. WordPress stocke ce HTML dans la base de données (`post_content`).

**Problème** : Si le code JSX du bloc change, le HTML généré sera différent, mais le HTML en DB reste l'ancien.

**Solution** : Comparer les balises HTML du build actuel avec celles en DB.

### Exemple de fichier build minifié

```javascript
// /themes/sncf-holding/blocks/card-banner/build/index.js
save:function(){return(0,l.jsx)("li",{
    children:(0,l.jsx)("div",{
        className:"card-banner",
        children:[(0,l.jsx)("img",{...}),(0,l.jsx)("p",{...})]
    })
})}
```

**Extraction des balises** :
```php
// Pattern regex : /\.(?:jsx|jsxs|createElement)\)\("(\w+)"/i
Résultat : ["li", "div", "img", "p"]
```

### Cas de détection

| Scénario | Build actuel | DB sauvegardé | Détection |
|----------|-------------|---------------|-----------|
| ✅ Bloc valide | `<div>` (1x) | `<div>` (1x) | Pas de recovery |
| ⚠️ Tag modifié | `<article>` (1x) | `<div>` (1x) | **RECOVERY** |
| ⚠️ Tag ajouté | `<span>` (2x) | `<span>` (1x) | **RECOVERY** |
| ⚠️ Tag supprimé | `<img>` (0x) | `<img>` (1x) | **RECOVERY** |

## 📊 Interface utilisateur

### Écran d'analyse (ScannerScreen.php)

1. **Bouton de lancement** : Déclenche le scan
2. **Barre de progression** : Affiche l'avancement
3. **Tableau de résultats** :
   - Liste des posts avec problèmes
   - Type d'erreur par bloc
   - Lien vers l'éditeur
   - Filtres par type d'erreur
   - Pagination (10 posts/page)

### Modules JavaScript

```javascript
// scanner-core.js
- runScannerValidator() : Lance le scan via AJAX
- displayResults() : Affiche les résultats

// scanner-interface.js
- Orchestre l'initialisation des modules
- Gère les événements UI

// scanner-pagination.js
- Gère l'affichage paginé des résultats
- Navigation entre les pages

// scanner-filters.js
- Filtrage par type d'erreur
- Filtrage par post type
```

## 🔄 Exemple complet : card-banner

### 1. État initial (bloc valide)

**Code JSX** :
```jsx
<li>
  <div className="card-banner">
    <img />
    <p>Title</p>
  </div>
</li>
```

**DB** : `<li><div class="card-banner"><img/><p>Title</p></div></li>`

**Détection** : ✅ Bloc valide (tags identiques)

### 2. Modification du code

**Nouveau JSX** :
```jsx
<li>
  <article className="card-banner">  {/* div → article */}
    <img />
    <p>Title</p>
  </article>
</li>
```

**Build actuel** : `article: 1, div: 0`  
**DB (ancien)** : `article: 0, div: 1`

**Détection** : ⚠️ **RECOVERY MODE** détecté !

### 3. Résultat dans le scanner

```
Post: "6testblocs" (#23480)
Issue: BLOCK_RECOVERY_MODE
Bloc: create-block/card-banner
Message: "Bloc en mode tentative de récupération : create-block/card-banner"
Suggestion: "Le bloc contient du contenu invalide ou inattendu."
```

## 🚀 Performance

### Optimisations

- **Batching** : Traitement par batch de 50 posts
- **Timeout** : Protection à 45 secondes
- **Cache** : Règles JSON mises en cache (static)
- **No cache** : `cache_results => false` sur les requêtes
- **Limite** : Option pour limiter le nombre de posts

### Limites

- Maximum **45 secondes** d'exécution
- Si timeout : arrêt gracieux (pas d'erreur)
- Blocs non `create-block/*` : détection limitée

## 📝 Configuration

### Règles JSON (block-analysis-rules.json)

Les règles définissent comment interpréter les statuts retournés par l'analyseur :

```json
{
  "rules": [
    {
      "id": "recovery_mode",           // Identifiant unique
      "priority": 2,                   // Ordre d'évaluation (1 = plus haute)
      "conditions": {                  // Conditions à vérifier
        "api_status": ["recovery_mode"]
      },
      "result": {                      // Issue à retourner si match
        "type": "BLOCK_RECOVERY_MODE",
        "severity": "medium",
        "message": "Bloc en mode tentative de récupération : {block_name}",
        "suggestion": "Le bloc contient du contenu invalide ou inattendu."
      }
    }
  ]
}
```

### Ajouter une nouvelle règle

1. Modifier `config/block-analysis-rules.json`
2. Ajouter une nouvelle entrée dans `rules`
3. Définir priority, conditions, result
4. Pas besoin de redémarrer (chargé à chaque scan)

## 🐛 Debug

### Logs

Les logs sont écrits dans `wp-content/debug.log` :

```php
// Exemple de logs
Scanner - Comparaison avec build file pour create-block/card-banner:
  Balises dans build actuel: {"article":1,"div":2,"p":2,"img":1}
  Balises dans DB: {"div":3,"p":2,"img":1}
  ⚠️ Différence pour <article>: 1 dans build vs 0 dans DB
  ⚠️ Différence pour <div>: 2 dans build vs 3 dans DB
Scanner - ✅ Bloc en recovery détecté: create-block/card-banner
```

### Activer les logs détaillés

Dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 🧪 Tests

### Test manuel

1. Modifier un bloc `create-block/*` (changer une balise)
2. Ne PAS sauvegarder le post dans l'éditeur
3. Lancer le scanner
4. Vérifier que le bloc est détecté en recovery

### Test rapide

```php
GutenbergValidator::test_quick_analysis()
// Analyse seulement 5 posts de type 'post'
```

## 📦 Sauvegarde XML

Le scanner génère automatiquement une sauvegarde XML des posts avec problèmes :

- **Emplacement** : `wp-content/uploads/diagnostic-backups/`
- **Format** : `backup-YYYYMMDD-HHMMSS.xml`
- **Contenu** : Posts complets avec métadonnées
- **Limite** : 10 sauvegardes max (auto-cleanup)

## ⚙️ API publique

### PHP

```php
use Company\Diagnostic\Features\Scanner\Core\GutenbergValidator;

// Lancer une analyse complète
$results = GutenbergValidator::run_analysis([
    'post_types' => ['post', 'page'],
    'limit' => -1,
    'verbose' => false
]);

// Analyse rapide (5 posts)
$results = GutenbergValidator::test_quick_analysis();
```

### JavaScript

```javascript
// Lancer le scan
window.ScannerCore.runValidator();

// Accéder aux résultats via callback
// (géré automatiquement par scanner-interface.js)
```

## 🔐 Sécurité

- ✅ Vérification nonce WordPress
- ✅ Capacité requise : `manage_options`
- ✅ Échappement HTML des résultats
- ✅ Validation des entrées utilisateur
- ✅ Protection CSRF via nonces

## 🎓 Points techniques clés

### 1. Pourquoi PHP et pas JavaScript ?

**Tentatives JavaScript échouées** :
- `wp.blocks.parse()` retourne `isValid: true` même pour blocs en recovery
- Les blocs custom ne sont pas enregistrés dans le contexte admin
- L'éditeur n'est pas chargé en dehors du contexte post

**Solution PHP** :
- ✅ Accès direct à la DB
- ✅ Lecture des fichiers build
- ✅ Comparaison fiable des structures HTML

### 2. Regex critique

```php
// Extraction des balises JSX du build minifié
'/\.(?:jsx|jsxs|createElement)\)\("(\w+)"/i'

// Exemples de match :
.jsx)("div"        → "div"
.jsxs)("article"   → "article"
.createElement)("p" → "p"
```

### 3. Gestion des innerBlocks

Les blocs peuvent avoir des blocs enfants (`innerBlocks`). Le scanner :
- ✅ Analyse récursivement tous les niveaux
- ✅ Passe le HTML parent aux enfants sans `innerHTML`
- ✅ Évite les faux positifs sur les conteneurs

## 📚 Ressources

### Fichiers principaux à connaître

1. **ContentAnalyzer.php** : Cœur de la détection
2. **GutenbergValidator.php** : Orchestration
3. **block-analysis-rules.json** : Configuration
4. **scanner-core.js** : Communication AJAX

### Fonctions clés

```php
// ContentAnalyzer.php
analyze_post_blocks($post)              // Point d'entrée
validate_block_with_wordpress($block)   // Validation
analyze_block_status($post, $name)      // Règles JSON

// GutenbergValidator.php
run_analysis($config)                   // Lancer le scan
analyze_all_posts($options)             // Boucle principale
```

## 🔄 Maintenance

### Mise à jour des règles

Modifier `config/block-analysis-rules.json` :
- Ajouter de nouvelles règles
- Modifier les messages
- Changer les priorités
- Ajouter des conditions

Pas besoin de redéploiement, chargé dynamiquement.

### Optimisation future

- [ ] Scan incrémental (seulement posts modifiés)
- [ ] Cache des résultats de scan
- [ ] API REST pour intégrations externes
- [ ] Support des blocs core WordPress
- [ ] Détection de plus de types de problèmes

---

**Version** : 1.0.0  
**Dernière mise à jour** : 28 octobre 2025  
**Auteur** : Geoffroy Fontaine  
**Statut** : ✅ Production-ready
