<?php

/**
 * Constantes globales du plugin Diagnostic
 *
 * Ce fichier centralise toutes les constantes utilisées dans le plugin,
 * incluant les versions, noms, capacités, nonces et autres valeurs fixes.
 * Il sert de référentiel unique pour éviter la duplication de valeurs.
 *
 * @package     Company\Diagnostic\Common
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Définition des constantes globales
 * - Centralisation des valeurs de configuration
 * - Éviter la duplication de constantes
 * - Maintenir la cohérence des noms
 *
 * @dependencies:
 * - PHP 8.0+
 * - Aucune dépendance externe
 *
 * @related_files:
 * - Tous les fichiers du plugin (utilisation des constantes)
 * - Functions.php (fonctions utilitaires)
 * - Plugin.php (initialisation)
 */

namespace Company\Diagnostic\Common;

/**
 * Constantes globales du plugin Diagnostic
 * 
 * Classe finale contenant toutes les constantes utilisées dans le plugin.
 * Organisées par catégories : version, noms, capacités, nonces, etc.
 */
final class Constants
{
  /**
   * Version du plugin
   * 
   * @var string
   */
  public const VERSION = '1.0.0';

  /**
   * Text domain pour i18n
   */
  public const TEXT_DOMAIN = 'diagnostic';

  /**
   * Capabilities WordPress
   */
  public const CAP_MANAGE_DIAGNOSTIC = 'manage_options';
  public const CAP_USE_SCANNER = 'manage_options';
  public const CAP_USE_POST_GENERATOR = 'manage_options';

  /**
   * Nonces de sécurité
   */
  public const NONCE_SCANNER = 'diagnostic_scanner_nonce';
  public const NONCE_POST_GENERATOR = 'diagnostic_post_generator_nonce';

  /**
   * Hooks d'actions personnalisées
   */
  public const ACTION_SCANNER_COMPLETE = 'diagnostic_scanner_complete';
  public const ACTION_FEATURE_LOADED = 'diagnostic_feature_loaded';
  public const ACTION_POST_GENERATOR_COMPLETE = 'diagnostic_post_generator_complete';

  /**
   * Types de problèmes détectés par le scanner
   */
  public const ISSUE_CREATE_BLOCK_UNREGISTERED = 'CREATE_BLOCK_UNREGISTERED';
  public const ISSUE_BLOCK_CONVERT_TO_HTML = 'BLOCK_CONVERT_TO_HTML';
  public const ISSUE_BLOCK_RECOVERY_MODE = 'BLOCK_RECOVERY_MODE';

  /**
   * Niveaux de sévérité
   */
  public const SEVERITY_HIGH = 'high';
  public const SEVERITY_MEDIUM = 'medium';
  public const SEVERITY_LOW = 'low';

  /**
   * Configuration par défaut
   */
  public const DEFAULT_SCAN_LIMIT = 1000;
  public const DEFAULT_TIMEOUT = 300;

  /**
   * Empêcher l'instanciation
   */
  private function __construct() {}
}
