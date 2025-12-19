# Gutenberg Recovery - Plugin WordPress

Plugin WordPress complet de diagnostic et récupération de blocs Gutenberg.

## Description

Gutenberg Recovery est un plugin WordPress professionnel qui offre des outils puissants pour analyser, diagnostiquer et récupérer les contenus Gutenberg. Il est conçu pour aider les administrateurs WordPress à maintenir l'intégrité de leurs contenus et à résoudre les problèmes liés aux blocs.

## Fonctionnalités

### 🔍 Scanner de Blocs
- **Analyse complète** : Scanne tous les posts, pages et types de contenu personnalisés
- **Validation Gutenberg** : Détecte les blocs corrompus, invalides ou obsolètes
- **Analyse par batch** : Traitement optimisé pour les sites avec beaucoup de contenu
- **Filtres avancés** : Filtrage par type de post, statut, date
- **Export de résultats** : Génération de rapports détaillés
- **Interface intuitive** : Pagination, tri et recherche en temps réel

### 🔧 Récupération de Blocs
- **Récupération automatique** : Correction intelligente des blocs corrompus
- **Récupération dans l'éditeur** : Intégration directe dans Gutenberg
- **Traitement par batch** : Récupération multiple via système d'iframes
- **Validation en temps réel** : Vérification post-récupération
- **Historique** : Suivi des posts validés et récupérés
- **API REST** : Endpoints pour automatisation

### ⚡ Générateur de Posts
- **Génération de contenu de test** : Création rapide de posts avec blocs Gutenberg
- **Blocs variés** : Paragraphes, titres, images, listes, citations
- **Configuration flexible** : Nombre de posts, type de contenu, statut
- **Prévisualisation** : Aperçu avant génération
- **Nettoyage** : Suppression facile des posts générés

## Installation

### Via GitHub

1. Téléchargez ou clonez ce repository :
```bash
git clone https://github.com/geoffont/gutenberg-recovery.git
```

2. Uploadez le dossier `gutenberg-recovery` dans `/wp-content/plugins/`

3. Activez le plugin dans le menu "Extensions" de WordPress

### Prérequis Système

- **WordPress** : 5.0 ou supérieur
- **PHP** : 7.4 ou supérieur
- **MySQL** : 5.6 ou supérieur
- **Gutenberg** : Éditeur de blocs activé

## Utilisation

### Scanner de Blocs

1. Accédez à **Gutenberg Recovery > Scanner** dans le menu admin WordPress
2. Configurez les filtres (type de post, statut, dates)
3. Lancez l'analyse
4. Consultez les résultats avec détails des erreurs
5. Exportez le rapport si nécessaire

### Récupération de Blocs

1. Accédez à **Gutenberg Recovery > Récupération**
2. Visualisez les posts nécessitant une récupération
3. Options disponibles :
   - Récupération individuelle via l'éditeur
   - Récupération par batch pour traitement multiple
4. Validez les résultats

### Générateur de Posts

1. Accédez à **Gutenberg Recovery > Générateur**
2. Configurez :
   - Nombre de posts à générer
   - Type de contenu (post, page, etc.)
   - Statut de publication
3. Générez le contenu de test
4. Nettoyez les posts générés quand vous n'en avez plus besoin

## Architecture

Le plugin suit une architecture modulaire avec séparation claire des responsabilités :

```
gutenberg-recovery/
├── gutenberg-recovery.php     # Point d'entrée principal
├── autoload.php               # Autoloader PSR-4
├── src/
│   ├── Plugin.php             # Orchestration (Singleton)
│   ├── Common/                # Constantes et fonctions utilitaires
│   ├── Core/                  # Menu admin et assets globaux
│   └── Features/              # Modules fonctionnels
│       ├── Scanner/           # Analyse de blocs
│       ├── BlockRecovery/     # Récupération de blocs
│       └── PostGenerator/     # Génération de contenu
```

Chaque feature contient :
- `Feature.php` : Configuration et initialisation
- `Core/` : Logique métier
- `UI/Screens/` : Interfaces utilisateur
- `Assets/` : CSS et JavaScript

Consultez [ARCHITECTURE.md](ARCHITECTURE.md) pour plus de détails.

## Développement

### Structure des Features

Chaque feature est autonome et suit le même pattern :

```php
Company\GutenbergRecovery\Features\{FeatureName}\
  ├── Feature.php              # Point d'entrée
  ├── Core/                    # Services métier
  ├── UI/Screens/             # Écrans admin
  └── Assets/                  # Ressources front-end
```

### Standards de Code

- **PSR-4** : Autoloading des classes
- **WordPress Coding Standards** : Respect des conventions WordPress
- **Documentation** : PHPDoc et JSDoc pour tous les fichiers
- **Sécurité** : Validation et échappement systématiques

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) : Architecture détaillée du plugin
- [CHANGELOG.md](CHANGELOG.md) : Historique des versions
- [CODE_AUDIT.md](CODE_AUDIT.md) : Audit de code
- [COMPLETION_REPORT.md](COMPLETION_REPORT.md) : Rapport de complétion

## Sécurité

- Protection contre les accès directs
- Nonces WordPress pour toutes les actions
- Sanitization des entrées utilisateur
- Échappement des sorties
- Vérification des capacités utilisateur

## Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Fork le projet
2. Créer une branche pour votre feature (`git checkout -b feature/amazing-feature`)
3. Commiter vos changements (`git commit -m 'Add amazing feature'`)
4. Pusher vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

## Auteur

**Geoffroy Fontaine** - [@geoffont](https://github.com/geoffont)

## License

Ce projet est sous licence privée. Tous droits réservés.

## Support

Pour toute question ou problème :
- Ouvrez une [issue](https://github.com/geoffont/gutenberg-recovery/issues)
- Consultez la documentation du projet

---

**Version actuelle** : 2.0.0
**Dernière mise à jour** : Décembre 2025
