<?php

/**
 * Block Recovery Screen - Interface utilisateur principale
 *
 * Ce fichier génère l'interface d'administration pour la récupération des blocs
 * Gutenberg en mode recovery. Il affiche le tableau de récupération avec filtres,
 * statuts de validation, et modal de progression.
 *
 * @package     Company\Diagnostic\Features\BlockRecovery\UI\Screens
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       2.0.0
 * @created     2025-10-21
 * @modified    2025-10-21
 *
 * @responsibilities:
 * - Génération de l'interface HTML du tableau de récupération
 * - Affichage des posts avec blocs en recovery
 * - Gestion des filtres par type de bloc
 * - Affichage des statuts de validation
 * - Modal de progression pour récupération batch
 * - Intégration des données du scanner
 *
 * @dependencies:
 * - Constants (configuration globale)
 * - ValidationRepository (statuts de validation)
 * - Scanner results (transient diagnostic_scanner_last_results)
 * - WordPress admin UI
 *
 * @related_files:
 * - ../../Feature.php (configuration et menus)
 * - ../../Core/BlockRecoveryService.php (logique métier)
 * - ../../Core/ValidationRepository.php (repository de validations)
 * - ../../Assets/js/block-recovery-advanced.js (interface JavaScript)
 * - ../../Assets/css/block-recovery.css (styles)
 */

namespace Company\Diagnostic\Features\BlockRecovery\UI\Screens;

use Company\Diagnostic\Common\Constants;

