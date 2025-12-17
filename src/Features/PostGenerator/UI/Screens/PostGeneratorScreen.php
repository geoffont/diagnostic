<?php

/**
 * Interface d'administration pour le générateur de posts de test
 *
 * Ce fichier contient l'interface utilisateur complète du générateur de posts,
 * incluant les formulaires de configuration, la génération via AJAX et
 * l'affichage des résultats. Il gère aussi les hooks d'administration et
 * la validation des données utilisateur.
 *
 * @package     Company\Diagnostic\Features\PostGenerator\UI\Screens
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Interface d'administration du PostGenerator
 * - Formulaires de configuration avancée
 * - Gestion des requêtes AJAX de génération
 * - Validation et sécurisation des données
 * - Affichage des résultats et feedback
 * - Intégration avec l'API WordPress
 *
 * @dependencies:
 * - WordPress admin hooks et functions
 * - PostContentGenerator (logique métier)
 * - Constants (configuration)
 * - WordPress nonces et sécurité
 *
 * @related_files:
 * - ../Core/PostContentGenerator.php (génération)
 * - Feature.php (intégration et assets)
 * - ../Assets/js/post-generator.js (interface JS)
 * - ../Assets/css/post-generator.css (styles)
 *
 * @ajax_actions:
 * - diagnostic_post_generator (génération de posts)
 */

namespace Company\Diagnostic\Features\PostGenerator\UI\Screens;

use Company\Diagnostic\Common\Constants;
use Company\Diagnostic\Features\PostGenerator\Core\PostContentGenerator;

/**
 * Interface d'administration pour la génération de posts
 * 
 * Classe responsable de l'affichage et de la gestion de l'interface
 * de génération de posts de test dans l'administration WordPress.
 */
class PostGeneratorScreen
{
  /**
   * Afficher la page principale de génération de posts
   * 
   * Génère l'interface complète incluant formulaires, options
   * et zones de résultats pour la génération de posts de test.
   * 
   * @return void
   */
  public static function render(): void
  {
    // Vérifier les permissions
    if (!current_user_can(Constants::CAP_USE_POST_GENERATOR)) {
      wp_die(__('Vous n\'avez pas les permissions nécessaires pour accéder à cette page.'));
    }

    // Afficher les résultats s'il y en a
    self::display_results();

    // Obtenir les statistiques actuelles
    $stats = PostContentGenerator::get_test_posts_stats(); ?>
    <div class="wrap">
      <h1>Générateur de contenu</h1>

      <div class="diagnostic-post-generator">
        <!-- Statistiques actuelles -->
        <div class="card">
          <h2>Statistiques actuelles</h2>
          <p><strong>Posts de test générés :</strong> <?php echo esc_html($stats['total_posts']); ?></p>
          <p><strong>Blocs au total :</strong> <?php echo esc_html($stats['total_blocks']); ?></p>
          <p><strong>Blocs par post (moyenne) :</strong> <?php echo esc_html($stats['avg_blocks_per_post']); ?></p>
        </div>

        <!-- Formulaire de génération -->
        <div class="card">
          <h2>Générer des posts de test</h2>
          <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="diagnostic_post_generator">
            <?php wp_nonce_field(Constants::NONCE_POST_GENERATOR, '_diagnostic_nonce'); ?>
            <input type="hidden" name="generator_action" value="generate">

            <table class="form-table">
              <tr>
                <th scope="row"><label for="post_count">Nombre de posts</label></th>
                <td><input type="number" id="post_count" name="post_count" value="10" min="1" max="100" class="small-text" required></td>
              </tr>
              <tr>
                <th scope="row"><label for="post_type">Type de contenu</label></th>
                <td>
                  <select id="post_type" name="post_type">
                    <option value="post">Articles (Posts)</option>
                    <option value="page">Pages</option>
                  </select>
                </td>
              </tr>
            </table>

            <p class="submit">
              <input type="submit" name="submit" value="Générer les posts de test" class="button button-primary">
            </p>
          </form>
        </div>

        <!-- Actions de maintenance -->
        <?php if ($stats['total_posts'] > 0): ?>
          <div class="card">
            <h2>Maintenance</h2>
            <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="diagnostic_post_generator">
              <?php wp_nonce_field(Constants::NONCE_POST_GENERATOR, '_diagnostic_nonce'); ?>
              <input type="hidden" name="generator_action" value="cleanup">

              <p><strong>Attention :</strong> Cette action supprimera tous les posts de test.</p>
              <p class="submit">
                <input type="submit" name="submit" value="Supprimer tous les posts de test" class="button button-secondary">
              </p>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
<?php
  }

  /**
   * Traiter les actions POST du formulaire
   */
  private static function handle_post_actions()
  {
    if (!wp_verify_nonce($_POST['_diagnostic_nonce'], Constants::NONCE_POST_GENERATOR)) {
      echo '<div class="notice notice-error is-dismissible"><p>Erreur de sécurité. Veuillez réessayer.</p></div>';
      return;
    }

    $action = sanitize_text_field($_POST['action']);

    switch ($action) {
      case 'generate':
        $post_count = intval($_POST['post_count']);
        $post_type = sanitize_text_field($_POST['post_type']);
        self::handle_generate_posts($post_count, $post_type);
        break;

      case 'cleanup':
        self::handle_cleanup_posts();
        break;

      default:
        echo '<div class="notice notice-error is-dismissible"><p>Action non reconnue.</p></div>';
        break;
    }
  }

