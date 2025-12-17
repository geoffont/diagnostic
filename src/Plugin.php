<?php

/**
 * Plugin principal du système de diagnostic WordPress
 *
 * Ce fichier contient la classe principale qui orchestre l'ensemble du plugin.
 * Il gère l'initialisation, le chargement des fonctionnalités et la coordination
 * entre les différents modules.
 *
 * @package     Company\Diagnostic
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Initialisation du plugin
 * - Chargement des fonctionnalités (Scanner, PostGenerator)
 * - Gestion du cycle de vie du plugin
 * - Coordination entre les modules
 *
 * @dependencies:
 * - WordPress 5.0+
 * - PHP 8.0+
 * - Common\Constants
 * - Core\AdminMenu
 * - Core\Assets
 * - Features\Scanner\Feature
 * - Features\PostGenerator\Feature
 *
 * @related_files:
 * - diagnostic.php (point d'entrée)
 * - Common/Constants.php (constantes globales)
 * - Core/AdminMenu.php (menus d'administration)
 * - Features/Feature.php (fonctionnalités)
 */

namespace Company\Diagnostic;

use Company\Diagnostic\Common\Constants;
use Company\Diagnostic\Core\AdminMenu;
use Company\Diagnostic\Core\Assets;
use Company\Diagnostic\Features\Scanner\Feature as ScannerFeature;
use Company\Diagnostic\Features\PostGenerator\Feature as PostGeneratorFeature;
use Company\Diagnostic\Features\BlockRecovery\Feature as BlockRecoveryFeature;

/**
 * Classe principale du plugin Diagnostic
 * 
 * Implémente le pattern Singleton pour garantir une seule instance du plugin.
 * Coordonne l'initialisation et le chargement de toutes les fonctionnalités.
 */
final class Plugin
{
  /**
   * Instance unique (Singleton)
   * 
   * @var Plugin|null
   */
  private static ?Plugin $instance = null;

  /**
   * Features enregistrées
   */
  private array $features = [];

  /**
   * État d'initialisation
   */
  private bool $initialized = false;

  /**
   * Initialiser le plugin
   */
  public static function init(): void
  {
    if (self::$instance === null) {
      self::$instance = new self();
      self::$instance->boot();
    }
  }

  /**
   * Obtenir l'instance du plugin
   */
  public static function instance(): Plugin
  {
    if (self::$instance === null) {
      self::init();
    }

    return self::$instance;
  }

  /**
   * Alias pour obtenir l'instance du plugin
   */
  public static function get_instance(): Plugin
  {
    return self::instance();
  }

  /**
   * Constructeur privé (Singleton)
   */
  private function __construct() {}

  /**
   * Démarrage du plugin
   */
  private function boot(): void
  {
    if ($this->initialized) {
      return;
    }

    // Vérifier les prérequis
    if (!$this->check_requirements()) {
      return;
    }

    // Hooks WordPress principaux - ordre correct
    add_action('plugins_loaded', [$this, 'load_textdomain']);
    add_action('init', [$this, 'init_core_components'], 5);  // D'abord les composants core
    add_action('init', [$this, 'register_features'], 10);   // Ensuite les features
    add_action('admin_init', [$this, 'admin_init']);

    $this->initialized = true;
  }

  /**
   * Vérifier les prérequis du plugin
   */
  private function check_requirements(): bool
  {
    // Vérifier PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
      add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo __('Le plugin Diagnostic nécessite PHP 7.4 ou supérieur.', Constants::TEXT_DOMAIN);
        echo '</p></div>';
      });
      return false;
    }

    // Vérifier WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
      add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo __('Le plugin Diagnostic nécessite WordPress 5.0 ou supérieur.', Constants::TEXT_DOMAIN);
        echo '</p></div>';
      });
      return false;
    }

    return true;
  }

  /**
   * Charger les fichiers de langue
   */
  public function load_textdomain(): void
  {
    load_plugin_textdomain(
      Constants::TEXT_DOMAIN,
      false,
      dirname(plugin_basename(DIAGNOSTIC_PLUGIN_PATH)) . '/languages'
    );
  }

  /**
   * Initialiser les composants core
   */
  public function init_core_components(): void
  {
    AdminMenu::init();
    Assets::init();
  }

  /**
   * Enregistrer les features
   */
  public function register_features(): void
  {
    $this->register_feature(ScannerFeature::class);
    $this->register_feature(PostGeneratorFeature::class);
    $this->register_feature(BlockRecoveryFeature::class);
  }

  /**
   * Enregistrer une feature
   */
  private function register_feature(string $feature_class): void
  {
    if (!class_exists($feature_class)) {
      return;
    }

    $this->features[] = $feature_class;

    // Initialiser la feature si elle a une méthode init
    if (method_exists($feature_class, 'init')) {
      $feature_class::init();
    }

    // Déclencher l'action pour signaler le chargement
    do_action(Constants::ACTION_FEATURE_LOADED, $feature_class);
  }

  /**
   * Initialisation admin
   */
  public function admin_init(): void
  {
    // Actions admin spécifiques
  }

  /**
   * Obtenir les features enregistrées
   */
  public function get_features(): array
  {
    return $this->features;
  }

  /**
   * Empêcher le clonage
   */
  private function __clone() {}

  /**
   * Empêcher la désérialisation
   */
  public function __wakeup() {}
}
