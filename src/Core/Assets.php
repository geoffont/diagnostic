<?php

/**
 * Gestionnaire global des assets CSS et JavaScript
 *
 * Ce fichier centralise la gestion des assets (CSS/JS) communs à tout le plugin.
 * Il gère les styles et scripts partagés, les dépendances globales et
 * l'enregistrement conditionnel des ressources selon les pages d'administration.
 *
 * @package     Company\Diagnostic\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Gestion des assets globaux du plugin
 * - Enregistrement des styles et scripts communs
 * - Gestion des dépendances partagées
 * - Chargement conditionnel selon les pages
 * - Optimisation des performances (minification, cache)
 *
 * @dependencies:
 * - WordPress wp_enqueue_style/script functions
 * - WordPress admin_enqueue_scripts hook
 * - Constants (URLs et versions)
 *
 * @related_files:
 * - Plugin.php (initialisation)
 * - Features/Feature.php (assets specifiques)
 * - Constants.php (configuration)
 *
 * @hooks:
 * - admin_enqueue_scripts (chargement assets)
 */

namespace Company\Diagnostic\Core;

use Company\Diagnostic\Common\Constants;

/**
 * Gestionnaire global des assets
 * 
 * Classe statique responsable de la gestion centralisée
 * des ressources CSS et JavaScript communes au plugin.
 */
class Assets
{
  /**
   * Initialiser le système de gestion des assets
   * 
   * Configure les hooks WordPress pour le chargement conditionnel
   * des assets selon les pages d'administration visitées.
   * 
   * @return void
   */
  public static function init(): void
  {
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
  }

  /**
   * Charger les assets admin globaux
   */
  public static function enqueue_admin_assets(string $hook): void
  {
    // TEMPORAIRE : Désactivé pendant la migration PSR-4
    // TODO : Migrer les assets admin globaux
    return;

    // Charger seulement sur les pages du plugin diagnostic
    if (strpos($hook, 'diagnostic') === false) {
      return;
    }

    // CSS global du plugin
    wp_enqueue_style(
      'diagnostic-admin',
      DIAGNOSTIC_PLUGIN_URL . 'assets/css/admin.css',
      [],
      Constants::VERSION
    );

    // JS global du plugin
    wp_enqueue_script(
      'diagnostic-admin',
      DIAGNOSTIC_PLUGIN_URL . 'assets/js/admin.js',
      ['jquery'],
      Constants::VERSION,
      true
    );

    // Variables globales pour JavaScript
    wp_localize_script('diagnostic-admin', 'diagnosticGlobal', [
      'version' => Constants::VERSION,
      'textDomain' => Constants::TEXT_DOMAIN,
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('diagnostic_global_nonce'),
      'strings' => [
        'loading' => __('Chargement...', Constants::TEXT_DOMAIN),
        'error' => __('Erreur', Constants::TEXT_DOMAIN),
        'success' => __('Succès', Constants::TEXT_DOMAIN),
      ]
    ]);
  }

  /**
   * Obtenir l'URL d'un asset
   */
  public static function get_asset_url(string $path): string
  {
    return DIAGNOSTIC_PLUGIN_URL . ltrim($path, '/');
  }

  /**
   * Obtenir le chemin d'un asset
   */
  public static function get_asset_path(string $path): string
  {
    return DIAGNOSTIC_PLUGIN_PATH . ltrim($path, '/');
  }

  /**
   * Vérifier si un asset existe
   */
  public static function asset_exists(string $path): bool
  {
    return file_exists(self::get_asset_path($path));
  }
}
