# Block Recovery - Architecture

## 📁 Structure

```
BlockRecovery/
├── Core/                           # Logique métier
│   ├── BlockRecoveryService.php    # Service de récupération des blocs
│   └── ValidationRepository.php    # Repository pour les validations
├── UI/                             # Interface utilisateur
│   └── BlockRecoveryScreen.php     # Écran d'administration
├── Assets/                         # Ressources front-end
│   ├── css/
│   │   └── block-recovery.css
│   └── js/
│       ├── block-recovery-advanced.js  # Orchestration côté admin
│       └── gutenberg-recovery.js       # Récupération native Gutenberg
└── Feature.php                     # Point d'entrée et coordinateur
```

## 🏗️ Architecture

### Séparation des responsabilités

#### **Feature.php** (Coordinateur)
- **Rôle** : Point d'entrée et orchestration
- **Responsabilités** :
  - Enregistrement des hooks WordPress (AJAX, REST API, menus)
  - Délégation vers les services métier
  - Gestion des permissions et validations de sécurité
  - Interface entre WordPress et la logique métier

#### **Core/BlockRecoveryService.php** (Logique métier)
- **Rôle** : Service de récupération des blocs
- **Responsabilités** :
  - Récupération de blocs individuels (`recoverSinglePost`)
  - Récupération avancée avec `render_block()` (`recoverSinglePostAdvanced`)
  - Nettoyage des attributs de recovery
  - Construction du markup HTML
  - Récupération de la liste des posts à traiter
- **Méthodes publiques** :
  - `recoverSinglePost(int $post_id, string $block_name): array`
  - `recoverSinglePostAdvanced(int $post_id, string $block_name): array`
  - `getPostsToRecover(string $block_name): array`

#### **Core/ValidationRepository.php** (Persistance)
- **Rôle** : Repository pour la gestion des validations
- **Responsabilités** :
  - Stockage et récupération des validations
  - Comptage des validations par bloc
  - Vérification des conditions d'auto-récupération
  - Réinitialisation des validations
- **Méthodes publiques** :
  - `getAll(): array`
  - `markAsValidated(int $post_id, string $block_name): bool`
  - `isValidated(int $post_id, string $block_name): bool`
  - `countValidatedForBlock(string $block_name): int`
  - `canAutoRecover(string $block_name): bool`
  - `resetForBlock(string $block_name): bool`
  - `resetAll(): bool`

## 🔄 Flux de données

### Récupération Simple
```
User Click → Feature.php (AJAX) → BlockRecoveryService → WordPress API → Database
```

### Récupération Multiple
```
1. User Click → Feature.php → ValidationRepository (check validations)
2. Feature.php → BlockRecoveryService → getPostsToRecover()
3. JavaScript → Batch processing (4 posts parallel)
4. Each post → Gutenberg iframe → wp.blocks.createBlock() → Save
5. PostMessage → Parent window → Next batch
```

### Validation
```
User Validate → Feature.php (AJAX) → ValidationRepository.markAsValidated() → Database
```

## 🎯 Patterns utilisés

### 1. **Service Layer Pattern**
- `BlockRecoveryService` encapsule toute la logique de récupération
- Méthodes réutilisables et testables
- Séparation claire entre logique métier et infrastructure WordPress

### 2. **Repository Pattern**
- `ValidationRepository` abstrait l'accès aux données de validation
- Indépendant de l'implémentation (actuellement options WordPress)
- Facilite les tests et les migrations futures

### 3. **Dependency Injection (Simple)**
- Instances uniques créées via getters statiques
- Facilite le remplacement pour les tests
```php
private static function getRecoveryService(): BlockRecoveryService
private static function getValidationRepo(): ValidationRepository
```

### 4. **Facade Pattern**
- `Feature.php` expose une API simple pour WordPress
- Masque la complexité des services sous-jacents

## 📝 Conventions de code

### Nommage
- **Classes** : PascalCase (ex: `BlockRecoveryService`)
- **Méthodes publiques** : camelCase (ex: `recoverSinglePost`)
- **Méthodes privées** : camelCase (ex: `cleanBlock`)
- **Constantes** : UPPER_SNAKE_CASE (ex: `OPTION_KEY`)

### Documentation
- Tous les fichiers ont un en-tête de description
- Toutes les méthodes publiques sont documentées avec PHPDoc
- Les paramètres et retours sont typés

### Retours de méthodes
Format standardisé pour les opérations :
```php
[
  'success' => bool,
  'data' => [
    'message' => string,
    // ... autres données
  ]
]
```

## 🧪 Tests (futurs)

Structure proposée pour les tests :
```
tests/php/Features/BlockRecovery/
├── Core/
│   ├── BlockRecoveryServiceTest.php
│   └── ValidationRepositoryTest.php
└── FeatureTest.php
```

## 🔒 Sécurité

### Validations
- Toutes les entrées AJAX sont validées (nonce, permissions)
- Sanitization des paramètres utilisateur
- Vérification des capacités WordPress

### Permissions requises
- `Constants::CAP_USE_SCANNER` pour toutes les opérations

## 📊 Performance

### Optimisations appliquées
- **Traitement parallèle** : Batch de 4 posts simultanés (4x plus rapide)
- **Iframes invisibles** : Pas d'onglets visibles pour l'utilisateur
- **Polling optimisé** : 50ms pour la détection de fin de sauvegarde
- **Cache WordPress** : `clean_post_cache()` après modification

### Métriques attendues
- 5 posts : ~12 secondes (vs 50s avant)
- 10 posts : ~24 secondes (vs 100s avant)
- 50 posts : ~120 secondes (vs 500s avant)

## 🚀 Évolutions futures

### Améliorations possibles
1. **Tests unitaires** : Ajouter une couverture de tests complète
2. **Batch size configurable** : Permettre à l'utilisateur de choisir
3. **Historique** : Logger les récupérations effectuées
4. **Retry automatique** : En cas d'échec de récupération
5. **Progress tracking** : Barre de progression plus détaillée
6. **API asynchrone** : Utiliser WP Cron pour très gros volumes

### Refactoring potentiel
- Extraire les constantes dans une classe `Constants`
- Créer un `EventDispatcher` pour les hooks WordPress
- Implémenter un vrai système de DI (ex: PHP-DI)
