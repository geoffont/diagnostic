<?php

/**
 * Registre et gestionnaire des types de blocs WordPress
 *
 * Gère l'inventaire et la classification des types de blocs.
 *
 * @package     Company\GutenbergRecovery\Features\Scanner\Core
 * @author      Company Development Team
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 */

namespace Company\GutenbergRecovery\Features\Scanner\Core;

/**
 * Registre des types de blocs WordPress
 */
class BlockRegistry
{
  /**
   * Vérifier si un bloc est enregistré
   * 
   * @param string $blockName Le nom du bloc à vérifier
   * @return bool True si le bloc est enregistré
   */
  public static function is_block_registered($blockName)
  {
    \Company\GutenbergRecovery\Features\Scanner\Core\WPLog::add('Vérification du bloc: ' . $blockName, '[BlockRegistry]');
    if (empty($blockName)) {
      return false;
    }

    // Utiliser directement l'API WordPress
    if (class_exists('WP_Block_Type_Registry')) {
      return \WP_Block_Type_Registry::get_instance()->is_registered($blockName);
    }

    return false;
  }

  /**
   * Vérifier si c'est un bloc create-block
   * 
   * @param string $blockName Le nom du bloc à vérifier
   * @return bool True si c'est un bloc create-block
   */
  public static function is_create_block($blockName)
  {
    return strpos($blockName, 'create-block/') === 0;
  }

  /**
   * Obtenir tous les post types valides pour l'analyse
   * 
   * @return array Liste des post types valides
   */
  public static function get_valid_post_types()
  {
    \Company\GutenbergRecovery\Features\Scanner\Core\WPLog::add('Récupération des post types valides', '[BlockRegistry]');
    // Récupérer automatiquement tous les post types publics
    $all_post_types = get_post_types(['public' => true], 'names');

    // Exclure les types qui ne supportent pas l'éditeur de blocs ou qui sont spéciaux
    $excluded_types = ['attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache'];
    $valid_post_types = array_diff($all_post_types, $excluded_types);

    return array_values($valid_post_types);
  }
}
