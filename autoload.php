<?php

/**
 * Autoloader PSR-4 pour le plugin Gutenberg Recovery
 *
 * Ce fichier implémente un système d'autoloading PSR-4 personnalisé
 * pour charger automatiquement les classes du plugin selon leur namespace.
 * Il permet d'éviter les require/include manuels et suit les standards
 * modernes de chargement de classes PHP.
 *
 * @package     Company\GutenbergRecovery
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-12-19
 *
 * @responsibilities:
 * - Chargement automatique des classes PSR-4
 * - Mapping namespace vers fichiers
 * - Gestion des erreurs de chargement
 * - Performance optimisée (chargement à la demande)
 *
 * @dependencies:
 * - PHP SPL spl_autoload_register
 * - Système de fichiers
 * - Structure PSR-4 du plugin
 *
 * @related_files:
 * - gutenberg-recovery.php (inclusion de ce fichier)
 * - src/ (classes à charger)
 *
 * @namespace_mapping:
 * - Company\GutenbergRecovery\ → src/
 * - Company\GutenbergRecovery\Core\ → src/Core/
 * - Company\GutenbergRecovery\Features\ → src/Features/
 */

// Fonction d'autoload PSR-4
spl_autoload_register(function ($class) {
  // Préfixe du namespace
  $prefix = 'Company\\GutenbergRecovery\\';

  // Dossier de base pour les fichiers
  $base_dir = __DIR__ . '/src/';

  // Vérifier si la classe utilise le namespace du plugin
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    // Pas notre namespace, laisser les autres autoloaders
    return;
  }

  // Récupérer le nom de la classe relative
  $relative_class = substr($class, $len);

  // Remplacer les séparateurs de namespace par des séparateurs de dossier
  $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

  // Charger le fichier s'il existe
  if (file_exists($file)) {
    require $file;
  }
});
