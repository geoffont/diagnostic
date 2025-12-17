<?php

/**
 * Générateur de contenu pour posts de test WordPress
 *
 * Ce fichier contient la logique métier principale pour générer des posts
 * de test avec du contenu Gutenberg varié. Il produit des articles et pages
 * avec différents types de blocs, médias et structures pour tester
 * les fonctionnalités du site et du plugin de diagnostic.
 *
 * @package     Company\Diagnostic\Features\PostGenerator\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Génération de posts et pages de test
 * - Création de contenu Gutenberg varié
 * - Gestion des médias et images
 * - Configuration des métadonnées
 * - Insertion en base de données WordPress
 * - Génération de titres et contenus aléatoires
 *
 * @dependencies:
 * - WordPress wp_insert_post() function
 * - WordPress Gutenberg blocks API
 * - BlockGenerator (génération de blocs)
 * - PHP random functions
 *
 * @related_files:
 * - BlockGenerator.php (création de blocs)
 * - PostGeneratorScreen.php (interface)
 * - Feature.php (intégration)
 *
 * @output:
 * - Posts WordPress avec contenu de test
 * - Blocs Gutenberg variés
 * - Métadonnées et taxonomies
 */

namespace Company\Diagnostic\Features\PostGenerator\Core;

use Company\Diagnostic\Common\Constants;

/**
 * Générateur de contenu pour posts de test
 * 
 * Classe responsable de la création programmatique de posts
 * et pages WordPress avec contenu de test varié et réaliste.
 */
class PostContentGenerator
{
  /**
   * Types de posts supportés pour la génération
   * 
   * @var array<string>
   */
  private const POST_TYPES = [
    'post',
    'page'
  ];

  /**
   * Catégories de test
   */
  private const TEST_CATEGORIES = [
    'Technologie',
    'Design',
    'Développement',
    'WordPress',
    'Gutenberg',
    'Test',
    'Performance',
    'SEO',
    'Accessibilité',
    'Sécurité'
  ];

  /**
   * Tags de test
   */
  private const TEST_TAGS = [
    'test',
    'demo',
    'exemple',
    'gutenberg',
    'blocks',
    'wordpress',
    'cms',
    'web',
    'frontend',
    'backend',
    'php',
    'javascript',
    'css',
    'html',
    'responsive',
    'mobile',
    'performance',
    'optimization'
  ];

  /**
   * Générer plusieurs posts de test
   * 
   * @param int $count Nombre de posts à générer
   * @param array $options Options de génération
   * @return array Résultat de la génération
   */
  public static function generate_test_posts(int $count = 10, array $options = []): array
  {
    $defaults = [
      'post_type' => 'post',
      'post_status' => 'publish',
      'blocks_per_post' => [5, 20], // Min et max blocs par post
      'include_problematic_blocks' => true,
      'add_categories' => true,
      'add_tags' => true,
      'add_featured_image' => false,
      'post_date_range' => 30, // Jours dans le passé
    ];

    $options = wp_parse_args($options, $defaults);

    $results = [
      'success' => 0,
      'errors' => 0,
      'post_ids' => [],
      'messages' => []
    ];

    // Préparer les catégories et tags si nécessaire
    if ($options['add_categories']) {
      self::ensure_test_categories();
    }

    if ($options['add_tags']) {
      self::ensure_test_tags();
    }

    for ($i = 1; $i <= $count; $i++) {
      try {
        $post_id = self::generate_single_post($i, $options);

        if ($post_id && !is_wp_error($post_id)) {
          $results['success']++;
          $results['post_ids'][] = $post_id;
          $results['messages'][] = "Post #{$i} créé avec l'ID {$post_id}";
        } else {
          $results['errors']++;
          $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : 'Erreur inconnue';
          $results['messages'][] = "Erreur lors de la création du post #{$i}: {$error_message}";
        }
      } catch (\Exception $e) {
        $results['errors']++;
        $results['messages'][] = "Exception lors de la création du post #{$i}: " . $e->getMessage();
      }
    }

    return $results;
  }

