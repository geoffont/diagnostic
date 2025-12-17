<?php

/**
 * Fonctionnalité Scanner - Point d'entrée et configuration
 *
 * Ce fichier configure et initialise la fonctionnalité Scanner du plugin.
 * Il gère l'enregistrement des menus, le chargement des assets, les hooks AJAX
 * et sert de point d'entrée principal pour toute la fonctionnalité Scanner.
 *
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Initialisation de la fonctionnalité Scanner
 * - Enregistrement des menus d'administration
 * - Chargement conditionnel des assets (CSS/JS)
 * - Configuration des hooks AJAX
 * - Transmission des données via wp_localize_script
 *
 * @dependencies:
 * - WordPress admin_menu hook
 * - WordPress admin_enqueue_scripts hook
 * - WordPress AJAX API
 * - Constants (configuration globale)
 * - ScannerScreen (interface utilisateur)
 *
 * @related_files:
 * - UI/Screens/ScannerScreen.php (écran d'administration)
 * - Core/GutenbergValidator.php (logique métier)
 * - Assets/js/scanner-interface.js (interface JavaScript)
 * - Assets/css/scanner-interface.css (styles)
 */

namespace Company\Diagnostic\Features\Scanner;

use Company\Diagnostic\Common\Constants;
use Company\Diagnostic\Features\Scanner\UI\Screens\ScannerScreen;

/**
 * Fonctionnalité Scanner - Point d'entrée principal
 * 
 * Classe responsable de l'initialisation et de la configuration complète
 * de la fonctionnalité Scanner, incluant les menus, assets et hooks.
 */
class Feature
{
  /**
   * Initialiser la fonctionnalité Scanner
   * 
   * Configure tous les hooks nécessaires au fonctionnement du Scanner.
   * Doit être appelée lors de l'initialisation du plugin.
   * 
   * @return void
   */
  public static function init(): void
  {
    // Hooks AJAX PHP - Scanner PHP qui FONCTIONNE
    add_action('wp_ajax_run_scanner_validator', [ScannerScreen::class, 'ajax_handler']);
    add_action('wp_ajax_nopriv_run_scanner_validator', [ScannerScreen::class, 'ajax_handler']);

    // Hook AJAX pour récupérer tous les posts pour la validation JS
    add_action('wp_ajax_get_all_posts_for_validation', [self::class, 'get_all_posts_for_validation']);

    // Hook AJAX pour enregistrer les résultats du scanner JS dans BlockRecovery
    add_action('wp_ajax_save_js_validation_results', [self::class, 'save_js_validation_results']);

    // Téléchargement de sauvegardes
    add_action('wp_ajax_diagnostic_download_backup', [self::class, 'handle_backup_download']);

    // REST API endpoints
    add_action('rest_api_init', [self::class, 'register_rest_endpoints']);

    // Hooks pour l'admin
    add_action('admin_menu', [self::class, 'register_menu']);
    add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

    // Charger le script de validation JavaScript dans l'éditeur Gutenberg
    add_action('enqueue_block_editor_assets', [self::class, 'enqueue_validation_script']);
  }

  /**
   * Enregistrer les endpoints REST API
   */
  public static function register_rest_endpoints(): void
  {
    register_rest_route('diagnostic/v1', '/validate-post', [
      'methods' => 'POST',
      'callback' => [self::class, 'rest_validate_post'],
      'permission_callback' => function () {
        return current_user_can(Constants::CAP_USE_SCANNER);
      },
      'args' => [
        'post_id' => [
          'required' => true,
          'type' => 'integer',
          'validate_callback' => function ($param) {
            return is_numeric($param) && $param > 0;
          }
        ],
        'content' => [
          'required' => false,
          'type' => 'string'
        ]
      ]
    ]);
  }