  /**
   * Traiter la génération de posts
   */
  private static function handle_generate_posts(int $count, string $post_type): void
  {
    $start_time = microtime(true);
    $results = PostContentGenerator::generate_test_posts($count, ['post_type' => $post_type]);
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);

    if ($results['success'] > 0) {
      $message = sprintf(
        '%d %s générés avec succès en %s secondes.',
        $results['success'],
        $post_type === 'post' ? 'articles' : 'pages',
        $duration
      );

      if ($results['errors'] > 0) {
        $message .= sprintf(' %d erreurs rencontrées.', $results['errors']);
      }

      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    } else {
      echo '<div class="notice notice-error is-dismissible"><p>Aucun post n\'a pu être généré.</p></div>';
    }
  }

  /**
   * Traiter la suppression des posts
   */
  private static function handle_cleanup_posts(): void
  {
    $start_time = microtime(true);
    $results = PostContentGenerator::cleanup_test_posts();
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);

    if ($results['deleted'] > 0) {
      $message = sprintf(
        '%d posts supprimés avec succès en %s secondes.',
        $results['deleted'],
        $duration
      );

      if ($results['errors'] > 0) {
        $message .= sprintf(' %d erreurs rencontrées.', $results['errors']);
      }

      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    } else {
      echo '<div class="notice notice-error is-dismissible"><p>Aucun post n\'a été supprimé.</p></div>';
    }
  }

  /**
   * Traiter les actions POST via admin_post hook
   */
  public static function handle_admin_post(): void
  {
    // Vérifier les permissions
    if (!current_user_can(Constants::CAP_USE_POST_GENERATOR)) {
      wp_die(__('Vous n\'avez pas les permissions nécessaires.'));
    }

    // Vérifier le nonce
    if (!wp_verify_nonce($_POST['_diagnostic_nonce'], Constants::NONCE_POST_GENERATOR)) {
      wp_die(__('Erreur de sécurité. Veuillez réessayer.'));
    }

    $action = sanitize_text_field($_POST['generator_action']);
    $redirect_url = admin_url('admin.php?page=diagnostic-post-generator');

    switch ($action) {
      case 'generate':
        $post_count = intval($_POST['post_count']);
        $post_type = sanitize_text_field($_POST['post_type']);

        // Stocker les résultats dans une option temporaire pour l'affichage
        $results = PostContentGenerator::generate_test_posts($post_count, ['post_type' => $post_type]);
        set_transient('diagnostic_post_generator_result', [
          'type' => 'generate',
          'results' => $results,
          'post_count' => $post_count,
          'post_type' => $post_type
        ], 300); // 5 minutes

        break;

      case 'cleanup':
        $results = PostContentGenerator::cleanup_test_posts();
        set_transient('diagnostic_post_generator_result', [
          'type' => 'cleanup',
          'results' => $results
        ], 300);

        break;
    }

    // Rediriger vers la page
    wp_redirect($redirect_url);
    exit;
  }

  /**
   * Afficher les résultats des actions
   */
  private static function display_results(): void
  {
    $result = get_transient('diagnostic_post_generator_result');
    if (!$result) {
      return;
    }

    // Supprimer le transient après lecture
    delete_transient('diagnostic_post_generator_result');

    if ($result['type'] === 'generate') {
      self::display_generation_results($result['results'], $result['post_count'], $result['post_type']);
    } elseif ($result['type'] === 'cleanup') {
      self::display_cleanup_results($result['results']);
    }
  }

  /**
   * Afficher les résultats de génération
   */
  private static function display_generation_results(array $results, int $post_count, string $post_type): void
  {
    if ($results['success'] > 0) {
      // Calculer le nombre total de blocs générés (estimation)
      $total_blocks = $results['success'] * 12; // Moyenne approximative de blocs par post

      $message = sprintf(
        '%d %s générés avec succès (environ %d blocs au total).',
        $results['success'],
        $post_type === 'post' ? 'articles' : 'pages',
        $total_blocks
      );

      if ($results['errors'] > 0) {
        $message .= sprintf(' %d erreurs rencontrées.', $results['errors']);
      }

      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    } else {
      echo '<div class="notice notice-error is-dismissible"><p>Aucun post n\'a pu être généré.</p></div>';
    }
  }

  /**
   * Afficher les résultats de nettoyage
   */
  private static function display_cleanup_results(array $results): void
  {
    if ($results['deleted'] > 0) {
      $message = sprintf('%d posts supprimés avec succès.', $results['deleted']);

      if ($results['errors'] > 0) {
        $message .= sprintf(' %d erreurs rencontrées.', $results['errors']);
      }

      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    } else {
      echo '<div class="notice notice-error is-dismissible"><p>Aucun post n\'a été supprimé.</p></div>';
    }
  }

  /**
   * Handler AJAX pour l'interface JavaScript (non utilisé actuellement)
   */
  public static function ajax_handler(): void
  {
    // Cette méthode pourra être implémentée plus tard pour AJAX
    wp_die();
  }
}