  /**
   * Générer un seul post de test
   * 
   * @param int $index Index du post
   * @param array $options Options de génération
   * @return int|WP_Error ID du post créé ou erreur
   */
  private static function generate_single_post(int $index, array $options)
  {
    // Générer le titre
    $title = self::generate_post_title($index);

    // Générer le contenu avec blocs
    $blocks_count = rand($options['blocks_per_post'][0], $options['blocks_per_post'][1]);
    $content = BlockGenerator::generate_random_blocks(
      $blocks_count,
      $options['include_problematic_blocks']
    );

    // Générer la date aléatoire
    $post_date = self::generate_random_date($options['post_date_range']);

    // Préparer les données du post
    $post_data = [
      'post_title' => $title,
      'post_content' => $content,
      'post_status' => $options['post_status'],
      'post_type' => $options['post_type'],
      'post_author' => get_current_user_id(),
      'post_date' => $post_date,
      'meta_input' => [
        '_diagnostic_test_post' => true,
        '_diagnostic_blocks_count' => $blocks_count,
        '_diagnostic_generated_at' => current_time('mysql')
      ]
    ];

    // Insérer le post
    $post_id = wp_insert_post($post_data);

    if ($post_id && !is_wp_error($post_id)) {
      // Ajouter les catégories si demandé
      if ($options['add_categories'] && $options['post_type'] === 'post') {
        self::assign_random_categories($post_id);
      }

      // Ajouter les tags si demandé
      if ($options['add_tags'] && $options['post_type'] === 'post') {
        self::assign_random_tags($post_id);
      }

      // Ajouter une image mise en avant si demandé
      if ($options['add_featured_image']) {
        self::assign_featured_image($post_id);
      }
    }

    return $post_id;
  }

  /**
   * Générer un titre de post
   * 
   * @param int $index Index du post
   * @return string Titre généré
   */
  private static function generate_post_title(int $index): string
  {
    $title_patterns = [
      "Article de test #%d : %s",
      "Démonstration Gutenberg #%d : %s",
      "Test de performance #%d : %s",
      "Exemple de contenu #%d : %s",
      "Post généré automatiquement #%d : %s",
      "Validation des blocs #%d : %s"
    ];

    $topics = [
      "Optimisation des performances",
      "Nouveaux blocs Gutenberg",
      "Architecture WordPress",
      "Développement front-end",
      "Intégration continue",
      "Tests automatisés",
      "Responsive design",
      "Accessibilité web",
      "SEO avancé",
      "Sécurité WordPress",
      "API REST WordPress",
      "Personnalisation des thèmes",
      "Développement de plugins",
      "Migration de contenu"
    ];

    $pattern = $title_patterns[array_rand($title_patterns)];
    $topic = $topics[array_rand($topics)];

    return sprintf($pattern, $index, $topic);
  }

  /**
   * Générer une date aléatoire dans le passé
   * 
   * @param int $days_back Nombre de jours dans le passé
   * @return string Date au format MySQL
   */
  private static function generate_random_date(int $days_back): string
  {
    $timestamp = time() - rand(0, $days_back * 24 * 60 * 60);
    return date('Y-m-d H:i:s', $timestamp);
  }

  /**
   * S'assurer que les catégories de test existent
   */
  private static function ensure_test_categories(): void
  {
    foreach (self::TEST_CATEGORIES as $category_name) {
      if (!term_exists($category_name, 'category')) {
        wp_insert_term($category_name, 'category', [
          'description' => "Catégorie de test pour le diagnostic : {$category_name}",
          'slug' => sanitize_title($category_name . '-test')
        ]);
      }
    }
  }

  /**
   * S'assurer que les tags de test existent
   */
  private static function ensure_test_tags(): void
  {
    foreach (self::TEST_TAGS as $tag_name) {
      if (!term_exists($tag_name, 'post_tag')) {
        wp_insert_term($tag_name, 'post_tag', [
          'description' => "Tag de test pour le diagnostic",
          'slug' => sanitize_title($tag_name)
        ]);
      }
    }
  }

