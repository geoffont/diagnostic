<?php

/**
 * Analyseur de contenu pour le diagnostic de posts
 *
 * Analyse les blocs Gutenberg et détecte les blocs en mode recovery.
 *
 * @package     Company\Diagnostic\Features\Scanner\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 */

namespace Company\Diagnostic\Features\Scanner\Core;

use Exception;

/**
 * Analyseur de contenu avancé
 */
class ContentAnalyzer
{
  /**
   * Analyser les blocs d'un post - FONCTION PRINCIPALE
   * 
   * @param \WP_Post $post Le post à analyser
   * @return array Liste des problèmes détectés
   */
  public static function analyze_post_blocks($post)
  {
    // Log INFO supprimé (non critique en production)
    $content = $post->post_content;
    $blocks = parse_blocks($content);
    $issues = [];

    // Vérifier les validations existantes pour ce post
    $validationRepo = new \Company\Diagnostic\Features\BlockRecovery\Core\ValidationRepository();
    
    // 1. Validation des blocs parsés
    $issues = array_merge($issues, self::validate_blocks_recursive($blocks, $post, [], $validationRepo));

    // 2. Analyse du contenu brut pour détecter des problèmes que parse_blocks pourrait manquer
    $raw_issues = self::analyze_raw_content($content, $post, $validationRepo);
    $issues = array_merge($issues, $raw_issues);

    // 3. Filtrer les types d'erreurs à exclure
    $excluded_types = ['SERIALIZATION_ERROR', 'INVALID_BLOCK', 'ORPHANED_CONTENT'];
    $issues = array_filter($issues, function ($issue) use ($excluded_types) {
      return !in_array($issue['type'], $excluded_types);
    });

    return array_values($issues);
  }

  /**
   * Validation récursive des blocs
   * 
   * @param array $blocks Liste des blocs à valider
   * @param \WP_Post $post Le post analysé
   * @param array $path Chemin actuel dans l'arborescence des blocs
   * @return array Liste des problèmes détectés
   */
  private static function validate_blocks_recursive($blocks, $post, $path = [], $validationRepo = null)
  {
    // Log INFO supprimé (non critique en production)
    $issues = [];

    foreach ($blocks as $index => $block) {
      $current_path = array_merge($path, [$index]);

      // Valider TOUS les blocs, même ceux sans nom
      $block_issues = self::validate_single_block($block, $post, $current_path, $validationRepo);
      $issues = array_merge($issues, $block_issues);

      // Traiter les blocs imbriqués
      if (!empty($block['innerBlocks'])) {
        $inner_issues = self::validate_blocks_recursive($block['innerBlocks'], $post, $current_path, $validationRepo);
        $issues = array_merge($issues, $inner_issues);
      }
    }

    return $issues;
  }

  /**
   * Valider un seul bloc et retourner les problèmes détectés
   * 
   * @param array $block Le bloc à valider
   * @param \WP_Post $post Le post analysé
   * @param array $path Chemin du bloc dans l'arborescence
   * @return array Liste des problèmes détectés
   */
  private static function validate_single_block($block, $post, $path = [], $validationRepo = null)
  {
    $issues = [];
    $blockName = $block['blockName'] ?? '';

    // Vérifier si le bloc est déjà validé
    if ($validationRepo && $validationRepo->isValidated($post->ID, $blockName)) {
      // Log INFO supprimé (non critique en production)
      return $issues; // Ne pas signaler d'issues pour les blocs validés
    }

    // Bloc create-block spécifique - détecter les blocs non enregistrés
    if (BlockRegistry::is_create_block($blockName)) {
      if (!BlockRegistry::is_block_registered($blockName)) {
        $issues[] = [
          'type' => 'CREATE_BLOCK_UNREGISTERED',
          'severity' => 'high',
          'message' => "Bloc create-block non enregistré: {$blockName}",
          'blockName' => $blockName,
          'path' => $path,
          'suggestion' => 'Vérifier l\'activation du plugin de bloc personnalisé'
        ];
      }
    }

    return $issues;
  }