class BlockRecoveryScreen
{
  public function render(): void
  {
    $scanner_results = get_transient('diagnostic_scanner_last_results');
    $recovery_blocks = $this->get_recovery_blocks($scanner_results);
    $unique_blocks = $this->get_unique_block_names($recovery_blocks);

    // Utiliser ValidationRepository pour des statistiques précises
    $validationRepo = new \Company\Diagnostic\Features\BlockRecovery\Core\ValidationRepository();

    // Nettoyer automatiquement les validations pour les posts supprimés
    $validationRepo->cleanupDeletedPosts();

    // Calculer les statistiques correctes
    $total_blocks = count($recovery_blocks);
    $total_unique_blocks = count($unique_blocks);
    $total_validated = $validationRepo::countAllValidatedPosts(); // Nombre de posts individuels validés
    $available_recoveries = $validationRepo::countAvailableRecoveries($recovery_blocks); // Posts récupérables
    $blocks_ready_for_auto = $validationRepo::getValidatedBlockNames(); // Blocs avec ≥2 validations
?>
    <div class="wrap diagnostic-block-recovery">
      <!-- En-tête -->
      <div class="page-header">
        <h1>
          <span class="dashicons dashicons-admin-tools"></span>
          <?php _e('Récupération de Blocs', Constants::TEXT_DOMAIN); ?>
        </h1>
        <div class="page-header-actions">
          <button type="button" class="button button-secondary" id="refresh-data-btn" title="Afficher uniquement les blocs non validés">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Afficher non validés', Constants::TEXT_DOMAIN); ?>
          </button>
          <button type="button" class="button button-secondary" id="debug-validations-btn" title="Afficher les données de validation">
            <span class="dashicons dashicons-info"></span>
            <?php _e('Debug', Constants::TEXT_DOMAIN); ?>
          </button>
          <button type="button" class="button button-link-delete" id="reset-validations-btn" title="Réinitialiser toutes les validations">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Réinitialiser', Constants::TEXT_DOMAIN); ?>
          </button>
        </div>
      </div>

      <?php if (empty($recovery_blocks)): ?>
        <!-- État vide -->
        <div class="empty-state">
          <span class="dashicons dashicons-yes-alt"></span>
          <h2><?php _e('Aucun bloc en mode recovery détecté', Constants::TEXT_DOMAIN); ?></h2>
          <p><?php _e('Excellent ! Tous vos blocs fonctionnent correctement. Lancez un nouveau scan pour vérifier l\'état de votre site.', Constants::TEXT_DOMAIN); ?></p>
          <a href="<?php echo admin_url('admin.php?page=diagnostic'); ?>" class="button button-primary">
            <span class="dashicons dashicons-search"></span>
            <?php _e('Lancer un scan', Constants::TEXT_DOMAIN); ?>
          </a>
        </div>
      <?php else: ?>

        <!-- Cartes de statistiques -->
        <div class="diagnostic-stats">
          <div class="stat-card warning">
            <span class="dashicons dashicons-warning stat-card-icon"></span>
            <div class="stat-card-value"><?php echo $total_blocks; ?></div>
            <div class="stat-card-label"><?php _e('Blocs à récupérer', Constants::TEXT_DOMAIN); ?></div>
            <div class="stat-card-detail"><?php echo $total_unique_blocks; ?> type(s) différent(s)</div>
          </div>

          <div class="stat-card success">
            <span class="dashicons dashicons-yes-alt stat-card-icon"></span>
            <div class="stat-card-value"><?php echo $total_validated; ?></div>
            <div class="stat-card-label"><?php _e('Posts validés', Constants::TEXT_DOMAIN); ?></div>
            <div class="stat-card-detail"><?php echo $total_validated > 0 ? 'Validation individuelle' : 'Aucun validé'; ?></div>
          </div>

          <div class="stat-card info">
            <span class="dashicons dashicons-update-alt stat-card-icon"></span>
            <div class="stat-card-value"><?php echo $available_recoveries; ?></div>
            <div class="stat-card-label"><?php _e('Récupérations disponibles', Constants::TEXT_DOMAIN); ?></div>
            <div class="stat-card-detail"><?php echo count($blocks_ready_for_auto); ?> bloc(s) débloqué(s)</div>
          </div>

          <div class="stat-card">
            <span class="dashicons dashicons-performance stat-card-icon"></span>
            <div class="stat-card-value">
              <?php
              $success_rate = $total_blocks > 0 ? round(($total_validated / $total_blocks) * 100) : 0;
              echo $success_rate . '%';
              ?>
            </div>
            <div class="stat-card-label"><?php _e('Taux de succès', Constants::TEXT_DOMAIN); ?></div>
            <div class="stat-card-detail"><?php echo $total_validated . ' / ' . $total_blocks; ?> validés</div>
          </div>
        </div>

        <!-- Barre de contrôles -->
        <div class="diagnostic-controls">
          <div class="controls-row">
            <div class="control-group">
              <label for="block-filter"><?php _e('Filtrer par type de bloc:', Constants::TEXT_DOMAIN); ?></label>
              <select id="block-filter" name="block_filter">
                <option value=""><?php _e('Tous les blocs', Constants::TEXT_DOMAIN); ?></option>
                <?php foreach ($unique_blocks as $block_name => $count): ?>
                  <option value="<?php echo esc_attr($block_name); ?>">
                    <?php echo esc_html($block_name); ?> (<?php echo $count; ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="button" id="filter-blocks-btn">
                <span class="dashicons dashicons-filter"></span>
                <?php _e('Appliquer', Constants::TEXT_DOMAIN); ?>
              </button>
              <button type="button" class="button" id="reset-filter-btn">
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Réinitialiser', Constants::TEXT_DOMAIN); ?>
              </button>
            </div>

            <div class="control-group">
              <button type="button" class="button button-primary" id="mass-recovery-btn" disabled>
                <span class="dashicons dashicons-update"></span>
                <?php _e('Récupération multiple', Constants::TEXT_DOMAIN); ?>
              </button>
              <span id="mass-recovery-status" class="recovery-status-badge"></span>
            </div>
          </div>
        </div>

        <!-- Pagination du haut -->
        <div class="pagination-controls top-pagination">
          <div class="pagination-info">
            <span class="displaying-num"></span>
          </div>
          <div class="pagination-links">
            <button id="first-page" class="button" disabled>« Première</button>
            <button id="prev-page" class="button" disabled>‹ Précédente</button>
            <span class="paging-input">
              Page <input type="number" id="current-page-input" value="1" min="1" class="current-page" size="3">
              sur <span class="total-pages">1</span>
            </span>
            <button id="next-page" class="button">Suivante ›</button>
            <button id="last-page" class="button">Dernière »</button>
          </div>
          <div class="items-per-page">
            <label for="items-per-page-select">Éléments par page :</label>
            <select id="items-per-page-select">
              <option value="10">10</option>
              <option value="20" selected>20</option>
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="-1">Tous</option>
            </select>
          </div>
        </div>

        <!-- Tableau des blocs -->
        <table class="wp-list-table widefat fixed striped" id="recovery-blocks-table">
          <thead>
            <tr>
              <th class="col-block-name"><?php _e('Nom du bloc', Constants::TEXT_DOMAIN); ?></th>
              <th class="col-post"><?php _e('Post', Constants::TEXT_DOMAIN); ?></th>
              <th class="col-status"><?php _e('Statut', Constants::TEXT_DOMAIN); ?></th>
              <th class="col-actions"><?php _e('Actions', Constants::TEXT_DOMAIN); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recovery_blocks as $block): ?>
              <?php
              $is_validated = \Company\Diagnostic\Features\BlockRecovery\Feature::is_post_validated(
                $block['post_id'],
                $block['block_name']
              );
              ?>
              <tr data-post-id="<?php echo esc_attr($block['post_id']); ?>"
                data-block-name="<?php echo esc_attr($block['block_name']); ?>"
                data-is-validated="<?php echo $is_validated ? '1' : '0'; ?>"
                class="block-row">
                <td><code class="block-name"><?php echo esc_html($block['block_name']); ?></code></td>
                <td>
                  <a href="<?php echo get_edit_post_link($block['post_id']); ?>" target="_blank" class="post-link">
                    <strong><?php echo esc_html(get_the_title($block['post_id'])); ?></strong>
                  </a>
                </td>
                <td class="validation-status">
                  <?php if ($is_validated): ?>
                    <span class="status-badge validated">
                      <span class="dashicons dashicons-yes-alt"></span>
                      <?php _e('Validé', Constants::TEXT_DOMAIN); ?>
                    </span>
                  <?php else: ?>
                    <span class="status-badge not-validated">
                      <span class="dashicons dashicons-warning"></span>
                      <?php _e('Non validé', Constants::TEXT_DOMAIN); ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="button button-primary recover-block-btn" title="<?php _e('Récupérer ce bloc avec Gutenberg', Constants::TEXT_DOMAIN); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Récupérer', Constants::TEXT_DOMAIN); ?>
                  </button>
                  <?php if (!$is_validated): ?>
                    <button class="button button-secondary validate-block-btn"
                      title="<?php _e('Marquer comme validé après vérification', Constants::TEXT_DOMAIN); ?>">
                      <span class="dashicons dashicons-yes"></span>
                      <?php _e('Valider', Constants::TEXT_DOMAIN); ?>
                    </button>
                  <?php else: ?>
                    <span class="validation-success">
                      <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination du bas -->
        <div class="pagination-controls bottom-pagination">
          <div class="pagination-info">
            <span class="displaying-num"></span>
          </div>
          <div class="pagination-links">
            <button id="first-page-bottom" class="button" disabled>« Première</button>
            <button id="prev-page-bottom" class="button" disabled>‹ Précédente</button>
            <span class="paging-input">
              Page <input type="number" id="current-page-input-bottom" value="1" min="1" class="current-page" size="3">
              sur <span class="total-pages">1</span>
            </span>
            <button id="next-page-bottom" class="button">Suivante ›</button>
            <button id="last-page-bottom" class="button">Dernière »</button>
          </div>
        </div>
      <?php endif; ?>

      <div id="recovery-message"></div>

      <!-- Fenêtre modale de progression -->
      <div id="mass-recovery-modal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h2>
              <span class="dashicons dashicons-update-alt modal-icon"></span>
              <?php _e('Récupération multiple en cours', Constants::TEXT_DOMAIN); ?>
            </h2>
            <p class="modal-subtitle"><?php _e('Veuillez patienter pendant la récupération automatique des blocs', Constants::TEXT_DOMAIN); ?></p>
          </div>

          <div class="progress-container">
            <div class="progress-bar">
              <div class="progress-fill"></div>
            </div>
            <p class="progress-text">0 / 0</p>
            <div class="progress-info">
              <div class="processing-status">
                <span class="spinner"></span>
                <span class="processing-text"><?php _e('Initialisation...', Constants::TEXT_DOMAIN); ?></span>
              </div>
              <div class="time-estimate"></div>
            </div>
          </div>

          <!-- Statistiques en temps réel -->
          <div class="recovery-stats">
            <div class="stat-item stat-success">
              <span class="dashicons dashicons-yes-alt"></span>
              <div class="stat-content">
                <span class="stat-label"><?php _e('Réussis', Constants::TEXT_DOMAIN); ?></span>
                <span class="stat-value" id="stat-success">0</span>
              </div>
            </div>
            <div class="stat-item stat-failed">
              <span class="dashicons dashicons-warning"></span>
              <div class="stat-content">
                <span class="stat-label"><?php _e('Échecs', Constants::TEXT_DOMAIN); ?></span>
                <span class="stat-value" id="stat-failed">0</span>
              </div>
            </div>
            <div class="stat-item stat-remaining">
              <span class="dashicons dashicons-clock"></span>
              <div class="stat-content">
                <span class="stat-label"><?php _e('Restants', Constants::TEXT_DOMAIN); ?></span>
                <span class="stat-value" id="stat-remaining">0</span>
              </div>
            </div>
          </div>

          <div class="recovery-log"></div>

          <div class="modal-actions">
            <button type="button" class="button button-secondary" id="cancel-recovery-btn">
              <span class="dashicons dashicons-no"></span>
              <?php _e('Annuler', Constants::TEXT_DOMAIN); ?>
            </button>
            <button type="button" class="button button-primary" id="close-modal-btn" disabled style="display: none;">
              <span class="dashicons dashicons-yes-alt"></span>
              <?php _e('Terminé', Constants::TEXT_DOMAIN); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
<?php
  }

