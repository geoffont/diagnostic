<?php

/**
 * Fonctionnalité Block Recovery - Point d'entrée et configuration
 *
 * Ce fichier configure et initialise la fonctionnalité Block Recovery du plugin.
 * Il gère la récupération automatique des blocs Gutenberg en mode recovery via
 * REST API et AJAX, coordonne les services de récupération et de validation.
 *
 * @package     Company\GutenbergRecovery\Features\BlockRecovery
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       2.0.0
 * @created     2025-10-21
 * @modified    2025-10-21
 *
 * @responsibilities:
 * - Initialisation de la fonctionnalité Block Recovery
 * - Configuration des endpoints REST API
 * - Configuration des hooks AJAX
 * - Enregistrement des menus d'administration
 * - Chargement des assets (CSS/JS)
 * - Coordination entre BlockRecoveryService et ValidationRepository
 *
 * @dependencies:
 * - WordPress REST API
 * - WordPress AJAX API
 * - WordPress admin_menu hook
 * - WordPress admin_enqueue_scripts hook
 * - Gutenberg Editor
 * - Constants (configuration globale)
 * - BlockRecoveryScreen (interface utilisateur)
 * - BlockRecoveryService (logique de récupération)
 * - ValidationRepository (gestion des validations)
 *
 * @related_files:
 * - UI/Screens/BlockRecoveryScreen.php (écran d'administration)
 * - Core/BlockRecoveryService.php (service de récupération)
 * - Core/ValidationRepository.php (repository de validations)
 * - Assets/js/block-recovery-advanced.js (interface principale)
 * - Assets/js/gutenberg-recovery.js (récupération dans éditeur)
 */

namespace Company\GutenbergRecovery\Features\BlockRecovery;

use Company\GutenbergRecovery\Common\Constants;
use Company\GutenbergRecovery\Features\BlockRecovery\UI\Screens\BlockRecoveryScreen;
use Company\GutenbergRecovery\Features\BlockRecovery\Core\BlockRecoveryService;
use Company\GutenbergRecovery\Features\BlockRecovery\Core\ValidationRepository;

class Feature
{
  private static ?BlockRecoveryService $recoveryService = null;
  private static ?ValidationRepository $validationRepo = null;

  /**
   * Obtenir l'instance du service de récupération
   */
  private static function getRecoveryService(): BlockRecoveryService
  {
    if (self::$recoveryService === null) {
      self::$recoveryService = new BlockRecoveryService();
    }
    return self::$recoveryService;
  }

  /**
   * Obtenir l'instance du repository de validation
   */
  private static function getValidationRepo(): ValidationRepository
  {
    if (self::$validationRepo === null) {
      self::$validationRepo = new ValidationRepository();
    }
    return self::$validationRepo;
  }
  public static function init(): void
  {
    add_action('wp_ajax_block_recovery_single', [self::class, 'handle_recovery_ajax']);
    add_action('wp_ajax_block_recovery_validate', [self::class, 'handle_validation_ajax']);
    add_action('wp_ajax_block_recovery_reset_validations', [self::class, 'handle_reset_validations_ajax']);
    add_action('wp_ajax_block_recovery_refresh_data', [self::class, 'handle_refresh_data_ajax']);
    add_action('diagnostic_scanner_complete', [self::class, 'save_scanner_results']);
    add_action('admin_menu', [self::class, 'register_menu']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    add_action('enqueue_block_editor_assets', [self::class, 'enqueue_gutenberg_recovery']);
  }

  public static function register_menu(): void
  {
    add_submenu_page(
      'gutenberg-recovery',
      __('Récupération de Blocs', Constants::TEXT_DOMAIN),
      __('Récupération de Blocs', Constants::TEXT_DOMAIN),
      Constants::CAP_USE_SCANNER,
      'diagnostic_block_recovery',
      [self::class, 'render_screen']
    );
  }

  public static function enqueue_assets(string $hook): void
  {
    if (strpos($hook, 'diagnostic_block_recovery') === false) {
      return;
    }

    wp_enqueue_style(
      'diagnostic-block-recovery',
      GUTENBERG_RECOVERY_PLUGIN_URL . 'src/Features/BlockRecovery/Assets/css/block-recovery.css',
      [],
      Constants::VERSION
    );

    wp_enqueue_script(
      'diagnostic-block-recovery',
      GUTENBERG_RECOVERY_PLUGIN_URL . 'src/Features/BlockRecovery/Assets/js/block-recovery-advanced.js',
      ['jquery'],
      Constants::VERSION,
      true
    );

    wp_localize_script('diagnostic-block-recovery', 'blockRecoveryConfig', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'restUrl' => rest_url(),
      'nonce' => wp_create_nonce(Constants::NONCE_SCANNER),
      'restNonce' => wp_create_nonce('wp_rest'),
      'validatedBlocks' => self::getValidationRepo()->getAll(),
    ]);
  }

