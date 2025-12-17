# Block Recovery - Récupération Native & Multiple de Blocs Gutenberg

## Description

Cette feature permet de récupérer automatiquement les blocs Gutenberg en mode recovery en utilisant la **fonction native de WordPress** (`wp.blocks.createBlock()`), avec support de la **récupération multiple intelligente** après validation.

## 🎯 Fonctionnalités

### 1. Récupération Simple (Native WordPress)
- Utilise `wp.blocks.createBlock()` comme le bouton "Tentative de récupération"
- Ouvre l'éditeur avec le bloc pré-sélectionné
- Récupération automatique au chargement de l'éditeur
- Vérification manuelle par l'utilisateur

### 2. Système de Validation
- Bouton "Valider" pour confirmer une récupération réussie
- Compteur de validations par type de bloc
- Stockage dans option WordPress `diagnostic_validated_blocks`
- Statut visuel : ✓ Validé / ⚠ Non validé

### 3. Filtrage par Bloc
- Dropdown avec liste des blocs uniques
- Affiche le nombre d'occurrences par bloc
- Filtrage en temps réel du tableau
- Boutons "Filtrer" et "Réinitialiser"

### 4. Récupération Multiple Automatique
- **Activé après 2 validations manuelles** du même type de bloc
- Bouton "Récupérer tous les blocs sélectionnés"
- Modal de progression avec barre et log
- Traitement séquentiel (un post à la fois)
- Sauvegarde automatique via Gutenberg
- Fermeture automatique des onglets d'édition

## 📁 Architecture

```
BlockRecovery/
├── Feature.php (230 lignes)
│   ├── handle_recovery_ajax() → Récupération simple
│   ├── handle_validation_ajax() → Validation manuelle
│   ├── handle_multiple_recovery_ajax() → Récupération multiple
│   ├── get_validated_blocks() → Obtenir blocs validés
│   └── increment_validation_count() → +1 validation
│
├── UI/BlockRecoveryScreen.php (160 lignes)
│   ├── render() → Interface complète
│   ├── get_recovery_blocks() → Liste des blocs en recovery
│   └── get_unique_block_names() → Blocs uniques pour filtre
│
├── Assets/
│   ├── js/
│   │   ├── block-recovery-advanced.js (380 lignes)
│   │   │   ├── Filtrage par bloc
│   │   │   ├── Récupération simple
│   │   │   ├── Validation manuelle
│   │   │   ├── Récupération multiple
│   │   │   └── Modal de progression
│   │   └── gutenberg-recovery.js (120 lignes)
│   │       └── Récupération native dans l'éditeur
│   └── css/
│       └── block-recovery.css (180 lignes)
│           └── Styles complets
└── README.md
```

## 🔄 Workflow Complet

### Phase 1: Validation Manuelle (2 fois minimum)

```
1. Scanner détecte bloc en recovery
   ↓
2. Interface affiche liste avec bouton "Récupérer"
   ↓
3. Clic "Récupérer" → Ouvre éditeur avec ?recovery_block=nom-bloc
   ↓
4. gutenberg-recovery.js détecte le paramètre
   ↓
5. wp.blocks.createBlock() recrée le bloc (FONCTION NATIVE)
   ↓
6. Utilisateur vérifie visuellement le bloc
   ↓
7. Utilisateur sauvegarde le post
   ↓
8. Retour sur la page → Clic "Valider"
   ↓
9. Compteur de validation +1
   ↓
   (Répéter 2 fois pour activer la récupération automatique)
```

### Phase 2: Récupération Automatique ✅

```
10. Bouton "Récupérer tous" devient actif (vert)
    ↓
11. Sélectionner le bloc dans le filtre dropdown
    ↓
12. Affichage : "✓ Prêt : X post(s) à récupérer"
    ↓
13. Clic "Récupérer tous les blocs sélectionnés"
    ↓
14. Confirmation utilisateur
    ↓
15. Modal s'ouvre avec barre de progression
    ↓
16. Pour chaque post:
    • Ouvre éditeur en iframe caché (?auto_save=1)
    • Gutenberg récupère automatiquement
    • Sauvegarde automatique (dispatch savePost)
    • Ferme l'iframe
    • Log : "✓ Récupéré : Nom du post"
    • Barre de progression mise à jour
    ↓
17. Résumé : "✓ Récupération terminée : X succès, Y échecs"
    ↓
18. Rafraîchissement automatique de la page
```