  private function get_recovery_blocks($scanner_results): array
  {
    if (empty($scanner_results['posts'])) {
      return [];
    }

    $recovery_blocks = [];
    $seen = []; // Pour dédupliquer : un post ne doit apparaître qu'une seule fois par type de bloc

    foreach ($scanner_results['posts'] as $post) {
      if (empty($post['issues'])) {
        continue;
      }

      $post_id = $post['id'];

      // IMPORTANT: Vérifier si le post existe toujours dans la base de données
      $post_object = get_post($post_id);
      if (!$post_object || $post_object->post_status === 'trash') {
        continue; // Ignorer les posts supprimés ou dans la corbeille
      }

      foreach ($post['issues'] as $issue) {
        if ($issue['type'] === 'BLOCK_RECOVERY_MODE') {
          $block_name = $issue['blockName'] ?? '';

          // Créer une clé unique : post_id + block_name
          $key = $post_id . '|' . $block_name;

          // Ne garder qu'une seule occurrence par combinaison post/bloc
          if (!isset($seen[$key])) {
            $recovery_blocks[] = [
              'post_id' => $post_id,
              'block_name' => $block_name
            ];
            $seen[$key] = true;
          }
        }
      }
    }

    return $recovery_blocks;
  }

  /**
   * Obtenir la liste des noms de blocs uniques avec leur nombre d'occurrences
   */
  private function get_unique_block_names(array $recovery_blocks): array
  {
    $unique = [];
    foreach ($recovery_blocks as $block) {
      $name = $block['block_name'];
      if (!isset($unique[$name])) {
        $unique[$name] = 0;
      }
      $unique[$name]++;
    }
    return $unique;
  }
}
