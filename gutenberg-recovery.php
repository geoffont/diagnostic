<?php

/**
 * Plugin Name: Gutenberg Recovery
 * Description: Analysez, diagnostiquez et récupérez vos blocs Gutenberg. Scannez vos contenus, détectez les blocs corrompus et récupérez-les automatiquement.
 * Version: 2.0.0
 * Author: Geoffroy Fontaine
 * Text Domain: gutenberg-recovery
 *
 * @package Company\GutenbergRecovery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

// Définir les constantes du plugin
define('GUTENBERG_RECOVERY_PLUGIN_FILE', __FILE__);
define('GUTENBERG_RECOVERY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GUTENBERG_RECOVERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GUTENBERG_RECOVERY_VERSION', '2.0.0');

// Charger l'autoloader PSR-4
require_once __DIR__ . '/autoload.php';

// Initialiser le plugin
use Company\GutenbergRecovery\Plugin;

/**
 * Initialisation du plugin
 */
function gutenberg_recovery_init_plugin()
{
  Plugin::get_instance();
}

// Hook d'initialisation
add_action('plugins_loaded', 'gutenberg_recovery_init_plugin', 1);

/**
 * Hook d'activation du plugin
 */
register_activation_hook(__FILE__, function () {
  // Vérifier les prérequis
  if (version_compare(PHP_VERSION, '7.4', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(__('Ce plugin nécessite PHP 7.4 ou supérieur.', 'gutenberg-recovery'));
  }

  if (version_compare(get_bloginfo('version'), '5.0', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(__('Ce plugin nécessite WordPress 5.0 ou supérieur.', 'gutenberg-recovery'));
  }
});

/**
 * Hook de désactivation du plugin
 */
register_deactivation_hook(__FILE__, function () {
  // Nettoyer les données temporaires
  if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
  }
});
