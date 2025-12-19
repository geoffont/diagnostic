<?php

/**
 * Fonctionnalité PostGenerator - Configuration et initialisation
 *
 * Ce fichier configure et initialise la fonctionnalité PostGenerator du plugin.
 * Il gère l'enregistrement des menus, le chargement des assets, les hooks
 * d'administration et sert de point d'entrée pour toute la fonctionnalité
 * de génération de posts de test.
 *
 * @package     Company\GutenbergRecovery\Features\PostGenerator
 * @author      Company Development Team
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Initialisation de la fonctionnalité PostGenerator
 * - Enregistrement des menus d'administration
 * - Chargement conditionnel des assets (CSS/JS)
 * - Configuration des hooks admin_post
 * - Intégration avec l'API WordPress
 *
 * @dependencies:
 * - WordPress admin_menu hook
 * - WordPress admin_enqueue_scripts hook
 * - WordPress admin_post hooks
 * - Constants (configuration globale)
 * - PostGeneratorScreen (interface utilisateur)
 *
 * @related_files:
 * - UI/Screens/PostGeneratorScreen.php (interface)
 * - Core/PostContentGenerator.php (logique métier)
 * - Assets/js/post-generator.js (interface JavaScript)
 * - Assets/css/post-generator.css (styles)
 *
 * @admin_hooks:
 * - admin_post_diagnostic_post_generator (génération)
 */

namespace Company\GutenbergRecovery\Features\PostGenerator;

use Company\GutenbergRecovery\Common\Constants;
use Company\GutenbergRecovery\Features\PostGenerator\UI\Screens\PostGeneratorScreen;

/**
 * Fonctionnalité PostGenerator - Point d'entrée principal
 * 
 * Classe responsable de l'initialisation et de la configuration complète
 * de la fonctionnalité PostGenerator, incluant menus, assets et hooks.
 */
class Feature
{
  /**
   * Initialiser la fonctionnalité PostGenerator
   * 
   * Configure tous les hooks nécessaires au fonctionnement du générateur
   * de posts. Doit être appelée lors de l'initialisation du plugin.
   * 
   * @return void
   */
  public static function init(): void
  {
    // Enregistrer les hooks AJAX
    add_action('wp_ajax_run_post_generator', [PostGeneratorScreen::class, 'ajax_handler']);

    // Hooks pour l'admin
    add_action('admin_menu', [self::class, 'register_menu']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

    // Hook pour traiter les actions POST dans l'admin
    add_action('admin_post_diagnostic_post_generator', [PostGeneratorScreen::class, 'handle_admin_post']);
  }

  /**
   * Enregistrer le menu PostGenerator dans l'admin
   */
  public static function register_menu(): void
  {
    add_submenu_page(
      'gutenberg-recovery',
      __('Générateur de Posts', Constants::TEXT_DOMAIN),
      __('Générateur de Posts', Constants::TEXT_DOMAIN),
      Constants::CAP_USE_POST_GENERATOR,
      'diagnostic-post-generator',
      [PostGeneratorScreen::class, 'render']
    );
  }

  /**
   * Charger les assets spécifiques au PostGenerator
   */
  public static function enqueue_assets(string $hook): void
  {
    // Charger seulement sur les pages du générateur
    if (strpos($hook, 'diagnostic-post-generator') === false) {
      return;
    }

    // CSS spécifique au générateur seulement (pas de JS pour l'instant)
    wp_enqueue_style(
      'diagnostic-post-generator',
      GUTENBERG_RECOVERY_PLUGIN_URL . 'src/Features/PostGenerator/Assets/css/post-generator.css',
      [],
      Constants::VERSION
    );

    // JavaScript désactivé temporairement pour éviter les erreurs
    // wp_enqueue_script(
    //   'diagnostic-post-generator',
    //   GUTENBERG_RECOVERY_PLUGIN_URL . 'src/Features/PostGenerator/Assets/js/post-generator.js',
    //   ['jquery'],
    //   Constants::VERSION,
    //   true
    // );
  }
}