## 💾 Structure des Données

### Option WordPress : `diagnostic_validated_blocks`

```php
[
  'create-block/test-block' => [
    'count' => 2,                        // Nombre de validations
    'first_validated_at' => '2025-10-07 14:30:00',
    'last_validated_at' => '2025-10-07 15:45:00'
  ],
  'create-block/another-block' => [
    'count' => 1,
    'first_validated_at' => '2025-10-07 16:00:00',
    'last_validated_at' => '2025-10-07 16:00:00'
  ]
]
```

### Transient : `diagnostic_scanner_last_results` (2h)

Résultats du Scanner avec structure :
```php
[
  'posts' => [
    [
      'id' => 123,
      'issues' => [
        [
          'type' => 'BLOCK_RECOVERY_MODE',
          'blockName' => 'create-block/test-block',
          'severity' => 'high',
          'message' => '...'
        ]
      ]
    ]
  ]
]
```

## 🎨 Interface Utilisateur

### Barre de Contrôles

```
┌─────────────────────────────────────────────────────────────┐
│ [Dropdown: Tous les blocs ▼] [Filtrer] [Réinitialiser]     │
│ [🔄 Récupérer tous les blocs sélectionnés] ✓ Prêt: 5 posts │
└─────────────────────────────────────────────────────────────┘
```

**États du bouton "Récupérer tous" :**
- ⚫ Grisé : Aucun bloc sélectionné
- 🟡 Grisé : Bloc sélectionné mais non validé (< 2 validations)
- 🟢 Actif : Bloc validé 2+ fois, prêt pour récupération automatique

### Tableau des Blocs

| Nom du bloc          | Post      | Statut         | Actions                    |
|----------------------|-----------|----------------|----------------------------|
| `create-block/test`  | Article 1 | ✓ Validé       | [Récupérer] [Valider]      |
| `create-block/test`  | Article 2 | ⚠ Non validé   | [Récupérer] [Valider]      |

### Modal de Progression

```
┌───────────────────────────────────────────┐
│ Récupération multiple en cours...        │
│                                           │
│ ████████████░░░░░░░░░░░░░░░░░  40%       │
│ 2 / 5                                     │
│                                           │
│ ⏳ Récupération de : Article 3...        │
│ ✓ Récupéré : Article 1                   │
│ ✓ Récupéré : Article 2                   │
│                                           │
│                        [Fermer (disabled)]│
└───────────────────────────────────────────┘
```

## 🔌 API & Endpoints

### AJAX Actions

#### `block_recovery_single`
**Récupération simple d'un bloc**
- Paramètres : `post_id`, `block_name`, `nonce`
- Retourne : URL de l'éditeur
- Utilisé par : Bouton "Récupérer"

#### `block_recovery_validate`
**Validation d'une récupération réussie**
- Paramètres : `block_name`, `nonce`
- Incrémente le compteur de validation
- Retourne : Statut de validation, compteur mis à jour
- Utilisé par : Bouton "Valider"

#### `block_recovery_multiple`
**Liste des posts pour récupération multiple**
- Paramètres : `block_name`, `nonce`
- Vérifie validation (count >= 2)
- Retourne : Liste des posts à récupérer
- Utilisé par : Bouton "Récupérer tous"

### API WordPress Natives Utilisées

#### Côté PHP
- `parse_blocks($content)` - Parser le contenu en blocs
- `get_transient()` - Récupérer les résultats du Scanner
- `update_option()` - Sauvegarder les validations
- `get_option()` - Récupérer les validations

#### Côté JavaScript (Gutenberg)
- `wp.data.select('core/block-editor').getBlocks()` - Obtenir tous les blocs
- `wp.blocks.getBlockType(blockName)` - Vérifier le type de bloc
- **`wp.blocks.createBlock(blockName, attrs)`** - **Récupération native** ⭐
- `wp.data.dispatch('core/block-editor').replaceBlock()` - Remplacer le bloc
- `wp.data.dispatch('core/editor').savePost()` - Sauvegarde automatique
- `wp.data.dispatch('core/notices').createSuccessNotice()` - Notifications