  /**
   * Charger le script de récupération dans l'éditeur Gutenberg
   */
  public static function enqueue_gutenberg_recovery(): void
  {
    wp_enqueue_script(
      'diagnostic-gutenberg-recovery',
      GUTENBERG_RECOVERY_PLUGIN_URL . 'src/Features/BlockRecovery/Assets/js/gutenberg-recovery.js',
      ['wp-blocks', 'wp-element', 'wp-data', 'wp-notices'],
      Constants::VERSION,
      true
    );
  }

  public static function render_screen(): void
  {
    $screen = new BlockRecoveryScreen();
    $screen->render();
  }

  public static function save_scanner_results($scanner_results): void
  {
    if (!is_array($scanner_results) || empty($scanner_results['posts'])) {
      return;
    }

    update_option('diagnostic_scanner_last_results', $scanner_results);
    set_transient('diagnostic_scanner_last_results', $scanner_results, 2 * HOUR_IN_SECONDS);
  }

  public static function handle_recovery_ajax(): void
  {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $block_name = sanitize_text_field($_POST['block_name'] ?? '');

    if (!$post_id || !$block_name) {
      wp_send_json_error(['message' => 'Paramètres manquants']);
      return;
    }

    $post = get_post($post_id);
    if (!$post) {
      wp_send_json_error(['message' => 'Post introuvable']);
      return;
    }

    // Retourner l'URL de l'éditeur pour que JavaScript déclenche la récupération native
    wp_send_json_success([
      'message' => 'Ouverture de l\'éditeur pour récupération',
      'edit_url' => get_edit_post_link($post_id, 'raw'),
      'block_name' => $block_name
    ]);
  }

  /**
   * Gérer la validation d'un bloc après récupération et sauvegarde
   */
  public static function handle_validation_ajax(): void
  {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    $block_name = sanitize_text_field($_POST['block_name'] ?? '');

    if (!$post_id || !$block_name) {
      wp_send_json_error(['message' => 'Paramètres manquants']);
    }

    $validationRepo = self::getValidationRepo();
    $success = $validationRepo->markAsValidated($post_id, $block_name);

    if ($success) {
      $validated_count = $validationRepo->countValidatedForBlock($block_name);
      wp_send_json_success([
        'message' => 'Post marqué comme validé',
        'validated_count' => $validated_count,
        'can_auto_recover' => $validationRepo->canAutoRecover($block_name)
      ]);
    }

    wp_send_json_error(['message' => 'Erreur lors de la validation']);
  }

  /**
   * Gérer la réinitialisation de toutes les validations (AJAX)
   */
  public static function handle_reset_validations_ajax(): void
  {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    $deleted = self::getValidationRepo()->resetAll();

    if ($deleted) {
      wp_send_json_success(['message' => 'Toutes les validations ont été réinitialisées']);
    } else {
      wp_send_json_success(['message' => 'Aucune validation à réinitialiser']);
    }
  }

  /**
   * Rafraîchir les données du scanner (AJAX)
   */
  public static function handle_refresh_data_ajax(): void
  {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    // Invalider le cache
    delete_transient('diagnostic_scanner_last_results');

    wp_send_json_success(['message' => 'Données rafraîchies']);
  }

  /**
   * Méthodes publiques pour compatibilité avec BlockRecoveryScreen
   */
  public static function get_validated_blocks(): array
  {
    return self::getValidationRepo()->getAll();
  }

  public static function is_post_validated(int $post_id, string $block_name): bool
  {
    return self::getValidationRepo()->isValidated($post_id, $block_name);
  }

  public static function count_validated_posts_for_block(string $block_name): int
  {
    return self::getValidationRepo()->countValidatedForBlock($block_name);
  }
}
