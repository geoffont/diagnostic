<?php

/**
 * Repository de validation des blocs - Gestion de persistance
 *
 * Ce fichier gère le stockage et la récupération des validations de blocs
 * dans wp_options. Il suit le pattern Repository pour abstraire la couche
 * de persistance des données de validation.
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
 * - Stockage des validations dans wp_options
 * - Vérification du statut de validation d'un post
 * - Vérification de l'éligibilité à la récupération automatique (≥2 validations)
 * - Réinitialisation complète des validations
 * - Gestion de la clé composite post_id|block_name
 *
 * @dependencies:
 * - WordPress Options API (get_option, update_option)
 *
 * @related_files:
 * - ../Feature.php (configuration et coordination)
 * - BlockRecoveryService.php (service de récupération)
 * - ../UI/Screens/BlockRecoveryScreen.php (affichage des validations)
 * 
 * @storage:
 * Option: diagnostic_validated_blocks
 * Format: ['post_id|block_name' => ['post_id', 'block_name', 'validated_at']]
 */

namespace Company\Diagnostic\Features\BlockRecovery\Core;

class ValidationRepository
{
  private const OPTION_KEY = 'diagnostic_validated_blocks';

  /**
   * Obtenir tous les blocs validés
   * Format: ['post_id|block_name' => ['post_id', 'block_name', 'validated_at']]
   */
  public function getAll(): array
  {
    return get_option(self::OPTION_KEY, []);
  }

  /**
   * Marquer un post/bloc comme validé
   */
  public function markAsValidated(int $post_id, string $block_name): bool
  {
    $validated = $this->getAll();
    $key = $this->makeKey($post_id, $block_name);

    $validated[$key] = [
      'post_id' => $post_id,
      'block_name' => $block_name,
      'validated_at' => current_time('mysql')
    ];

    return update_option(self::OPTION_KEY, $validated);
  }
  /**
   * Vérifier si un post/bloc est validé
   */
  public function isValidated(int $post_id, string $block_name): bool
  {
    $validated = $this->getAll();
    $key = $this->makeKey($post_id, $block_name);
    return isset($validated[$key]);
  }

  /**
   * Compter combien de posts différents ont été validés pour un bloc
   * Ne compte QUE les posts qui existent encore dans WordPress
   */
  public function countValidatedForBlock(string $block_name): int
  {
    $validated = $this->getAll();
    $count = 0;

    foreach ($validated as $data) {
      if (isset($data['block_name']) && $data['block_name'] === $block_name) {
        // Vérifier que le post existe encore
        if (isset($data['post_id']) && get_post_status($data['post_id']) !== false) {
          $count++;
        }
      }
    }

    return $count;
  }

  /**
   * Réinitialiser toutes les validations
   */
  public function resetAll(): bool
  {
    return delete_option(self::OPTION_KEY);
  }

  /**
   * Nettoyer les validations pour les posts qui n'existent plus
   * Retourne le nombre d'entrées supprimées
   */
  public function cleanupDeletedPosts(): int
  {
    $validated = $this->getAll();
    $cleaned = 0;

    foreach ($validated as $key => $data) {
      if (isset($data['post_id'])) {
        // Si le post n'existe plus, supprimer l'entrée
        if (get_post_status($data['post_id']) === false) {
          unset($validated[$key]);
          $cleaned++;
        }
      }
    }

    if ($cleaned > 0) {
      update_option(self::OPTION_KEY, $validated);
    }

    return $cleaned;
  }

  /**
   * Créer une clé unique pour un post/bloc
   */
  private function makeKey(int $post_id, string $block_name): string
  {
    return $post_id . '|' . $block_name;
  }

  /**
   * Vérifier si un bloc peut être récupéré automatiquement
   * (au moins 2 validations requises)
   */
  public function canAutoRecover(string $block_name): bool
  {
    return $this->countValidatedForBlock($block_name) >= 2;
  }

  /**
   * Compter le nombre total de posts validés (unique)
   * Ne compte QUE les posts qui existent encore dans WordPress
   * Utilisé pour les statistiques du dashboard
   */
  public static function countAllValidatedPosts(): int
  {
    $validated = get_option(self::OPTION_KEY, []);
    $count = 0;

    foreach ($validated as $data) {
      // Vérifier que le post existe encore
      if (isset($data['post_id']) && get_post_status($data['post_id']) !== false) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Obtenir la liste des noms de blocs qui ont au moins 2 validations
   * Ne compte QUE les posts qui existent encore dans WordPress
   * Ces blocs sont éligibles pour la récupération automatique
   */
  public static function getValidatedBlockNames(): array
  {
    $validated = get_option(self::OPTION_KEY, []);
    $counts = [];

    foreach ($validated as $data) {
      // Vérifier que le post existe encore avant de compter
      if (isset($data['block_name']) && isset($data['post_id']) && get_post_status($data['post_id']) !== false) {
        $block_name = $data['block_name'];
        if (!isset($counts[$block_name])) {
          $counts[$block_name] = 0;
        }
        $counts[$block_name]++;
      }
    }

    // Filtrer les blocs avec au moins 2 validations
    $eligible_blocks = [];
    foreach ($counts as $block_name => $count) {
      if ($count >= 2) {
        $eligible_blocks[] = $block_name;
      }
    }

    return $eligible_blocks;
  }

  /**
   * Compter le nombre de récupérations disponibles
   * = Posts non validés pour les blocs qui ont au moins 2 validations
   */
  public static function countAvailableRecoveries(array $all_issues): int
  {
    $validated = get_option(self::OPTION_KEY, []);
    $eligible_blocks = self::getValidatedBlockNames();

    if (empty($eligible_blocks)) {
      return 0;
    }

    $count = 0;

    // Parcourir tous les issues
    foreach ($all_issues as $issue) {
      $block_name = $issue['block_name'] ?? '';
      $post_id = $issue['post_id'] ?? 0;

      // Vérifier si ce bloc est éligible (≥2 validations)
      if (!in_array($block_name, $eligible_blocks)) {
        continue;
      }

      // Vérifier si ce post n'est PAS validé
      $key = $post_id . '|' . $block_name;
      if (!isset($validated[$key])) {
        $count++;
      }
    }

    return $count;
  }
}