  /**
   * Endpoint REST pour valider un post spécifique
   */
  public static function rest_validate_post(\WP_REST_Request $request): array
  {
    $post_id = $request->get_param('post_id');
    $content = $request->get_param('content');

    // Récupérer le post
    $post = get_post($post_id);

    if (!$post) {
      return [
        'success' => false,
        'invalid_blocks' => [],
        'error' => 'Post not found'
      ];
    }

    // Utiliser le contenu fourni ou celui du post
    if (!$content) {
      $content = $post->post_content;
    }

    // Parser les blocs
    $blocks = parse_blocks($content);

    // Analyser les blocs avec ContentAnalyzer
    $post_issues = Core\ContentAnalyzer::analyze_post_blocks($post);

    // Transformer en format compatible avec le JavaScript
    $invalid_blocks = array_map(function ($issue) {
      return [
        'isInvalid' => true,
        'reason' => $issue['type'] ?? 'unknown',
        'message' => $issue['message'] ?? '',
        'blockName' => $issue['blockName'] ?? 'unknown',
        'path' => [],
        'pathString' => ''
      ];
    }, $post_issues);

    return [
      'success' => true,
      'invalid_blocks' => $invalid_blocks,
      'total_blocks' => count($blocks),
      'post_id' => $post_id
    ];
  }

  /**
   * Récupérer tous les posts pour la validation JavaScript
   *
   * @return void
   */
  public static function get_all_posts_for_validation(): void
  {
    // Vérifier les permissions
    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    // Vérifier le nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    // Récupérer tous les posts et pages
    $args = [
      'post_type' => ['post', 'page'],
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'orderby' => 'date',
      'order' => 'DESC'
    ];

    $query = new \WP_Query($args);
    $posts = [];

    if ($query->have_posts()) {
      while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $post = get_post($post_id);

        $posts[] = [
          'id' => $post_id,
          'title' => get_the_title(),
          'modified' => $post->post_modified // Ajouter la date de modification pour le cache
        ];
      }
      wp_reset_postdata();
    }

