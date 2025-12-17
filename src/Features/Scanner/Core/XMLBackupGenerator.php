<?php

/**
 * Générateur de sauvegardes XML pour les posts avec problèmes
 *
 * Génère des fichiers XML de sauvegarde pour les posts problématiques.
 *
 * @package     Company\Diagnostic\Features\Scanner\Core
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 */

namespace Company\Diagnostic\Features\Scanner\Core;

use Company\Diagnostic\Common\Constants;

/**
 * Générateur de sauvegardes XML
 */
class XMLBackupGenerator
{
  /**
   * Répertoire de stockage des sauvegardes
   */
  private const BACKUP_DIR = 'diagnostic-backups';

  /**
   * Nombre maximum de sauvegardes à conserver
   */
  private const MAX_BACKUPS = 10;

  /**
   * Extension des fichiers de sauvegarde
   */
  private const BACKUP_EXTENSION = '.xml';

  /**
   * Générer une sauvegarde XML pour les posts avec problèmes
   *
   * @param array $postsWithIssues Posts détectés avec des problèmes
   * @param array $scanResults Résultats complets du scan
   * @return array Informations sur la sauvegarde générée
   */
  public static function generateBackup(array $postsWithIssues, array $scanResults): array
  {
    \Company\Diagnostic\Features\Scanner\Core\WPLog::add('Génération de sauvegarde XML', '[XMLBackupGenerator]');
    try {
      // Créer le répertoire de sauvegarde si nécessaire
      $backupDir = self::ensureBackupDirectory();
      \Company\Diagnostic\Features\Scanner\Core\WPLog::add('Répertoire de sauvegarde: ' . $backupDir, '[XMLBackupGenerator]');

      // Générer le nom de fichier avec timestamp
      $filename = self::generateBackupFilename();
      \Company\Diagnostic\Features\Scanner\Core\WPLog::add('Nom du fichier de sauvegarde: ' . $filename, '[XMLBackupGenerator]');
      $filepath = $backupDir . '/' . $filename;

      // Créer le document XML
      $xml = self::createXMLDocument($postsWithIssues, $scanResults);

      // Sauvegarder le fichier
      $saved = file_put_contents($filepath, $xml);

      if ($saved === false) {
        throw new \Exception('Impossible d\'écrire le fichier de sauvegarde');
      }

      // Nettoyer les anciennes sauvegardes
      self::cleanupOldBackups($backupDir);

      return [
        'success' => true,
        'filepath' => $filepath,
        'filename' => $filename,
        'size' => filesize($filepath),
        'posts_count' => count($postsWithIssues),
        'url' => self::getBackupUrl($filename),
        'created_at' => current_time('mysql')
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'posts_count' => count($postsWithIssues)
      ];
    }
  }

  /**
   * Créer le répertoire de sauvegarde
   *
   * @return string Chemin du répertoire de sauvegarde
   * @throws \Exception Si le répertoire ne peut pas être créé
   */
  private static function ensureBackupDirectory(): string
  {
    $uploadDir = wp_upload_dir();
    $backupDir = $uploadDir['basedir'] . '/' . self::BACKUP_DIR;

    if (!file_exists($backupDir)) {
      if (!wp_mkdir_p($backupDir)) {
        throw new \Exception('Impossible de créer le répertoire de sauvegarde');
      }

      // Créer un fichier .htaccess pour protéger les sauvegardes
      $htaccess = $backupDir . '/.htaccess';
      $htaccess_content = <<<HTACCESS
# Bloquer l'accès direct aux fichiers XML
<Files "*.xml">
    Order deny,allow
    Deny from all
</Files>

# Permettre l'accès via index.php
<Files "index.php">
    Order allow,deny
    Allow from all
</Files>

# Bloquer le listing des répertoires
Options -Indexes
HTACCESS;
      file_put_contents($htaccess, $htaccess_content);

      // Créer un index.php vide pour sécurité
      $index = $backupDir . '/index.php';
      file_put_contents($index, "<?php\n// Silence is golden.\n");
    }

    return $backupDir;
  }

  /**
   * Générer un nom de fichier unique pour la sauvegarde
   *
   * @return string Nom de fichier avec timestamp
   */
  private static function generateBackupFilename(): string
  {
    $timestamp = current_time('Y-m-d_H-i-s');
    $site_url = parse_url(home_url(), PHP_URL_HOST);
    $site_name = sanitize_file_name($site_url ?: 'unknown');

    return "scanner-backup_{$site_name}_{$timestamp}" . self::BACKUP_EXTENSION;
  }

