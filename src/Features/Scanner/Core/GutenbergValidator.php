<?php

/**
 * Validateur de blocs Gutenberg avec traitement par batch
 *
 * Analyse les blocs Gutenberg et détecte les problèmes de structure.
 *
 * @package     Company\GutenbergRecovery\Features\Scanner\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 */

namespace Company\GutenbergRecovery\Features\Scanner\Core;

use Exception;
use Error;

/**
 * Validateur de blocs WordPress en PHP
 */
class GutenbergValidator
{
  /** @var bool Protection contre les appels multiples simultanés */
  private static $analysis_running = false;

  /**
   * Analyser tous les posts WordPress
   * 
   * @param array $options Options d'analyse
   * @return array Résultats de l'analyse
   */
  public static function analyze_all_posts($options = [])
  {
    \Company\GutenbergRecovery\Features\Scanner\Core\WPLog::add('Début analyse_all_posts', '[GutenbergValidator]');
    $valid_post_types = BlockRegistry::get_valid_post_types();

    $defaults = [
      'post_types' => $valid_post_types,
      'limit' => -1,
      'verbose' => false
    ];

    $options = array_merge($defaults, $options);

    // Message informatif sur les post types qui seront analysés (seulement si pas en mode AJAX)
    $is_ajax = (function_exists('wp_doing_ajax') && wp_doing_ajax()) ||
      (defined('DOING_AJAX') && DOING_AJAX) ||
      (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($options['verbose'] && !$is_ajax) {
      echo "Post types à analyser: " . implode(', ', $options['post_types']) . "\n";
      echo "Limite: " . ($options['limit'] == -1 ? 'Aucune (tous les posts)' : $options['limit']) . "\n";
    }

    $results = [
      'totalPosts' => 0,
      'postsWithIssues' => 0,
      'totalIssues' => 0,
      'issuesByType' => [],
      'blocksByIssueType' => [],
      'posts' => [],
      'summary' => '',
      'timestamp' => current_time('mysql'),
      'post_types_analyzed' => $options['post_types']
    ];

    foreach ($options['post_types'] as $post_type) {
      \Company\GutenbergRecovery\Features\Scanner\Core\WPLog::add('Analyse du post_type: ' . $post_type, '[GutenbergValidator]');
      // Traitement par batch pour éviter les timeouts
      $batch_size = 50;
      $processed_posts = 0;
      $offset = 0;
      $start_time = time();
      $max_execution_time = 45; // 45 secondes max

      do {
        \Company\GutenbergRecovery\Features\Scanner\Core\WPLog::add('Batch offset: ' . $offset, '[GutenbergValidator]');
        // Protection contre timeout
        if (time() - $start_time > $max_execution_time) {
          break 2; // Sortir des deux boucles
        }

        $posts = get_posts([
          'post_type' => $post_type,
          'post_status' => ['publish', 'draft', 'pending', 'private'],
          'numberposts' => $batch_size,
          'offset' => $offset,
          'suppress_filters' => false,
          'no_found_rows' => true,
          'cache_results' => false,
          'update_post_meta_cache' => false,
          'meta_query' => [
            [
              'key' => '_wp_old_date',
              'compare' => 'NOT EXISTS',
            ]
          ]
        ]);

        // Si pas de posts trouvés, on continue avec le post_type suivant
        if (empty($posts)) {
          break;
        }

        foreach ($posts as $post) {
          // Protection contre timeout
          if (time() - $start_time > $max_execution_time) {
            break 3; // Sortir de toutes les boucles
          }

          $processed_posts++;
          $results['totalPosts']++;

          // Limite optionnelle par configuration
          if ($options['limit'] > 0 && $processed_posts >= $options['limit']) {
            break 2; // Sortir des deux boucles foreach
          }

          // Debug : Nettoyer le cache de ce post spécifiquement
          if (isset($options['force_refresh']) && $options['force_refresh']) {
            clean_post_cache($post->ID);
          }

          // Analyser les blocs du post
          $post_issues = ContentAnalyzer::analyze_post_blocks($post);

          // CORRECTION CRITIQUE: S'assurer que $post_issues est TOUJOURS un tableau
          if (!is_array($post_issues)) {
            $post_issues = []; // Forcer un tableau vide
          }

          // Vérifier que chaque élément du tableau est bien un tableau associatif
          $post_issues = array_filter($post_issues, function ($issue) use ($post) {
            if (!is_array($issue)) {
              return false;
            }
            return true;
          });

          // CORRECTION CRITIQUE: array_filter peut créer des objets avec des clés non-consécutives
          // Forcer la réindexation pour garantir un tableau indexé séquentiellement
          $post_issues = array_values($post_issues);

          // Mode debug : ajouter des informations détaillées
          if (isset($options['debug_mode']) && $options['debug_mode']) {
            $debug_info = [
              'post_id' => $post->ID,
              'post_title' => $post->post_title,
              'post_modified' => $post->post_modified,
              'content_length' => strlen($post->post_content),
              'content_hash' => md5($post->post_content),
              'issues_count' => count($post_issues)
            ];

            if (!isset($results['debug_posts'])) {
              $results['debug_posts'] = [];
            }
            $results['debug_posts'][] = $debug_info;
          }

          if (!empty($post_issues)) {
            $results['postsWithIssues']++;
            $results['totalIssues'] += count($post_issues);

            // Compter par type et collecter les noms de blocs
            foreach ($post_issues as $issue) {
              $type = $issue['type'];
              $blockName = $issue['blockName'] ?? 'unknown';

              $results['issuesByType'][$type] = ($results['issuesByType'][$type] ?? 0) + 1;

              // Collecter les noms de blocs uniques par type d'erreur
              if (!isset($results['blocksByIssueType'][$type])) {
                $results['blocksByIssueType'][$type] = [];
              }
              if (!in_array($blockName, $results['blocksByIssueType'][$type])) {
                $results['blocksByIssueType'][$type][] = $blockName;
              }
            }

            $results['posts'][] = [
              'id' => $post->ID,
              'title' => $post->post_title,
              'type' => $post_type,
              'editUrl' => function_exists('admin_url') ? admin_url("post.php?post={$post->ID}&action=edit") : "#post-{$post->ID}",
              'issues' => is_array($post_issues) ? $post_issues : [] // Assurer que c'est toujours un tableau
            ];
          }
        } // Fin de la boucle foreach($posts)

        // Incrémenter l'offset pour le batch suivant
        $offset += $batch_size;
      } while (!empty($posts) && ($options['limit'] <= 0 || $processed_posts < $options['limit'])); // Fin de la boucle do-while
    } // Fin de la boucle foreach($post_types)

    // Générer le résumé
    $results['summary'] = self::generate_summary($results);

    // VALIDATION FINALE: S'assurer que tous les posts ont des issues sous forme de tableau
    if (isset($results['posts']) && is_array($results['posts'])) {
      foreach ($results['posts'] as $key => $post) {
        if (isset($post['issues'])) {
          if (!is_array($post['issues'])) {
            $results['posts'][$key]['issues'] = []; // Forcer un tableau vide
          } else {
            // Vérifier si c'est un objet avec des clés numériques (problème identifié)
            $keys = array_keys($post['issues']);
            $is_sequential = array_keys($keys) === $keys && (empty($keys) || $keys === range(0, count($keys) - 1));

            if (!$is_sequential) {
              // Réindexer pour obtenir un tableau séquentiel
              $results['posts'][$key]['issues'] = array_values($post['issues']);
            }
          }
        }
      }
    }

    // NOUVELLE FONCTIONNALITÉ: Génération automatique de sauvegarde XML
    // Générer une sauvegarde XML des posts avec problèmes si des issues sont détectées
    if ($results['postsWithIssues'] > 0 && isset($results['posts']) && is_array($results['posts'])) {
      try {
        // Filtrer les posts qui ont des problèmes pour la sauvegarde
        $postsWithIssues = array_filter($results['posts'], function ($post) {
          return !empty($post['issues']) && is_array($post['issues']) && count($post['issues']) > 0;
        });

        if (!empty($postsWithIssues)) {
          // Générer la sauvegarde XML
          $backupResult = XMLBackupGenerator::generateBackup($postsWithIssues, $results);

          // Ajouter les informations de sauvegarde aux résultats
          $results['backup'] = $backupResult;

          // Log de succès ou d'erreur
          if ($backupResult['success']) {
            error_log("Scanner: Sauvegarde XML générée avec succès - {$backupResult['posts_count']} posts sauvegardés dans {$backupResult['filename']}");
          } else {
            error_log("Scanner: Erreur lors de la génération de la sauvegarde XML - {$backupResult['error']}");
          }
        }
      } catch (Exception $e) {
        // En cas d'erreur, enregistrer mais ne pas faire échouer l'analyse
        $results['backup'] = [
          'success' => false,
          'error' => 'Exception lors de la génération de sauvegarde: ' . $e->getMessage(),
          'posts_count' => $results['postsWithIssues']
        ];
        error_log("Scanner: Exception lors de la génération de sauvegarde XML - " . $e->getMessage());
      }
    } else {
      // Aucun post avec problème, pas de sauvegarde nécessaire
      $results['backup'] = [
        'success' => true,
        'message' => 'Aucune sauvegarde nécessaire - aucun post avec problème détecté',
        'posts_count' => 0
      ];
    }

    return $results;
  }

  /**
   * Générer un résumé des résultats
   * 
   * @param array $results Résultats de l'analyse
   * @return string Résumé généré
   */
  private static function generate_summary($results)
  {
    $total = $results['totalPosts'];
    $withIssues = $results['postsWithIssues'];
    $issues = $results['totalIssues'];

    $summary = "Analyse terminée: {$total} posts analysés. ";
    $summary .= "{$withIssues} posts avec problèmes ({$issues} problèmes au total).";

    if (!empty($results['issuesByType'])) {
      $summary .= " Types principaux: ";
      $types = array_slice($results['issuesByType'], 0, 3, true);
      $typesList = [];
      foreach ($types as $type => $count) {
        $typesList[] = "{$type} ({$count})";
      }
      $summary .= implode(', ', $typesList);
    }

    return $summary;
  }

  /**
   * Point d'entrée principal pour l'interface
   * 
   * @param array $config Configuration de l'analyse
   * @return array Résultats de l'analyse
   */
  public static function run_analysis($config = [])
  {
    // Protection contre les appels multiples simultanés
    if (self::$analysis_running) {
      return [
        'error' => true,
        'message' => 'Une analyse est déjà en cours'
      ];
    }

    self::$analysis_running = true;

    // Timeout de sécurité
    $max_execution_time = ini_get('max_execution_time');
    if ($max_execution_time < 60) {
      set_time_limit(60);
    }

    try {
      // Vider le cache si demandé pour avoir les données les plus récentes
      if (isset($config['force_refresh']) && $config['force_refresh']) {
        if (function_exists('wp_cache_flush')) {
          wp_cache_flush();
        }
      }

      // Validation de base pour éviter les erreurs
      if (!function_exists('get_posts')) {
        return [
          'error' => true,
          'message' => 'Fonctions WordPress non disponibles'
        ];
      }

      // Préparer les options pour analyze_all_posts qui gère la détection automatique
      $options = [];

      // Si post_types est spécifiquement défini dans config, l'utiliser
      if (isset($config['post_types'])) {
        $options['post_types'] = $config['post_types'];
      }
      // Sinon, laisser analyze_all_posts détecter automatiquement tous les post types

      if (isset($config['limit'])) {
        $options['limit'] = intval($config['limit']);
      }

      if (isset($config['verbose'])) {
        $options['verbose'] = $config['verbose'];
      }

      // Transmettre le mode debug et le force_refresh aux options
      if (isset($config['debug_mode'])) {
        $options['debug_mode'] = $config['debug_mode'];
      }
      if (isset($config['force_refresh'])) {
        $options['force_refresh'] = $config['force_refresh'];
      }

      // Utiliser la nouvelle méthode qui détecte automatiquement tous les post types
      $results = self::analyze_all_posts($options);

      if (!$results || !is_array($results)) {
        return [
          'error' => true,
          'message' => 'Résultats d\'analyse invalides'
        ];
      }

      // Le formatage HTML est maintenant géré côté JavaScript
      return $results;
    } catch (Exception $e) {
      return [
        'error' => true,
        'message' => 'Exception durant l\'analyse: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ];
    } catch (Error $e) {
      return [
        'error' => true,
        'message' => 'Erreur fatale durant l\'analyse: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ];
    } finally {
      self::$analysis_running = false;
    }
  }

  /**
   * Version simplifiée pour test rapide
   * 
   * @return array Résultats du test rapide
   */
  public static function test_quick_analysis()
  {
    try {
      if (!function_exists('get_posts')) {
        return [
          'error' => true,
          'message' => 'Fonction get_posts non disponible'
        ];
      }

      // Test avec un seul post type et limite de 5 posts
      $results = self::analyze_all_posts([
        'post_types' => ['post'],
        'limit' => 5,
        'verbose' => false
      ]);

      return $results;
    } catch (Exception $e) {
      return [
        'error' => true,
        'message' => 'Erreur durant le test: ' . $e->getMessage()
      ];
    }
  }
}
