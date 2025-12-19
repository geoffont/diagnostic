<?php

/**
 * Générateur de blocs Gutenberg pour contenus de test
 *
 * Ce fichier contient la logique de création de blocs Gutenberg variés
 * pour les posts de test. Il génère différents types de blocs (paragraphe,
 * image, liste, etc.) avec du contenu aléatoire mais cohérent pour
 * tester le rendu et la compatibilité des blocs.
 *
 * @package     Company\GutenbergRecovery\Features\PostGenerator\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Génération de blocs Gutenberg variés
 * - Création de contenu aléatoire cohérent
 * - Support des différents types de blocs core
 * - Configuration des attributs de blocs
 * - Génération de HTML valide pour blocs
 *
 * @dependencies:
 * - WordPress Gutenberg blocks API
 * - Constants (configuration)
 * - PHP random functions
 *
 * @related_files:
 * - PostContentGenerator.php (assemblage final)
 * - Constants.php (configuration)
 *
 * @block_types:
 * - Core blocks (paragraph, heading, image, list)
 * - Media blocks (gallery, video, audio)
 * - Layout blocks (columns, group, separator)
 */

namespace Company\GutenbergRecovery\Features\PostGenerator\Core;

use Company\GutenbergRecovery\Common\Constants;

/**
 * Générateur de blocs Gutenberg
 * 
 * Classe responsable de la création de blocs Gutenberg
 * avec contenu varié pour les posts de test.
 */
class BlockGenerator
{
  /**
   * Types de blocs supportés pour la génération
   * 
   * @var array<string>
   */
  private const BLOCK_TYPES = [
    'paragraph',
    'heading',
    'image',
    'quote',
    'list',
    'gallery',
    'code',
    'table',
    'video',
    'audio',
    'button',
    'columns',
    'group',
    'cover',
    'separator',
    'spacer',
    'shortcode',
    'html',
    'preformatted',
    'pullquote',
    'verse'
  ];

  /**
   * Blocs problématiques pour tester le scanner
   */
  private const PROBLEMATIC_BLOCKS = [
    'create-block/test-block',
    'create-block/non-existent-block',
    'create-block/broken-block',
    'custom/invalid-block',
    'namespace/missing-block'
  ];

  /**
   * Générer du contenu de bloc aléatoire
   * 
   * @param int $block_count Nombre de blocs à générer
   * @param bool $include_problematic Inclure des blocs problématiques
   * @return string Contenu HTML des blocs générés
   */
  public static function generate_random_blocks(int $block_count = 10, bool $include_problematic = true): string
  {
    $blocks = [];

    for ($i = 0; $i < $block_count; $i++) {
      // 80% de chance de générer un bloc normal, 20% un bloc problématique
      if ($include_problematic && rand(1, 100) <= 20) {
        $blocks[] = self::generate_problematic_block();
      } else {
        $blocks[] = self::generate_normal_block();
      }
    }

    return implode("\n\n", $blocks);
  }

  /**
   * Générer un bloc normal WordPress
   * 
   * @return string HTML du bloc généré
   */
  private static function generate_normal_block(): string
  {
    $block_type = self::BLOCK_TYPES[array_rand(self::BLOCK_TYPES)];

    switch ($block_type) {
      case 'paragraph':
        return self::generate_paragraph_block();

      case 'heading':
        return self::generate_heading_block();

      case 'image':
        return self::generate_image_block();

      case 'quote':
        return self::generate_quote_block();

      case 'list':
        return self::generate_list_block();

      case 'code':
        return self::generate_code_block();

      case 'button':
        return self::generate_button_block();

      case 'columns':
        return self::generate_columns_block();

      default:
        return self::generate_paragraph_block();
    }
  }

  /**
   * Générer un bloc problématique pour tester le scanner
   * 
   * @return string HTML du bloc problématique
   */
  private static function generate_problematic_block(): string
  {
    $block_name = self::PROBLEMATIC_BLOCKS[array_rand(self::PROBLEMATIC_BLOCKS)];
    $content = self::generate_lorem_ipsum(rand(5, 15));

    // Générer différents types de problèmes
    $problem_type = rand(1, 4);

    switch ($problem_type) {
      case 1:
        // Bloc non enregistré
        return "<!-- wp:{$block_name} -->\n<div class=\"wp-block-{$block_name}\">{$content}</div>\n<!-- /wp:{$block_name} -->";

      case 2:
        // Bloc avec attributs invalides
        $invalid_attrs = '{"invalidAttribute":true,"brokenData":[1,2,3}';
        return "<!-- wp:{$block_name} {$invalid_attrs} -->\n<div>{$content}</div>\n<!-- /wp:{$block_name} -->";

      case 3:
        // Bloc avec contenu malformé
        return "<!-- wp:{$block_name} -->\n<p class=\"wp-block-{$block_name}\">{$content}\n<!-- /wp:{$block_name} -->";

      case 4:
        // Bloc avec namespace inexistant
        return "<!-- wp:nonexistent/block -->\n<div>{$content}</div>\n<!-- /wp:nonexistent/block -->";

      default:
        return "<!-- wp:{$block_name} -->\n<div>{$content}</div>\n<!-- /wp:{$block_name} -->";
    }
  }

  /**
   * Générer un bloc paragraphe
   */
  private static function generate_paragraph_block(): string
  {
    $content = self::generate_lorem_ipsum(rand(10, 50));
    return "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->";
  }

