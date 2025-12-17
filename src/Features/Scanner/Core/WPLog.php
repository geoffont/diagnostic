<?php

namespace Company\Diagnostic\Features\Scanner\Core;

/**
 * Classe utilitaire pour la gestion des logs du Scanner
 *
 * Fournit un système de logging avec niveaux (ERROR, WARNING, INFO, DEBUG)
 * et un mode verbose pour activer/désactiver les logs non-critiques.
 *
 * @package     Company\Diagnostic\Features\Scanner\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-12-02
 *
 * @example
 * ```php
 * // Activer le mode verbose
 * WPLog::set_verbose(true);
 *
 * // Logs critiques (toujours affichés)
 * WPLog::error('Erreur critique', '[Scanner]');
 * WPLog::warning('Avertissement', '[Scanner]');
 *
 * // Logs de debug (seulement en mode verbose)
 * WPLog::info('Information', '[Scanner]');
 * WPLog::debug('Message de debug', '[Scanner]');
 * ```
 */
class WPLog
{
  /**
   * Constantes de niveaux de log
   */
  const LEVEL_ERROR = 'ERROR';
  const LEVEL_WARNING = 'WARNING';
  const LEVEL_INFO = 'INFO';
  const LEVEL_DEBUG = 'DEBUG';

  /**
   * Logs en mémoire
   * @var array
   */
  private static $logs = [];

  /**
   * Mode verbose activé/désactivé
   * @var bool
   */
  private static $verbose_mode = false;

  /**
   * Activer/désactiver le mode verbose
   *
   * En mode verbose, tous les logs sont enregistrés.
   * Sinon, seuls les ERROR et WARNING sont enregistrés.
   *
   * @param bool $enabled
   * @return void
   */
  public static function set_verbose($enabled)
  {
    self::$verbose_mode = (bool) $enabled;
  }

  /**
   * Vérifier si le mode verbose est activé
   *
   * @return bool
   */
  public static function is_verbose()
  {
    return self::$verbose_mode;
  }

  /**
   * Ajouter un log avec niveau
   *
   * @deprecated Utiliser error(), warning(), info() ou debug() à la place
   * @param string $message Message à logger
   * @param string $context Contexte du log (ex: '[Scanner]')
   * @return void
   */
  public static function add($message, $context = '[Scanner]')
  {
    // Par défaut, traiter comme INFO pour compatibilité ascendante
    self::log(self::LEVEL_INFO, $message, $context);
  }

  /**
   * Logger une erreur critique (toujours enregistrée)
   *
   * @param string $message Message d'erreur
   * @param string $context Contexte du log
   * @return void
   */
  public static function error($message, $context = '[Scanner]')
  {
    self::log(self::LEVEL_ERROR, $message, $context);
  }

  /**
   * Logger un avertissement (toujours enregistré)
   *
   * @param string $message Message d'avertissement
   * @param string $context Contexte du log
   * @return void
   */
  public static function warning($message, $context = '[Scanner]')
  {
    self::log(self::LEVEL_WARNING, $message, $context);
  }

  /**
   * Logger une information (seulement en mode verbose)
   *
   * @param string $message Message d'information
   * @param string $context Contexte du log
   * @return void
   */
  public static function info($message, $context = '[Scanner]')
  {
    if (self::$verbose_mode) {
      self::log(self::LEVEL_INFO, $message, $context);
    }
  }

  /**
   * Logger un message de debug (seulement en mode verbose)
   *
   * @param string $message Message de debug
   * @param string $context Contexte du log
   * @return void
   */
  public static function debug($message, $context = '[Scanner]')
  {
    if (self::$verbose_mode) {
      self::log(self::LEVEL_DEBUG, $message, $context);
    }
  }

  /**
   * Méthode interne de logging
   *
   * @param string $level Niveau du log (ERROR, WARNING, INFO, DEBUG)
   * @param string $message Message à logger
   * @param string $context Contexte du log
   * @return void
   */
  private static function log($level, $message, $context)
  {
    $entry = sprintf(
      '%s %s [%s]: %s',
      date('Y-m-d H:i:s'),
      $context,
      $level,
      $message
    );

    // Ajouter au tableau en mémoire
    self::$logs[] = [
      'timestamp' => date('Y-m-d H:i:s'),
      'level' => $level,
      'context' => $context,
      'message' => $message,
      'formatted' => $entry
    ];

    // Écrire dans error_log WordPress
    error_log($entry);
  }

  /**
   * Récupérer les logs en mémoire
   *
   * @param string|null $level Filtrer par niveau (ERROR, WARNING, INFO, DEBUG)
   * @param string|null $context Filtrer par contexte
   * @return array Liste des logs
   */
  public static function get_logs($context = null, $level = null)
  {
    $logs = self::$logs;

    // Filtrer par contexte si spécifié
    if ($context !== null) {
      $logs = array_filter($logs, function ($log) use ($context) {
        return $log['context'] === $context;
      });
    }

    // Filtrer par niveau si spécifié
    if ($level !== null) {
      $logs = array_filter($logs, function ($log) use ($level) {
        return $log['level'] === $level;
      });
    }

    return array_values($logs);
  }

  /**
   * Effacer tous les logs en mémoire
   *
   * @return void
   */
  public static function clear()
  {
    self::$logs = [];
  }

  /**
   * Obtenir le nombre de logs par niveau
   *
   * @return array Tableau associatif [niveau => nombre]
   */
  public static function get_counts()
  {
    $counts = [
      self::LEVEL_ERROR => 0,
      self::LEVEL_WARNING => 0,
      self::LEVEL_INFO => 0,
      self::LEVEL_DEBUG => 0
    ];

    foreach (self::$logs as $log) {
      if (isset($counts[$log['level']])) {
        $counts[$log['level']]++;
      }
    }

    return $counts;
  }
}