## 🔒 Sécurité

✅ **Vérifications implémentées :**
- Nonce vérifié sur tous les endpoints AJAX
- Permissions vérifiées (`Constants::CAP_USE_SCANNER`)
- Sanitization des paramètres (`sanitize_text_field`, `absint`)
- Validation du compteur (minimum 2 validations requises)
- Confirmation utilisateur avant récupération multiple

## 🧪 Utilisation

### Récupération Simple

1. **Lancer un scan** : Diagnostic > Scanner
2. **Aller sur** : Diagnostic > Récupération de Blocs
3. **Cliquer "Récupérer"** pour un bloc
4. **Vérifier** le bloc dans l'éditeur
5. **Sauvegarder** le post
6. **Revenir et cliquer "Valider"**

### Récupération Multiple

1. **Valider 2 fois** le même type de bloc (voir ci-dessus)
2. **Sélectionner le bloc** dans le dropdown
3. **Vérifier** que le bouton "Récupérer tous" est actif (vert)
4. **Cliquer** "Récupérer tous les blocs sélectionnés"
5. **Confirmer** l'action
6. **Observer** la progression dans la modal
7. **Attendre** la fin du traitement
8. **Page rafraîchie** automatiquement

## 📊 Statistiques & Debug

### Logs Console (Gutenberg)

```javascript
[Block Recovery] Démarrage de la récupération native pour: create-block/test-block
[Block Recovery] Mode auto-save: true
[Block Recovery] Blocs trouvés: 5
[Block Recovery] Bloc 0 : create-block/test-block Valid: false
[Block Recovery] Tentative de récupération du bloc: abc123
[Block Recovery] ✅ Bloc récupéré avec succès
[Block Recovery] Sauvegarde automatique déclenchée
```

### Commandes Utiles

```php
// Voir les blocs validés
get_option('diagnostic_validated_blocks');

// Réinitialiser les validations
delete_option('diagnostic_validated_blocks');

// Voir les résultats du scanner
get_transient('diagnostic_scanner_last_results');
```

## ⚙️ Configuration

### Seuil de Validation

Par défaut : **2 validations** requises avant activation de la récupération automatique.

Pour modifier, éditer dans `Feature.php` :

```php
// Ligne 152
if ($data['count'] >= 2) // Changer 2 par la valeur souhaitée
```

### Délai de Récupération

Délai entre chaque post lors de la récupération multiple : **3 secondes**

Pour modifier, éditer dans `block-recovery-advanced.js` :

```javascript
// Ligne 280
}, 3000); // Changer 3000 (ms) par la valeur souhaitée
```

## 🐛 Troubleshooting

### Le bouton "Récupérer tous" reste grisé
- Vérifier que le bloc a été validé au moins 2 fois
- Sélectionner un bloc dans le dropdown
- Vérifier la console : `blockRecoveryConfig.validatedBlocks`

### La récupération automatique ne fonctionne pas
- Ouvrir la console du navigateur (F12)
- Chercher les logs `[Block Recovery]`
- Vérifier que le JavaScript du bloc est bien chargé
- Vérifier que le bloc est enregistré (`wp.blocks.getBlockType()`)

### Les validations ne sont pas sauvegardées
- Vérifier les permissions utilisateur
- Tester : `get_option('diagnostic_validated_blocks')`
- Vérifier les logs AJAX dans l'onglet Network

## 📝 Notes Importantes

⚠️ **Limitations :**
- La récupération multiple utilise des iframes cachés (peut être lent pour beaucoup de posts)
- Nécessite que le JavaScript du bloc soit chargé dans l'éditeur
- Fonctionne uniquement dans l'éditeur Gutenberg (pas dans l'API REST)

✅ **Avantages :**
- Utilise la fonction native de WordPress (fiable)
- Pas de manipulation directe de la base de données
- Aucun problème de cache JavaScript
- Validation manuelle avant récupération automatique
- Traçabilité des validations

## 🎯 Roadmap

Améliorations futures possibles :
- [ ] Traitement par batch (plusieurs posts en parallèle)
- [ ] Export/Import des validations
- [ ] Statistiques détaillées par bloc
- [ ] Historique des récupérations
- [ ] Annulation de validation
- [ ] Notification email après récupération multiple