  /**
   * Assigner des catégories aléatoires à un post
   * 
   * @param int $post_id ID du post
   */
  private static function assign_random_categories(int $post_id): void
  {
    $categories = get_terms([
      'taxonomy' => 'category',
      'hide_empty' => false,
      'name' => self::TEST_CATEGORIES
    ]);

    if (!empty($categories) && !is_wp_error($categories)) {
      // Sélectionner 1 à 3 catégories aléatoires
      $selected_count = rand(1, min(3, count($categories)));
      $selected_categories = array_rand($categories, $selected_count);

      if (!is_array($selected_categories)) {
        $selected_categories = [$selected_categories];
      }

      $category_ids = [];
      foreach ($selected_categories as $index) {
        $category_ids[] = $categories[$index]->term_id;
      }

      wp_set_post_categories($post_id, $category_ids);
    }
  }

  /**
   * Assigner des tags aléatoires à un post
   * 
   * @param int $post_id ID du post
   */
  private static function assign_random_tags(int $post_id): void
  {
    // Sélectionner 2 à 6 tags aléatoires
    $selected_count = rand(2, min(6, count(self::TEST_TAGS)));
    $selected_tags = array_rand(array_flip(self::TEST_TAGS), $selected_count);

    if (!is_array($selected_tags)) {
      $selected_tags = [$selected_tags];
    }

    wp_set_post_tags($post_id, $selected_tags);
  }

  /**
   * Assigner une image mise en avant (placeholder)
   * 
   * @param int $post_id ID du post
   */
  private static function assign_featured_image(int $post_id): void
  {
    // Pour l'instant, on utilise juste un placeholder
    // Dans une version future, on pourrait télécharger une vraie image
    $image_id = rand(1, 100);
    update_post_meta($post_id, '_thumbnail_id', $image_id);
  }

  /**
   * Supprimer tous les posts de test générés
   * 
   * @return array Résultat de la suppression
   */
  public static function cleanup_test_posts(): array
  {
    $posts = get_posts([
      'post_type' => ['post', 'page'],
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_key' => '_diagnostic_test_post',
      'meta_value' => true
    ]);

    $results = [
      'deleted' => 0,
      'errors' => 0,
      'messages' => []
    ];

    foreach ($posts as $post) {
      $deleted = wp_delete_post($post->ID, true);

      if ($deleted) {
        $results['deleted']++;
        $results['messages'][] = "Post supprimé : {$post->post_title} (ID: {$post->ID})";
      } else {
        $results['errors']++;
        $results['messages'][] = "Erreur lors de la suppression du post ID: {$post->ID}";
      }
    }

    return $results;
  }

  /**
   * Obtenir les statistiques des posts de test
   * 
   * @return array Statistiques
   */
  public static function get_test_posts_stats(): array
  {
    $posts = get_posts([
      'post_type' => ['post', 'page'],
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_key' => '_diagnostic_test_post',
      'meta_value' => true
    ]);

    $stats = [
      'total_posts' => count($posts),
      'by_status' => [],
      'by_type' => [],
      'total_blocks' => 0,
      'avg_blocks_per_post' => 0
    ];

    $total_blocks = 0;

    foreach ($posts as $post) {
      // Compter par statut
      if (!isset($stats['by_status'][$post->post_status])) {
        $stats['by_status'][$post->post_status] = 0;
      }
      $stats['by_status'][$post->post_status]++;

      // Compter par type
      if (!isset($stats['by_type'][$post->post_type])) {
        $stats['by_type'][$post->post_type] = 0;
      }
      $stats['by_type'][$post->post_type]++;

      // Compter les blocs
      $blocks_count = get_post_meta($post->ID, '_diagnostic_blocks_count', true);
      if ($blocks_count) {
        $total_blocks += (int) $blocks_count;
      }
    }

    $stats['total_blocks'] = $total_blocks;
    $stats['avg_blocks_per_post'] = $stats['total_posts'] > 0 ? round($total_blocks / $stats['total_posts'], 2) : 0;

    return $stats;
  }
}
