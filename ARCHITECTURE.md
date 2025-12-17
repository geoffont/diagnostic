# Architecture du Plugin Diagnostic 2.0.0

## Vue d'ensemble

Le plugin Diagnostic suit une architecture modulaire avec séparation claire des responsabilités. Chaque fichier a un rôle spécifique et bien défini.

## Structure et Responsabilités

### 📁 Racine du Plugin

| Fichier | Responsabilité | Dépendances |
|---------|---------------|-------------|
| `diagnostic.php` | Point d'entrée principal | WordPress |
| `autoload.php` | Chargement automatique des classes | SPL |
| `index.php` | Protection de sécurité | Aucune |
| `debug-assets.php` | Debug des assets en développement | WordPress |

### 📁 src/

#### 🏗️ Plugin.php
- **Responsabilité** : Orchestration générale du plugin
- **Pattern** : Singleton
- **Dépendances** : Core/*, Features/*
- **Rôle** : Initialisation, coordination des modules

#### 📁 Common/

| Fichier | Responsabilité | Type |
|---------|---------------|------|
| `Constants.php` | Constantes globales | Configuration |
| `Functions.php` | Utilitaires purs | Helpers |

#### 📁 Core/

| Fichier | Responsabilité | WordPress Hook |
|---------|---------------|----------------|
| `AdminMenu.php` | Menus d'administration | `admin_menu` |
| `Assets.php` | Gestion des assets globaux | `admin_enqueue_scripts` |

### 📁 Features/

#### 🔍 Scanner/

```
Scanner/
├── Feature.php                 # Point d'entrée + configuration
├── Core/
│   ├── GutenbergValidator.php  # Logique métier d'analyse
│   ├── ContentAnalyzer.php     # Analyse détaillée de contenu
│   └── BlockRegistry.php       # Registre des types de blocs
├── UI/Screens/
│   └── ScannerScreen.php       # Interface d'administration
└── Assets/
    ├── js/scanner-interface.js # Interface JavaScript
    └── css/scanner-interface.css # Styles
```

**Responsabilités Scanner :**
- `Feature.php` : Initialisation, menus, assets, AJAX
- `GutenbergValidator.php` : Analyse par batch, validation blocs
- `ContentAnalyzer.php` : Analyse fine du contenu
- `ScannerScreen.php` : Interface admin, génération HTML
- `scanner-interface.js` : Pagination, AJAX, UI dynamique

#### ⚡ PostGenerator/

```
PostGenerator/
├── Feature.php                      # Point d'entrée + configuration
├── Core/
│   ├── PostContentGenerator.php    # Génération de contenu
│   └── BlockGenerator.php          # Génération de blocs
├── UI/Screens/
│   └── PostGeneratorScreen.php     # Interface d'administration
└── Assets/
    ├── js/post-generator.js         # Interface JavaScript
    └── css/post-generator.css       # Styles
```

**Responsabilités PostGenerator :**
- `Feature.php` : Initialisation, menus, assets, hooks
- `PostContentGenerator.php` : Logique de génération
- `BlockGenerator.php` : Création de blocs Gutenberg
- `PostGeneratorScreen.php` : Interface admin, formulaires
- `post-generator.js` : Formulaires, AJAX, préférences

#### 🔧 BlockRecovery/

```
BlockRecovery/
├── Feature.php                      # Point d'entrée + configuration
├── Core/
│   ├── BlockRecoveryService.php    # Service de récupération
│   └── ValidationRepository.php    # Gestion des validations
├── UI/Screens/
│   └── BlockRecoveryScreen.php     # Interface d'administration
└── Assets/
    ├── js/
    │   ├── block-recovery-advanced.js   # Interface principale
    │   ├── gutenberg-recovery.js        # Récupération dans éditeur
    │   ├── block-recovery.js            # Utilitaires
    │   └── block-recovery-native.js     # Récupération native
    └── css/block-recovery.css           # Styles
```

**Responsabilités BlockRecovery :**
- `Feature.php` : REST API, AJAX, menus, assets
- `BlockRecoveryService.php` : Logique de récupération
- `ValidationRepository.php` : Suivi des posts validés
- `BlockRecoveryScreen.php` : Interface admin, tableau de bord
- `block-recovery-advanced.js` : Récupération batch via iframes
- `gutenberg-recovery.js` : Récupération dans éditeur Gutenberg

## Principes Architecturaux

### 🎯 Un Fichier = Une Responsabilité

Chaque fichier a une responsabilité unique et bien définie :

- **Feature.php** → Configuration et initialisation
- **Core/** → Logique métier pure
- **UI/Screens/** → Interfaces utilisateur
- **Assets/** → Ressources front-end

### 🔄 Séparation des Préoccupations

1. **Configuration** (Feature.php)
2. **Logique Métier** (Core/)
3. **Interface Utilisateur** (UI/)
4. **Présentation** (Assets/)

### 🏗️ Patterns Utilisés

- **Singleton** : Plugin.php
- **Static Classes** : Constants, Functions
- **Namespaces PSR-4** : Organisation modulaire
- **Hooks WordPress** : Intégration native

### 📊 Métriques de Code

```
Total fichiers : 27
├── PHP : 18 fichiers
├── JavaScript : 6 fichiers
└── CSS : 3 fichiers

Responsabilités :
├── Configuration : 6 fichiers
├── Logique métier : 9 fichiers
├── Interface UI : 6 fichiers
└── Assets : 9 fichiers

Par Feature :
├── Scanner : 8 fichiers (PHP: 4, JS: 1, CSS: 1)
├── PostGenerator : 8 fichiers (PHP: 4, JS: 1, CSS: 1)
└── BlockRecovery : 9 fichiers (PHP: 4, JS: 4, CSS: 1)
```

## Documentation Standards

### 📝 En-têtes PHPDoc

Chaque fichier PHP contient :
- Description de la responsabilité
- Auteur et copyright
- Version et dates
- Dépendances
- Fichiers connexes

### 📝 En-têtes JSDoc

Chaque fichier JavaScript contient :
- Description des fonctionnalités
- Dépendances (jQuery, APIs)
- Variables globales
- Fichiers connexes

### 📝 En-têtes CSS

Chaque fichier CSS contient :
- Description des styles
- Sections organisées
- Dépendances
- Responsive design

## État du Code

✅ **Code propre** : Principe de responsabilité unique respecté
✅ **Documentation complète** : En-têtes standardisés
✅ **Architecture modulaire** : Séparation claire
✅ **Standards WordPress** : Hooks et conventions
✅ **Prêt pour production** : Code documenté et organisé

---

*Dernière mise à jour : 21 octobre 2025*
*Version du plugin : 2.0.0*
