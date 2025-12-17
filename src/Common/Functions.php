<?php

/**
 * Fonctions utilitaires communes du plugin Diagnostic
 *
 * Ce fichier contient une collection de fonctions utilitaires pures,
 * sans dépendances WordPress spécifiques. Ces fonctions sont réutilisables
 * dans différentes parties du plugin pour des tâches communes comme
 * la validation, le formatting et la manipulation de données.
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
 * - Fonctions utilitaires pures
 * - Validation de données
 * - Formatage et transformation
 * - Helpers pour sécurité
 * - Outils de manipulation d'arrays
 * - Fonctions de validation (JSON, nonces, etc.)
 *
 * @dependencies:
 * - PHP 8.0+ (types stricts)
 * - Fonctions PHP standard
 * - Quelques fonctions WordPress (wp_verify_nonce, esc_html, etc.)
 *
 * @related_files:
 * - Constants.php (constantes utilisées)
 * - Tous les fichiers du plugin (utilisation des utilitaires)
 *
 * @design_patterns:
 * - Static methods pour éviter l'instanciation
 * - Type hints stricts pour la sécurité
 * - Fonctions pures sans effets de bord
 */

namespace Company\Diagnostic\Common;

/**
 * Fonctions utilitaires communes
 * 
 * Classe statique contenant des fonctions utilitaires pures
 * réutilisables dans tout le plugin.
 */
class Functions
{
  /**
   * Valider et fusionner une configuration avec ses valeurs par défaut
   * 
   * Prend un array de configuration utilisateur et le fusionne avec
   * des valeurs par défaut, en ne gardant que les clés autorisées.
   * 
   * @param array $config Configuration utilisateur
   * @param array $defaults Valeurs par défaut et clés autorisées
   * @return array Configuration validée et complétée
   */
  public static function validate_config(array $config, array $defaults): array
  {
    return array_merge($defaults, array_intersect_key($config, $defaults));
  }

  /**
   * Nettoyer une chaîne pour un slug
   */
  public static function sanitize_slug(string $string): string
  {
    return sanitize_key(trim($string));
  }

  /**
   * Formater un pourcentage
   */
  public static function format_percentage(int $value, int $total): string
  {
    if ($total === 0) {
      return '0%';
    }

    $percentage = round(($value / $total) * 100, 1);
    return $percentage . '%';
  }

  /**
   * Valider un nonce de sécurité
   */
  public static function verify_nonce(string $nonce, string $action): bool
  {
    return wp_verify_nonce($nonce, $action) !== false;
  }

  /**
   * Générer un ID unique pour les éléments HTML
   */
  public static function generate_html_id(string $base): string
  {
    return $base . '-' . uniqid();
  }

  /**
   * Échapper une chaîne pour l'affichage HTML
   */
  public static function esc_html(string $string): string
  {
    return esc_html($string);
  }

  /**
   * Échapper une URL
   */
  public static function esc_url(string $url): string
  {
    return esc_url($url);
  }

  /**
   * Vérifier si une chaîne est un JSON valide
   */
  public static function is_valid_json(string $string): bool
  {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }
}