  /**
   * Analyser le contenu brut pour détecter des problèmes de parsing
   * 
   * @param string $content Le contenu brut du post
   * @param \WP_Post $post Le post analysé
   * @return array Liste des problèmes détectés
   */
  public static function analyze_raw_content($content, $post, $validationRepo = null)
  {
    $issues = [];

    // Détecter TOUS les blocs create-block avec analyse fine du statut
    // Pattern modifié pour capturer les blocs même s'ils sont imbriqués
    // On cherche simplement <!-- wp:create-block/xxx --> sans exiger le closing tag immédiat
    $regex_result = preg_match_all('/<!-- wp:create-block\/([a-z-]+)(?:\s+({[^}]*}))?\s*-->/s', $content, $matches, PREG_SET_ORDER);

    if ($regex_result > 0) {
      // Utiliser un Set pour éviter les doublons
      $processed_blocks = [];

      foreach ($matches as $match) {
        $block_name = 'create-block/' . $match[1];

        // Vérifier si le bloc est déjà validé
        if ($validationRepo && $validationRepo->isValidated($post->ID, $block_name)) {
          // Log INFO supprimé (non critique en production)
          continue; // Passer au bloc suivant
        }

        // Éviter de traiter plusieurs fois le même type de bloc
        if (in_array($block_name, $processed_blocks)) {
          continue;
        }
        $processed_blocks[] = $block_name;

        $issue = self::analyze_block_status($post, $block_name, '');
        if ($issue) {
          $issues[] = $issue;
        }
      }
    }
    return $issues;
  }

  /**
   * Analyser le statut d'un bloc avec configuration JSON externe
   * 
   * @param \WP_Post $post Le post analysé
   * @param string $block_name Le nom du bloc
   * @param string $block_content Le contenu du bloc
   * @return array|null Issue détectée ou null si bloc valide
   */
  private static function analyze_block_status($post, $block_name, $block_content): ?array
  {
    // Vérifier si le bloc est enregistré
    $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
    $is_registered = isset($registered_blocks[$block_name]);

    // Utiliser l'API WordPress pour analyser les blocs comme l'éditeur le fait
    $api_status = self::analyze_block_via_api($post, $block_name);

    // Charger les règles depuis le fichier JSON
    $rules = self::load_analysis_rules();

    // Évaluer chaque règle par ordre de priorité
    foreach ($rules as $rule) {
      if (self::evaluate_json_conditions($rule['conditions'], $api_status, $is_registered)) {
        return self::build_issue_from_rule($rule['result'], $block_name);
      }
    }

    return null; // Bloc valide
  }

  /**
   * Charger les règles d'analyse depuis le fichier JSON
   * 
   * @return array Liste des règles triées par priorité
   */
  private static function load_analysis_rules(): array
  {
    static $rules_cache = null;

    if ($rules_cache === null) {
      $json_file = plugin_dir_path(__FILE__) . '../config/block-analysis-rules.json';
      if (file_exists($json_file)) {
        $json_content = file_get_contents($json_file);
        $rules_data = json_decode($json_content, true);

        if ($rules_data && isset($rules_data['rules'])) {
          // Trier par priorité
          $rules_cache = $rules_data['rules'];
          usort($rules_cache, function ($a, $b) {
            return ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999);
          });
        }
      }

      // Fallback vers les règles par défaut si JSON absent
      if ($rules_cache === null) {
        $rules_cache = self::get_default_rules();
      }
    }