  /**
   * Créer le document XML avec tous les posts problématiques
   *
   * @param array $postsWithIssues Posts avec problèmes
   * @param array $scanResults Résultats du scan
   * @return string Contenu XML formaté
   */
  private static function createXMLDocument(array $postsWithIssues, array $scanResults): string
  {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    // Élément racine
    $root = $dom->createElement('diagnostic_backup');
    $root->setAttribute('version', Constants::VERSION);
    $root->setAttribute('created_at', current_time('c'));
    $root->setAttribute('site_url', home_url());
    $dom->appendChild($root);

    // Informations du scan
    $scanInfo = $dom->createElement('scan_information');
    $scanInfo->appendChild($dom->createElement('total_posts_scanned', $scanResults['total_posts'] ?? 0));
    $scanInfo->appendChild($dom->createElement('posts_with_issues', count($postsWithIssues)));
    $scanInfo->appendChild($dom->createElement('scan_duration', $scanResults['execution_time'] ?? '0'));

    // Statistiques par type d'issue
    $issueStats = $dom->createElement('issue_statistics');
    $issueCounts = self::calculateIssueStatistics($postsWithIssues);
    foreach ($issueCounts as $type => $count) {
      $stat = $dom->createElement('issue_type');
      $stat->setAttribute('type', $type);
      $stat->setAttribute('count', $count);
      $issueStats->appendChild($stat);
    }
    $scanInfo->appendChild($issueStats);
    $root->appendChild($scanInfo);

    // Posts avec problèmes
    $postsElement = $dom->createElement('posts');

    foreach ($postsWithIssues as $post) {
      $postElement = self::createPostElement($dom, $post);
      $postsElement->appendChild($postElement);
    }

    $root->appendChild($postsElement);

    return $dom->saveXML();
  }

  /**
   * Créer un élément XML pour un post
   *
   * @param \DOMDocument $dom Document XML
   * @param array $post Données du post
   * @return \DOMElement Élément XML du post
   */
  private static function createPostElement(\DOMDocument $dom, array $post): \DOMElement
  {
    $postElement = $dom->createElement('post');
    $postElement->setAttribute('id', $post['id']);
    $postElement->setAttribute('type', $post['post_type'] ?? 'post');
    $postElement->setAttribute('status', $post['post_status'] ?? 'publish');

    // Informations de base
    $postElement->appendChild($dom->createElement('title', htmlspecialchars($post['title'] ?? '')));
    $postElement->appendChild($dom->createElement('slug', $post['post_name'] ?? ''));
    $postElement->appendChild($dom->createElement('author_id', $post['post_author'] ?? ''));
    $postElement->appendChild($dom->createElement('created_date', $post['post_date'] ?? ''));
    $postElement->appendChild($dom->createElement('modified_date', $post['post_modified'] ?? ''));

    // URL d'édition
    if (!empty($post['editUrl'])) {
      $postElement->appendChild($dom->createElement('edit_url', htmlspecialchars($post['editUrl'])));
    }

    // Contenu complet
    $contentElement = $dom->createElement('content');
    $contentElement->appendChild($dom->createCDATASection($post['post_content'] ?? ''));
    $postElement->appendChild($contentElement);

    // Extrait si disponible
    if (!empty($post['post_excerpt'])) {
      $excerptElement = $dom->createElement('excerpt');
      $excerptElement->appendChild($dom->createCDATASection($post['post_excerpt']));
      $postElement->appendChild($excerptElement);
    }

    // Issues détectées
    $issuesElement = $dom->createElement('issues');
    foreach ($post['issues'] ?? [] as $issue) {
      $issueElement = $dom->createElement('issue');
      $issueElement->setAttribute('type', $issue['type'] ?? '');
      $issueElement->setAttribute('severity', $issue['severity'] ?? 'medium');
      $issueElement->appendChild($dom->createTextNode($issue['message'] ?? ''));
      $issuesElement->appendChild($issueElement);
    }
    $postElement->appendChild($issuesElement);

    // Métadonnées personnalisées importantes
    $meta = get_post_meta($post['id']);
    if (!empty($meta)) {
      $metaElement = $dom->createElement('custom_fields');
      foreach ($meta as $key => $values) {
        // Exclure certaines métadonnées sensibles ou inutiles
        if (self::shouldIncludeMetaKey($key)) {
          foreach ($values as $value) {
            $fieldElement = $dom->createElement('field');
            $fieldElement->setAttribute('key', $key);
            $fieldElement->appendChild($dom->createCDATASection($value));
            $metaElement->appendChild($fieldElement);
          }
        }
      }
      $postElement->appendChild($metaElement);
    }

    return $postElement;
  }

