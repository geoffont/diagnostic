<?php

/**
 * Service de récupération de blocs - Logique métier
 *
 * Ce fichier contient la logique métier pour la récupération des blocs
 * Gutenberg en mode recovery. Il coordonne la validation et la liste
 * des posts à récupérer.
 *
 * @package     Company\Diagnostic\Features\BlockRecovery\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       2.0.0
 * @created     2025-10-21
 * @modified    2025-10-21
 *
 * @responsibilities:
 * - Marquer les posts comme validés après récupération
 * - Récupérer la liste des posts à traiter pour un bloc donné
 * - Coordination avec ValidationRepository
 * - Logique métier pure sans dépendances UI
 *
 * @dependencies:
 * - ValidationRepository (gestion des validations)
 * - Scanner results (transient diagnostic_scanner_last_results)
 *
 * @related_files:
 * - ../Feature.php (configuration et coordination)
 * - ValidationRepository.php (repository de validations)
 * - ../UI/Screens/BlockRecoveryScreen.php (interface utilisateur)
 * 
 * @note:
 * La récupération réelle des blocs se fait dans l'éditeur Gutenberg
 * via gutenberg-recovery.js. Ce service gère uniquement la validation
 * et la liste des posts à traiter.
 */

namespace Company\Diagnostic\Features\BlockRecovery\Core;

class BlockRecoveryService
{
  /**
   * Récupérer la liste des posts à traiter pour un bloc
   */
  public function getPostsToRecover(string $block_name): array
  {
    $scanner_results = get_transient('diagnostic_scanner_last_results');
    if (empty($scanner_results['posts'])) {
      return [];
    }

    $posts_to_recover = [];
    foreach ($scanner_results['posts'] as $post) {
      if (empty($post['issues'])) {
        continue;
      }

      $post_id = $post['id'] ?? 0;

      // Vérifier que le post existe toujours et n'est pas dans la corbeille
      $post_object = get_post($post_id);
      if (!$post_object || $post_object->post_status === 'trash') {
        continue;
      }

      foreach ($post['issues'] as $issue) {
        if (
          $issue['type'] === 'BLOCK_RECOVERY_MODE' &&
          ($issue['blockName'] ?? '') === $block_name
        ) {
          $posts_to_recover[] = [
            'post_id' => $post_id,
            'post_title' => get_the_title($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw')
          ];
          break;
        }
      }
    }

    return $posts_to_recover;
  }
}
