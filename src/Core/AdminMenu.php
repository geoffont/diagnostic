<?php

/**
 * Gestionnaire des menus d'administration WordPress
 *
 * Ce fichier gère la création et l'organisation des menus d'administration
 * du plugin dans l'interface WordPress. Il configure le menu principal
 * et coordonne l'ajout des sous-menus par les différentes fonctionnalités.
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
 * - Création du menu principal Diagnostic
 * - Configuration des permissions d'accès
 * - Définition des icônes et positions
 * - Coordination avec les sous-menus des fonctionnalités
 * - Gestion de la page d'accueil du plugin
 *
 * @dependencies:
 * - WordPress admin_menu hook
 * - WordPress add_menu_page() function
 * - Constants (capacités et noms)
 *
 * @related_files:
 * - Plugin.php (initialisation)
 * - Features/Feature.php (sous-menus)
 * - Constants.php (permissions et noms)
 *
 * @wordpress_hooks:
 * - admin_menu (ajout des menus)
 */

namespace Company\Diagnostic\Core;

use Company\Diagnostic\Common\Constants;

/**
 * Gestionnaire des menus d'administration
 * 
 * Classe statique responsable de la création du menu principal
 * et de la coordination des sous-menus du plugin.
 */
class AdminMenu
{
  /**
   * Initialiser le système de menus d'administration
   * 
   * Configure le hook WordPress et initialise le menu principal.
   * Doit être appelée lors de l'initialisation du plugin.
   * 
   * @return void
   */
  public static function init(): void
  {
    add_action('admin_menu', [self::class, 'register_menu']);
  }

  /**
   * Enregistrer les menus WordPress
   */
  public static function register_menu(): void
  {
    // Menu principal
    add_menu_page(
      __('Diagnostic', Constants::TEXT_DOMAIN),
      __('Diagnostic', Constants::TEXT_DOMAIN),
      Constants::CAP_MANAGE_DIAGNOSTIC,
      'diagnostic',
      [self::class, 'dashboard_page'],
      'dashicons-chart-area',
      100
    );

    // Sous-menu Dashboard
    add_submenu_page(
      'diagnostic',
      __('Dashboard', Constants::TEXT_DOMAIN),
      __('Dashboard', Constants::TEXT_DOMAIN),
      Constants::CAP_MANAGE_DIAGNOSTIC,
      'diagnostic',
      [self::class, 'dashboard_page']
    );
  }

  /**
   * Page du dashboard principal
   */
  public static function dashboard_page(): void
  {
    if (!current_user_can(Constants::CAP_MANAGE_DIAGNOSTIC)) {
      wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', Constants::TEXT_DOMAIN));
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Diagnostic Dashboard', Constants::TEXT_DOMAIN) . '</h1>';
    echo '<p>' . esc_html__('Tableau de bord principal du plugin Diagnostic.', Constants::TEXT_DOMAIN) . '</p>';

    // Afficher les features disponibles
    self::display_features_status();

    echo '</div>';
  }

  /**
   * Afficher le statut des features
   */
  private static function display_features_status(): void
  {
    $plugin = \Company\Diagnostic\Plugin::instance();
    $features = $plugin->get_features();

    if (empty($features)) {
      return;
    }

    echo '<div class="diagnostic-features-status">';
    echo '<h2>' . esc_html__('Features actives', Constants::TEXT_DOMAIN) . '</h2>';
    echo '<ul>';

    foreach ($features as $feature_class) {
      $feature_name = self::get_feature_display_name($feature_class);
      echo '<li>✅ ' . esc_html($feature_name) . '</li>';
    }

    echo '</ul>';
    echo '</div>';
  }

  /**
   * Obtenir le nom d'affichage d'une feature
   * 
   * Extrait le nom lisible de la feature depuis son namespace complet.
   * 
   * @param string $feature_class Le nom complet de la classe
   * @return string Le nom d'affichage de la feature
   */
  private static function get_feature_display_name(string $feature_class): string
  {
    // Exemple: "Company\Diagnostic\Features\Scanner\Feature" -> "Scanner"
    // Exemple: "Company\Diagnostic\Features\PostGenerator\Feature" -> "PostGenerator"

    $parts = explode('\\', $feature_class);

    // Recherche du segment "Features" et prend le suivant
    $features_index = array_search('Features', $parts);
    if ($features_index !== false && isset($parts[$features_index + 1])) {
      $feature_name = $parts[$features_index + 1];

      // Conversion des noms techniques en noms d'affichage
      switch ($feature_name) {
        case 'PostGenerator':
          return __('Générateur de Posts', Constants::TEXT_DOMAIN);
        case 'Scanner':
          return __('Scanner de Blocs', Constants::TEXT_DOMAIN);
        default:
          return $feature_name;
      }
    }

    // Fallback si la structure ne correspond pas
    return basename(str_replace('\\', '/', $feature_class));
  }
}