    wp_send_json_success([
      'posts' => $posts,
      'total' => count($posts)
    ]);
  }

  /**
   * Enregistrer les résultats de la validation JavaScript pour BlockRecovery
   *
   * @return void
   */
  public static function save_js_validation_results(): void
  {
    // Vérifier les permissions
    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_send_json_error(['message' => 'Permissions insuffisantes']);
      return;
    }

    // Vérifier le nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Constants::NONCE_SCANNER)) {
      wp_send_json_error(['message' => 'Nonce invalide']);
      return;
    }

    // Récupérer les résultats envoyés par JavaScript
    $results = json_decode(stripslashes($_POST['results'] ?? '{}'), true);

    if (empty($results)) {
      wp_send_json_error(['message' => 'Aucun résultat fourni']);
      return;
    }

    // Formater les résultats au format attendu par BlockRecovery
    $formatted_results = [
      'posts' => []
    ];

    foreach ($results as $post_id => $result) {
      // Ignorer les posts sans blocs invalides
      if (empty($result['invalidBlocks']) || !is_array($result['invalidBlocks'])) {
        continue;
      }

      // Créer la structure attendue par BlockRecovery
      $issues = [];
      foreach ($result['invalidBlocks'] as $block) {
        $issues[] = [
          'type' => 'BLOCK_RECOVERY_MODE',
          'blockName' => $block['name'] ?? 'unknown',
          'message' => sprintf(
            'Bloc invalide détecté : %s',
            $block['name'] ?? 'unknown'
          ),
          'clientId' => $block['clientId'] ?? '',
          'path' => $block['path'] ?? [],
          'validationIssues' => $block['validationIssues'] ?? []
        ];
      }

      if (!empty($issues)) {
        // Récupérer les informations complètes du post pour le backup XML
        $post = get_post($post_id);

        $formatted_results['posts'][$post_id] = [
          'id' => (int)$post_id,
          'title' => get_the_title($post_id),
          'post_type' => $post->post_type ?? 'post',
          'post_status' => $post->post_status ?? 'publish',
          'post_content' => $post->post_content ?? '',
          'post_excerpt' => $post->post_excerpt ?? '',
          'post_name' => $post->post_name ?? '',
          'post_author' => $post->post_author ?? '',
          'post_date' => $post->post_date ?? '',
          'post_modified' => $post->post_modified ?? '',
          'editUrl' => admin_url("post.php?post={$post_id}&action=edit"),
          'issues' => $issues
        ];
      }
    }

    // Si aucun post avec des problèmes
    if (empty($formatted_results['posts'])) {
      wp_send_json_success([
        'message' => 'Aucun bloc invalide détecté',
        'posts_with_issues' => 0
      ]);
      return;
    }

    // Générer le backup XML des posts avec problèmes
    $backup_result = null;
    try {
      $postsWithIssues = array_values($formatted_results['posts']);

      // Créer un résumé des résultats pour le backup
      $scan_results = [
        'total_posts' => count($results),
        'posts_with_issues' => count($postsWithIssues),
        'execution_time' => '0s',
        'timestamp' => current_time('mysql')
      ];

      $backup_result = Core\XMLBackupGenerator::generateBackup($postsWithIssues, $scan_results);

      if ($backup_result['success']) {
        error_log("Scanner JS: Sauvegarde XML générée avec succès - {$backup_result['posts_count']} posts sauvegardés dans {$backup_result['filename']}");
      } else {
        error_log("Scanner JS: Erreur lors de la génération de la sauvegarde XML - {$backup_result['error']}");
      }
    } catch (\Exception $e) {
      error_log("Scanner JS: Exception lors de la génération de sauvegarde XML - " . $e->getMessage());
      $backup_result = [
        'success' => false,
        'error' => 'Exception lors de la génération de sauvegarde: ' . $e->getMessage(),
        'posts_count' => count($formatted_results['posts'])
      ];
    }

    // Déclencher l'action qui sauvegarde dans BlockRecovery
    do_action('diagnostic_scanner_complete', $formatted_results);

    $response_data = [
      'message' => 'Résultats enregistrés avec succès',
      'posts_with_issues' => count($formatted_results['posts'])
    ];

    // Ajouter les informations de backup si disponibles
    if ($backup_result !== null) {
      $response_data['backup'] = $backup_result;
    }

    wp_send_json_success($response_data);
  }

  /**
   * Enregistrer le menu Scanner dans l'admin
   */
  public static function register_menu(): void
  {
    add_submenu_page(
      'diagnostic',
      __('Scanner Blocs', Constants::TEXT_DOMAIN),
      __('Scanner Blocs', Constants::TEXT_DOMAIN),
      Constants::CAP_USE_SCANNER,
      'diagnostic_scanner_blocks',
      [ScannerScreen::class, 'render']
    );

    // Ajouter une sous-page pour la gestion des sauvegardes
    add_submenu_page(
      'diagnostic',
      __('Sauvegardes Scanner', Constants::TEXT_DOMAIN),
      __('Sauvegardes', Constants::TEXT_DOMAIN),
      Constants::CAP_USE_SCANNER,
      'diagnostic_scanner_backups',
      [ScannerScreen::class, 'render_backups']
    );
  }

  /**
   * Charger le script de validation JavaScript dans l'éditeur Gutenberg
   *
   * Ce script s'exécute uniquement si le paramètre URL 'js_validation=1' est présent
   * Il utilise l'API native de WordPress (block.isValid) pour détecter les blocs invalides
   */
  public static function enqueue_validation_script(): void
  {
    // Charger uniquement si le paramètre js_validation est présent
    if (isset($_GET['js_validation']) && $_GET['js_validation'] === '1') {
      wp_enqueue_script(
        'diagnostic-gutenberg-validation',
        DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/gutenberg-validation.js',
        [],
        Constants::VERSION . '.' . time(), // Timestamp pour forcer le rechargement
        true
      );
    }
  }

  /**
   * Charger les assets spécifiques au Scanner
   */
  public static function enqueue_assets(string $hook): void
  {
    // Charger seulement sur les pages du scanner
    if (strpos($hook, 'diagnostic_scanner_blocks') === false) {
      return;
    }

    // CSS spécifique au scanner
    wp_enqueue_style(
      'diagnostic-scanner',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/css/scanner-interface.css',
      [],
      Constants::VERSION
    );

    // JavaScript modules du scanner - ordre d'inclusion important

    // Module Core : communication AJAX et données
    wp_enqueue_script(
      'diagnostic-scanner-core',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/scanner-core.js',
      ['jquery'],
      Constants::VERSION,
      true
    );

    // Module Pagination : gestion des pages et table HTML
    wp_enqueue_script(
      'diagnostic-scanner-pagination',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/scanner-pagination.js',
      ['diagnostic-scanner-core'],
      Constants::VERSION,
      true
    );

    // Module Filters : système de filtrage avancé
    wp_enqueue_script(
      'diagnostic-scanner-filters',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/scanner-filters.js',
      ['diagnostic-scanner-core'],
      Constants::VERSION,
      true
    );

    // Module JS Validation : validation JavaScript via iframe (CHARGÉ EN PREMIER)
    wp_enqueue_script(
      'diagnostic-scanner-js-validation',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/scanner-js-validation.js',
      ['jquery'],
      Constants::VERSION . '.' . time(), // Timestamp pour forcer le rechargement
      true
    );

    // Interface principale : orchestration des modules
    wp_enqueue_script(
      'diagnostic-scanner',
      DIAGNOSTIC_PLUGIN_URL . 'src/Features/Scanner/Assets/js/scanner-interface.js',
      ['diagnostic-scanner-core', 'diagnostic-scanner-pagination', 'diagnostic-scanner-filters', 'diagnostic-scanner-js-validation'],
      Constants::VERSION . '.' . time(), // Timestamp pour forcer le rechargement
      true
    );    // Variables pour JavaScript du scanner - IMPORTANT: utiliser diagnosticScannerData
    wp_localize_script('diagnostic-scanner', 'diagnosticScannerData', [
      'nonce' => wp_create_nonce(Constants::NONCE_SCANNER),
      'ajaxurl' => admin_url('admin-ajax.php'),
      'strings' => [
        'scanInProgress' => __('Analyse en cours...', Constants::TEXT_DOMAIN),
        'scanComplete' => __('Analyse terminée', Constants::TEXT_DOMAIN),
        'scanError' => __('Erreur lors de l\'analyse', Constants::TEXT_DOMAIN),
      ]
    ]);
  }

  /**
   * Gérer le téléchargement sécurisé des sauvegardes
   */
  public static function handle_backup_download(): void
  {
    // Vérifier les permissions
    if (!current_user_can(Constants::CAP_USE_SCANNER)) {
      wp_die(__('Permissions insuffisantes.', Constants::TEXT_DOMAIN));
    }

    // Vérifier le nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'diagnostic_download_backup')) {
      wp_die(__('Nonce invalide.', Constants::TEXT_DOMAIN));
    }

    // Vérifier le fichier
    if (!isset($_GET['file'])) {
      wp_die(__('Fichier non spécifié.', Constants::TEXT_DOMAIN));
    }

    $filename = sanitize_file_name($_GET['file']);

    // Vérifier que c'est bien un fichier XML de sauvegarde
    if (!preg_match('/^scanner-backup_[a-zA-Z0-9._-]+\.xml$/', $filename)) {
      wp_die(__('Nom de fichier invalide.', Constants::TEXT_DOMAIN));
    }

    // Construire le chemin complet
    $uploadDir = wp_upload_dir();
    $filepath = $uploadDir['basedir'] . '/diagnostic-backups/' . $filename;

    // Vérifier que le fichier existe
    if (!file_exists($filepath)) {
      wp_die(__('Fichier non trouvé.', Constants::TEXT_DOMAIN));
    }

    // Forcer le téléchargement
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
      ob_end_clean();
    }

    // Lire et envoyer le fichier
    readfile($filepath);
    exit;
  }
}