  /**
   * Calculer les statistiques par type d'issue
   *
   * @param array $postsWithIssues Posts avec problèmes
   * @return array Compteurs par type d'issue
   */
  private static function calculateIssueStatistics(array $postsWithIssues): array
  {
    $stats = [];

    foreach ($postsWithIssues as $post) {
      foreach ($post['issues'] ?? [] as $issue) {
        $type = $issue['type'] ?? 'unknown';
        $stats[$type] = ($stats[$type] ?? 0) + 1;
      }
    }

    return $stats;
  }

  /**
   * Déterminer si une métadonnée doit être incluse dans la sauvegarde
   *
   * @param string $key Clé de métadonnée
   * @return bool True si elle doit être incluse
   */
  private static function shouldIncludeMetaKey(string $key): bool
  {
    // Exclure les métadonnées sensibles ou temporaires
    $excluded = [
      '_edit_lock',
      '_edit_last',
      '_wp_old_slug',
      '_wp_old_date',
      '_pingme',
      '_encloseme'
    ];

    // Exclure les métadonnées qui commencent par un underscore (internes WP)
    // sauf celles importantes pour les blocs
    if (strpos($key, '_') === 0) {
      $important = [
        '_wp_page_template',
        '_thumbnail_id',
        '_wp_attachment_metadata'
      ];

      return in_array($key, $important);
    }

    return !in_array($key, $excluded);
  }

  /**
   * Nettoyer les anciennes sauvegardes
   *
   * @param string $backupDir Répertoire des sauvegardes
   */
  private static function cleanupOldBackups(string $backupDir): void
  {
    $files = glob($backupDir . '/*' . self::BACKUP_EXTENSION);

    if (count($files) > self::MAX_BACKUPS) {
      // Trier par date de modification (plus ancien en premier)
      usort($files, function ($a, $b) {
        return filemtime($a) - filemtime($b);
      });

      // Supprimer les plus anciens
      $filesToDelete = array_slice($files, 0, count($files) - self::MAX_BACKUPS);
      foreach ($filesToDelete as $file) {
        unlink($file);
      }
    }
  }

  /**
   * Obtenir l'URL de téléchargement d'une sauvegarde
   *
   * @param string $filename Nom du fichier
   * @return string URL de téléchargement
   */
  private static function getBackupUrl(string $filename): string
  {
    // Au lieu d'un lien direct, utiliser un endpoint sécurisé
    return admin_url('admin-ajax.php') . '?' . http_build_query([
      'action' => 'diagnostic_download_backup',
      'file' => $filename,
      'nonce' => wp_create_nonce('diagnostic_download_backup')
    ]);
  }

  /**
   * Lister les sauvegardes disponibles
   *
   * @return array Liste des sauvegardes avec leurs informations
   */
  public static function listBackups(): array
  {
    try {
      $uploadDir = wp_upload_dir();
      $backupDir = $uploadDir['basedir'] . '/' . self::BACKUP_DIR;

      if (!file_exists($backupDir)) {
        return [];
      }

      $files = glob($backupDir . '/*' . self::BACKUP_EXTENSION);
      $backups = [];

      foreach ($files as $file) {
        $filename = basename($file);
        $backups[] = [
          'filename' => $filename,
          'size' => filesize($file),
          'created_at' => date('Y-m-d H:i:s', filemtime($file)),
          'url' => self::getBackupUrl($filename)
        ];
      }

      // Trier par date de création (plus récent en premier)
      usort($backups, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
      });

      return $backups;
    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Supprimer une sauvegarde spécifique
   *
   * @param string $filename Nom du fichier à supprimer
   * @return bool True si supprimé avec succès
   */
  public static function deleteBackup(string $filename): bool
  {
    try {
      $uploadDir = wp_upload_dir();
      $filepath = $uploadDir['basedir'] . '/' . self::BACKUP_DIR . '/' . $filename;

      if (file_exists($filepath) && strpos($filename, self::BACKUP_EXTENSION) !== false) {
        return unlink($filepath);
      }

      return false;
    } catch (\Exception $e) {
      return false;
    }
  }
}