    return $rules_cache;
  }

  /**
   * Évaluer les conditions JSON pour une règle
   * 
   * @param array $conditions Les conditions à vérifier
   * @param string $api_status Le statut API du bloc
   * @param bool $is_registered Si le bloc est enregistré
   * @return bool True si toutes les conditions sont remplies
   */
  private static function evaluate_json_conditions($conditions, $api_status, $is_registered): bool
  {
    foreach ($conditions as $key => $expected) {
      switch ($key) {
        case 'api_status':
          if (!in_array($api_status, (array)$expected)) {
            return false;
          }
          break;
        case 'is_registered':
          if ($is_registered !== $expected) {
            return false;
          }
          break;
        // Possibilité d'ajouter d'autres conditions facilement
        default:
          // Condition inconnue, ignorer pour compatibilité
          break;
      }
    }

    return true;
  }

  /**
   * Construire une issue depuis une règle JSON
   * 
   * @param array $rule_result Configuration du résultat depuis JSON
   * @param string $block_name Le nom du bloc
   * @return array Issue formatée
   */
  private static function build_issue_from_rule($rule_result, $block_name): array
  {
    return [
      'type' => $rule_result['type'],
      'severity' => $rule_result['severity'],
      'message' => str_replace('{block_name}', $block_name, $rule_result['message']),
      'suggestion' => $rule_result['suggestion'],
      'blockName' => $block_name,
      'path' => ['raw_content'],
      // Nouvelles propriétés depuis JSON
      'action_recommended' => $rule_result['action_recommended'] ?? null
    ];
  }

  /**
   * Règles par défaut en cas d'absence du fichier JSON
   * 
   * @return array Règles de fallback
   */
  private static function get_default_rules(): array
  {
    return [
      [
        'id' => 'missing_unregistered_fallback',
        'priority' => 1,
        'conditions' => ['api_status' => ['missing'], 'is_registered' => false],
        'result' => [
          'type' => 'BLOCK_CONVERT_TO_HTML',
          'severity' => 'high',
          'message' => 'Bloc non compatible : {block_name}',
          'suggestion' => 'Convertir en HTML ou supprimer'
        ]
      ]
      // Autres règles de fallback...
    ];
  }

  /**
   * Analyser un bloc via l'API WordPress pour déterminer son état réel
   * 
   * @param \WP_Post $post Le post à analyser
   * @param string $block_name Le nom du bloc à analyser
   * @return string L'état du bloc ('missing', 'error', 'recovery_mode', 'invalid', 'valid')
   */
  private static function analyze_block_via_api($post, $block_name)
  {
    try {
      // Parser les blocs du post comme le fait WordPress
      $blocks = parse_blocks($post->post_content);

      // Rechercher le bloc récursivement (dans les innerBlocks aussi)
      $result = self::find_and_validate_block($blocks, $block_name, $post);

      return $result ? $result : 'missing';
    } catch (Exception $e) {
      error_log("Scanner - Exception: " . $e->getMessage());
      return 'error';
    }
  }

  /**
   * Trouver et valider un bloc récursivement dans l'arborescence des blocs
   *
   * @param array $blocks Liste des blocs à analyser
   * @param string $block_name Le nom du bloc à trouver
   * @param \WP_Post $post Le post contenant les blocs
   * @param string $parent_html Le HTML complet du bloc parent (pour les innerBlocks)
   * @return string|null L'état du bloc ou null si non trouvé
   */
  private static function find_and_validate_block($blocks, $block_name, $post = null, $parent_html = '')
  {
    foreach ($blocks as $block) {
      // Vérifier si c'est le bloc recherché
      if ($block['blockName'] === $block_name) {
        // Utiliser la vraie validation WordPress
        $validation_result = self::validate_block_with_wordpress($block, $block_name, $post, $parent_html);

        return $validation_result;
      }

      // Rechercher récursivement dans les innerBlocks
      if (!empty($block['innerBlocks'])) {
        $result = self::find_and_validate_block($block['innerBlocks'], $block_name, $post, $parent_html);
        if ($result) {
          return $result;
        }
      }
    }

    return null;
  }

  /**
   * Utiliser la validation WordPress native pour déterminer l'état d'un bloc
   * Cette méthode reproduit exactement la logique de WordPress pour détecter les blocs en recovery mode
   *
   * @param array $block Le bloc à valider
   * @param string $block_name Le nom du bloc
   * @param \WP_Post|null $post Le post contenant le bloc (pour extract_block_markup_from_post)
   * @param string $saved_html Le HTML sauvegardé du bloc (peut venir du parent pour les innerBlocks)
   * @return string L'état du bloc ('missing', 'recovery_mode', 'valid')
   */
  private static function validate_block_with_wordpress($block, $block_name, $post = null, $saved_html = '')
  {
    // Vérifier si le bloc est enregistré dans WordPress
    $block_type = \WP_Block_Type_Registry::get_instance()->get_registered($block_name);

    if (!$block_type) {
      // WARNING : Bloc non enregistré (peut être un problème)
      \Company\Diagnostic\Features\Scanner\Core\WPLog::warning("Bloc non enregistré: {$block_name}", '[ContentAnalyzer]');
      return 'missing';
    }

    // Extraire le HTML sauvegardé du bloc
    // innerContent contient le HTML externe sans les innerBlocks
    $saved_innerHTML = '';

    if (!empty($saved_html)) {
      $saved_innerHTML = trim($saved_html);
    } elseif (isset($block['innerContent']) && is_array($block['innerContent'])) {
      // innerContent est un tableau qui contient les strings HTML et null pour les innerBlocks
      // On ne garde que les strings HTML
      $html_parts = array_filter($block['innerContent'], 'is_string');
      $saved_innerHTML = trim(implode('', $html_parts));
    } elseif (isset($block['innerHTML'])) {
      $saved_innerHTML = trim($block['innerHTML']);
    }

    // Si le bloc n'a pas de contenu HTML, il est considéré comme valide
    // (cas des blocs dynamiques ou sans rendu côté client)
    if (empty($saved_innerHTML)) {
      // Log INFO supprimé (non critique en production)
      return 'valid';
    }

    // CORRECTIF : Détecter le type de bloc (statique vs dynamique)
    // Les blocs statiques (avec save.js) ne doivent PAS être validés avec render_block()
    // car ils n'ont pas de callback PHP de rendu
    $is_dynamic_block = self::is_dynamic_block($block_type);

    if (!$is_dynamic_block) {
      // Bloc statique : le HTML est généré côté client par save.js
      // La validation côté serveur PHP n'est PAS fiable car elle ne peut pas exécuter save.js
      //
      // SOLUTION : Validation JavaScript via l'éditeur Gutenberg
      // WordPress utilise block.isValid qui compare le HTML sauvegardé avec le HTML régénéré par save.js
      // Cette comparaison ne peut se faire que côté JavaScript dans l'éditeur
      //
      // Pour l'instant, on considère les blocs statiques comme valides côté PHP
      // La vraie validation se fera via gutenberg-validation.js en iframe
      // Log INFO supprimé (non critique en production)

      // Vérifier seulement les marqueurs d'erreur explicites dans le HTML
      $recovery_status = self::check_recovery_markers($saved_innerHTML, $block_name);
      if ($recovery_status === 'recovery_mode') {
        return 'recovery_mode';
      }

      // Pour les blocs statiques, on retourne 'valid' côté PHP
      // La validation précise sera faite par JavaScript (gutenberg-validation.js)
      return 'valid';
    }

    // MÉTHODE WORDPRESS NATIVE pour les blocs dynamiques : Régénérer le HTML du bloc et comparer
    // WordPress utilise cette approche dans is_block_content_valid()
    // Log INFO supprimé (non critique en production)

    try {
      // Créer un bloc temporaire avec les mêmes attributs mais sans innerBlocks
      // pour tester uniquement le HTML externe du bloc
      $test_block = [
        'blockName' => $block_name,
        'attrs' => $block['attrs'] ?? [],
        'innerBlocks' => [], // On teste uniquement le HTML externe
        'innerHTML' => '', // Vide pour la régénération
        'innerContent' => [] // Vide pour la régénération
      ];

      // Régénérer le HTML du bloc comme WordPress le ferait
      $regenerated_html = trim(render_block($test_block));

      // Normaliser les deux HTML pour la comparaison
      $saved_normalized = self::normalize_html($saved_innerHTML);
      $regenerated_normalized = self::normalize_html($regenerated_html);

      // Log DEBUG supprimé (verbeux, uniquement utile pour debugging)

      // Si le HTML régénéré est différent du HTML sauvegardé, le bloc est en recovery
      if ($saved_normalized !== $regenerated_normalized) {
        // WARNING : Bloc en recovery mode détecté
        \Company\Diagnostic\Features\Scanner\Core\WPLog::warning(
          "Bloc {$block_name} en recovery mode - HTML différent",
          '[ContentAnalyzer]'
        );
        return 'recovery_mode';
      }

      // Log INFO supprimé (non critique en production)
      return 'valid';

    } catch (\Exception $e) {
      // En cas d'erreur lors de la régénération, considérer comme potentiellement en recovery
      // ERROR : Erreur critique lors de la validation
      \Company\Diagnostic\Features\Scanner\Core\WPLog::error(
        "Erreur lors de la validation du bloc {$block_name}: " . $e->getMessage(),
        '[ContentAnalyzer]'
      );

      // Fallback : vérifier si le HTML contient des marqueurs de recovery
      return self::check_recovery_markers($saved_innerHTML, $block_name);
    }
  }

  /**
   * Déterminer si un bloc est dynamique (callback PHP) ou statique (save.js)
   *
   * Un bloc dynamique a un render_callback qui génère le HTML côté serveur.
   * Un bloc statique a un save.js qui génère le HTML côté client.
   *
   * @param \WP_Block_Type $block_type Le type de bloc à vérifier
   * @return bool True si le bloc est dynamique, false si statique
   */
  private static function is_dynamic_block($block_type): bool
  {
    // Vérifier si le bloc a un callback de rendu PHP
    // Si render_callback est défini et n'est pas null, c'est un bloc dynamique
    if (isset($block_type->render_callback) && $block_type->render_callback !== null) {
      return true;
    }

    // Pas de callback PHP = bloc statique avec save.js
    return false;
  }

  /**
   * Normaliser le HTML pour la comparaison
   * Supprime les espaces, retours à la ligne et différences non significatives
   *
   * @param string $html Le HTML à normaliser
   * @return string Le HTML normalisé
   */
  private static function normalize_html($html)
  {
    // Supprimer les espaces multiples
    $html = preg_replace('/\s+/', ' ', $html);

    // Supprimer les espaces autour des balises
    $html = preg_replace('/>\s+</', '><', $html);

    // Supprimer les espaces en début et fin
    $html = trim($html);

    // Normaliser les guillemets
    $html = str_replace(["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"], ['"', '"', "'", "'"], $html);

    // Supprimer les attributs data-block-* ajoutés par WordPress
    $html = preg_replace('/\s*data-block-[^=]*="[^"]*"/', '', $html);

    return $html;
  }

  /**
   * Vérifier la présence de marqueurs de recovery dans le HTML
   * Utilisé comme fallback si la validation par régénération échoue
   *
   * @param string $html Le HTML à vérifier
   * @param string $block_name Le nom du bloc
   * @return string L'état du bloc
   */
  private static function check_recovery_markers($html, $block_name)
  {
    $recovery_markers = [
      'wp-block-html',               // Bloc converti en HTML
      'is-invalid',                  // Classe WordPress pour blocs invalides
      'has-warning',                 // Avertissement WordPress
      'block-editor-warning',        // Composant d'avertissement
      'invalid-block',               // Marqueur d'invalidité
      'This block contains unexpected or invalid content', // Message WordPress
      'Attempt Block Recovery',      // Bouton de recovery
      'Convert to HTML',             // Bouton de conversion
    ];

    foreach ($recovery_markers as $marker) {
      if (stripos($html, $marker) !== false) {
        // WARNING : Marqueur de recovery détecté dans le HTML
        \Company\Diagnostic\Features\Scanner\Core\WPLog::warning(
          "Bloc {$block_name} contient le marqueur de recovery '{$marker}'",
          '[ContentAnalyzer]'
        );
        return 'recovery_mode';
      }
    }

    return 'valid';
  }
}