  /**
   * Générer un bloc titre
   */
  private static function generate_heading_block(): string
  {
    $level = rand(1, 6);
    $content = self::generate_lorem_ipsum(rand(3, 8));
    return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>{$content}</h{$level}>\n<!-- /wp:heading -->";
  }

  /**
   * Générer un bloc image
   */
  private static function generate_image_block(): string
  {
    $id = rand(1, 100);
    $url = "https://picsum.photos/800/600?random={$id}";
    $alt = "Image de test " . $id;

    return "<!-- wp:image {\"id\":{$id},\"url\":\"{$url}\",\"alt\":\"{$alt}\"} -->\n<figure class=\"wp-block-image\"><img src=\"{$url}\" alt=\"{$alt}\"/></figure>\n<!-- /wp:image -->";
  }

  /**
   * Générer un bloc citation
   */
  private static function generate_quote_block(): string
  {
    $quote = self::generate_lorem_ipsum(rand(15, 30));
    $author = "Auteur " . rand(1, 20);

    return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>{$quote}</p><cite>{$author}</cite></blockquote>\n<!-- /wp:quote -->";
  }

  /**
   * Générer un bloc liste
   */
  private static function generate_list_block(): string
  {
    $is_ordered = rand(0, 1);
    $tag = $is_ordered ? 'ol' : 'ul';
    $items = [];

    for ($i = 0; $i < rand(3, 7); $i++) {
      $items[] = "<li>" . self::generate_lorem_ipsum(rand(3, 10)) . "</li>";
    }

    $list_content = implode("\n", $items);
    $type = $is_ordered ? 'ordered' : '';

    return "<!-- wp:list {\"ordered\":{$is_ordered}} -->\n<{$tag} class=\"wp-block-list\">\n{$list_content}\n</{$tag}>\n<!-- /wp:list -->";
  }

  /**
   * Générer un bloc code
   */
  private static function generate_code_block(): string
  {
    $languages = ['php', 'javascript', 'css', 'html', 'python', 'sql'];
    $language = $languages[array_rand($languages)];

    $code_samples = [
      'php' => '<?php\nfunction hello_world() {\n    echo "Hello, World!";\n}\nhello_world();',
      'javascript' => 'function calculateSum(a, b) {\n    return a + b;\n}\nconsole.log(calculateSum(5, 3));',
      'css' => '.example {\n    color: #333;\n    font-size: 16px;\n    margin: 10px;\n}',
      'html' => '<div class="container">\n    <h1>Titre</h1>\n    <p>Contenu</p>\n</div>',
    ];

    $code = $code_samples[$language] ?? 'echo "Code example";';

    return "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>{$code}</code></pre>\n<!-- /wp:code -->";
  }

  /**
   * Générer un bloc bouton
   */
  private static function generate_button_block(): string
  {
    $texts = ['En savoir plus', 'Contactez-nous', 'Télécharger', 'S\'inscrire', 'Acheter maintenant'];
    $text = $texts[array_rand($texts)];
    $url = 'https://example.com/' . strtolower(str_replace(' ', '-', $text));

    return "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link\" href=\"{$url}\">{$text}</a></div>\n<!-- /wp:button -->";
  }

  /**
   * Générer un bloc colonnes
   */
  private static function generate_columns_block(): string
  {
    $columns_count = rand(2, 4);
    $columns = [];

    for ($i = 0; $i < $columns_count; $i++) {
      $content = self::generate_lorem_ipsum(rand(20, 40));
      $columns[] = "<!-- wp:column -->\n<div class=\"wp-block-column\">\n<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->\n</div>\n<!-- /wp:column -->";
    }

    $columns_content = implode("\n\n", $columns);

    return "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n{$columns_content}\n</div>\n<!-- /wp:columns -->";
  }

  /**
   * Générer du texte Lorem Ipsum
   * 
   * @param int $word_count Nombre de mots
   * @return string Texte généré
   */
  private static function generate_lorem_ipsum(int $word_count): string
  {
    $words = [
      'lorem',
      'ipsum',
      'dolor',
      'sit',
      'amet',
      'consectetur',
      'adipiscing',
      'elit',
      'sed',
      'do',
      'eiusmod',
      'tempor',
      'incididunt',
      'ut',
      'labore',
      'et',
      'dolore',
      'magna',
      'aliqua',
      'enim',
      'ad',
      'minim',
      'veniam',
      'quis',
      'nostrud',
      'exercitation',
      'ullamco',
      'laboris',
      'nisi',
      'aliquip',
      'ex',
      'ea',
      'commodo',
      'consequat',
      'duis',
      'aute',
      'irure',
      'in',
      'reprehenderit',
      'voluptate',
      'velit',
      'esse',
      'cillum',
      'fugiat',
      'nulla',
      'pariatur',
      'excepteur',
      'sint',
      'occaecat',
      'cupidatat',
      'non',
      'proident',
      'sunt',
      'culpa',
      'qui',
      'officia',
      'deserunt',
      'mollit',
      'anim',
      'id',
      'est',
      'laborum'
    ];

    $selected_words = [];
    for ($i = 0; $i < $word_count; $i++) {
      $selected_words[] = $words[array_rand($words)];
    }

    return implode(' ', $selected_words);
  }
}
