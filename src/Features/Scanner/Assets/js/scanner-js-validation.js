/**
 * Scanner JS Validation Module - Validation JavaScript des blocs via iframe
 *
 * Ce module charge l'éditeur Gutenberg dans un iframe invisible pour chaque post
 * et utilise l'API native de WordPress (block.isValid) pour détecter les blocs invalides.
 *
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       1.0.0
 * @created     2025-09-12
 * @modified    2025-12-02
 *
 * @workflow:
 * 1. Utilisateur clique sur "Valider avec JavaScript"
 * 2. Création d'une iframe pour chaque post avec l'éditeur Gutenberg
 * 3. Le script gutenberg-validation.js s'exécute dans l'iframe
 * 4. Réception des résultats via postMessage
 * 5. Envoi automatique des résultats à BlockRecovery
 * 6. Affichage des résultats avec lien vers BlockRecovery
 *
 * @dependencies:
 * - jQuery
 * - diagnosticScannerData (variables localisées)
 * - gutenberg-validation.js (script exécuté dans l'iframe)
 *
 * @constants:
 * - VALIDATION_TIMEOUT: Timeout pour la validation d'un post (30s)
 */
(function($) {
  'use strict';

  // Constantes
  const VALIDATION_TIMEOUT = 30000; // 30 secondes
  const PARALLEL_WORKERS = 5; // Nombre de posts validés en parallèle
  const CACHE_KEY = 'diagnostic_scanner_cache';
  const CACHE_EXPIRY_DAYS = 30; // Durée de validité du cache

  /**
   * Système de logging conditionnel
   * Les logs ne s'affichent que si window.DEBUG_MODE est true
   */
  const log = {
    info: function(...args) {
      if (window.DEBUG_MODE) {
        console.log('[JS Validation]', ...args);
      }
    },
    warn: function(...args) {
      if (window.DEBUG_MODE) {
        console.warn('[JS Validation]', ...args);
      }
    },
    error: function(...args) {
      if (window.DEBUG_MODE) {
        console.error('[JS Validation]', ...args);
      }
    }
  };

  // État de la validation JS
  let jsValidationInProgress = false;
  let currentPostIndex = 0;
  let postsToValidate = [];
  let validationResults = {};
  let activeWorkers = 0; // Nombre de workers actifs en parallèle
  let completedPosts = 0; // Nombre de posts terminés
  let cacheHits = 0; // Nombre de résultats trouvés dans le cache
  let cacheSkipped = 0; // Nombre de posts skippés grâce au cache

  /**
   * ======================================
   * SYSTÈME DE CACHE
   * ======================================
   */

  /**
   * Récupérer le cache depuis localStorage
   */
  function getCache() {
    try {
      const cacheData = localStorage.getItem(CACHE_KEY);
      if (!cacheData) {
        return {};
      }

      const cache = JSON.parse(cacheData);
      const now = Date.now();

      // Nettoyer les entrées expirées
      const cleanedCache = {};
      let hasExpired = false;

      for (const postId in cache) {
        const entry = cache[postId];
        const expiryTime = entry.timestamp + (CACHE_EXPIRY_DAYS * 24 * 60 * 60 * 1000);

        if (now < expiryTime) {
          cleanedCache[postId] = entry;
        } else {
          hasExpired = true;
        }
      }

      // Sauvegarder le cache nettoyé si des entrées ont expiré
      if (hasExpired) {
        localStorage.setItem(CACHE_KEY, JSON.stringify(cleanedCache));
      }

      return cleanedCache;
    } catch (e) {
      log.error('Erreur lors de la lecture du cache:', e);
      return {};
    }
  }

  /**
   * Sauvegarder un résultat dans le cache
   */
  function setCacheEntry(postId, postModified, result) {
    try {
      const cache = getCache();
      cache[postId] = {
        modified: postModified,
        result: result,
        timestamp: Date.now()
      };

      localStorage.setItem(CACHE_KEY, JSON.stringify(cache));
      log.info(`Cache mis à jour pour le post ${postId}`);
    } catch (e) {
      log.error('Erreur lors de la sauvegarde du cache:', e);
      // Si localStorage est plein, vider le cache et réessayer
      if (e.name === 'QuotaExceededError') {
        clearCache();
        log.warn('Cache vidé car localStorage est plein');
      }
    }
  }

  /**
   * Récupérer un résultat du cache si le post n'a pas été modifié
   */
  function getCacheEntry(postId, postModified) {
    try {
      const cache = getCache();
      const entry = cache[postId];

      if (!entry) {
        return null;
      }

      // Vérifier si le post a été modifié depuis la mise en cache
      if (entry.modified === postModified) {
        log.info(`✓ Cache HIT pour le post ${postId} (non modifié depuis ${postModified})`);
        return entry.result;
      }

      log.info(`✗ Cache MISS pour le post ${postId} (modifié: ${entry.modified} → ${postModified})`);
      return null;
    } catch (e) {
      log.error('Erreur lors de la lecture du cache:', e);
      return null;
    }
  }

  /**
   * Vider complètement le cache
   */
  function clearCache() {
    try {
      localStorage.removeItem(CACHE_KEY);
      log.info('Cache vidé avec succès');
    } catch (e) {
      log.error('Erreur lors du vidage du cache:', e);
    }
  }

  /**
   * Obtenir des statistiques sur le cache
   */
  function getCacheStats() {
    const cache = getCache();
    const entries = Object.keys(cache).length;
    const size = JSON.stringify(cache).length;

    return {
      entries: entries,
      size: size,
      sizeKB: (size / 1024).toFixed(2)
    };
  }

  /**
   * Initialiser la validation JavaScript
   */
  function initJsValidation() {
    // Écouter les messages postMessage des iframes
    window.addEventListener('message', handleValidationMessage);

    // Remplacer le comportement du bouton principal du scanner
    replaceMainScannerButton();
  }

  /**
   * Exposer les fonctions publiques pour l'utiliser depuis l'extérieur
   */
  window.ScannerJsValidation = {
    startValidation: startJsValidationForAllPosts,
    clearCache: function() {
      clearCache();
      const stats = getCacheStats();
      console.log(`✓ Cache vidé. Statistiques actuelles: ${stats.entries} entrées (${stats.sizeKB} KB)`);
      alert('Le cache du scanner a été vidé avec succès. Le prochain scan validera tous les posts.');
    },
    getCacheStats: function() {
      const stats = getCacheStats();
      console.log(`📦 Cache actuel: ${stats.entries} entrées (${stats.sizeKB} KB)`);
      return stats;
    }
  };

  /**
   * Remplacer le comportement du bouton principal du scanner
   */
  function replaceMainScannerButton() {
    const $runScannerBtn = $('#run-scanner-validator');

    if ($runScannerBtn.length === 0) {
      // Si le bouton n'existe pas encore, réessayer après un délai
      setTimeout(replaceMainScannerButton, 500);
      return;
    }

    // Désactiver le comportement par défaut et le remplacer par la validation JS
    $runScannerBtn.off('click');
    $runScannerBtn.on('click', function(e) {
      e.preventDefault();
      startJsValidationForAllPosts();
    });

    // Mettre à jour le texte du bouton
    $runScannerBtn.html('🔍 Analyser tous les blocs');

    log.info('Bouton principal remplacé avec validation JavaScript');
  }

  /**
   * Démarrer la validation JavaScript sur tous les posts
   */
  function startJsValidationForAllPosts() {
    if (jsValidationInProgress) {
      alert('Une validation est déjà en cours...');
      return;
    }

    log.info('Démarrage de l\'analyse de tous les posts...');

    // Afficher l'indicateur de chargement
    showScannerProgress();

    // Récupérer tous les posts via AJAX
    $.ajax({
      url: diagnosticScannerData.ajaxurl,
      type: 'POST',
      data: {
        action: 'get_all_posts_for_validation',
        nonce: diagnosticScannerData.nonce
      },
      success: function(response) {
        if (response.success && response.data && response.data.posts) {
          postsToValidate = response.data.posts;

          log.info('Posts récupérés:', postsToValidate.length);

          // Initialiser la validation
          jsValidationInProgress = true;
          currentPostIndex = 0;
          completedPosts = 0;
          activeWorkers = 0;
          validationResults = {};
          cacheHits = 0;
          cacheSkipped = 0;

          // Afficher les statistiques du cache au démarrage
          const cacheStats = getCacheStats();
          log.info(`📦 Cache actuel: ${cacheStats.entries} entrées (${cacheStats.sizeKB} KB)`);

          // Afficher un indicateur de progression
          showProgressModal();

          // Démarrer les workers en parallèle
          startParallelValidation();
        } else {
          alert('Erreur lors de la récupération des posts: ' + (response.data?.message || 'Erreur inconnue'));
          hideScannerProgress();
        }
      },
      error: function(xhr, status, error) {
        log.error('Erreur AJAX:', error);
        alert('Erreur lors de la récupération des posts');
        hideScannerProgress();
      }
    });
  }

  /**
   * Afficher l'indicateur de progression du scanner
   */
  function showScannerProgress() {
    $('#scanner-progress').show();
    $('#run-scanner-validator').prop('disabled', true);
  }

  /**
   * Masquer l'indicateur de progression du scanner
   */
  function hideScannerProgress() {
    $('#scanner-progress').hide();
    $('#run-scanner-validator').prop('disabled', false);
  }


  /**
   * Démarrer la validation en parallèle
   */
  function startParallelValidation() {
    // Lancer autant de workers que possible (jusqu'à PARALLEL_WORKERS)
    const workersToStart = Math.min(PARALLEL_WORKERS, postsToValidate.length);

    log.info(`Démarrage de ${workersToStart} workers en parallèle`);

    for (let i = 0; i < workersToStart; i++) {
      validateNextPost();
    }
  }

  /**
   * Valider le prochain post
   */
  function validateNextPost() {
    // Si tous les posts sont assignés, ne rien faire
    if (currentPostIndex >= postsToValidate.length) {
      return;
    }

    const postIndex = currentPostIndex;
    const post = postsToValidate[postIndex];

    // Incrémenter l'index pour le prochain worker
    currentPostIndex++;
    activeWorkers++;

    // Vérifier le cache avant de lancer la validation
    const cachedResult = getCacheEntry(post.id, post.modified);

    if (cachedResult) {
      // Résultat trouvé dans le cache
      cacheHits++;
      validationResults[post.id] = cachedResult;

      log.info(`✓ Post ${post.id} chargé depuis le cache (${postIndex + 1}/${postsToValidate.length})`);

      // Marquer immédiatement comme terminé
      onPostValidationComplete(post.id);
    } else {
      // Pas de cache, validation normale
      log.info(`Worker démarré pour post ${post.id} (${postIndex + 1}/${postsToValidate.length})`);

      // Créer une iframe pour charger l'éditeur
      createValidationIframe(post.id, post.title, post.modified);
    }
  }

  /**
   * Callback appelé quand un post est terminé (succès ou erreur)
   */
  function onPostValidationComplete(postId) {
    activeWorkers--;
    completedPosts++;

    log.info(`Post ${postId} terminé. Progression: ${completedPosts}/${postsToValidate.length}, Workers actifs: ${activeWorkers}`);

    // Mettre à jour la barre de progression
    const currentPost = postsToValidate.find(p => p.id == postId);
    updateProgressModal(completedPosts, postsToValidate.length, currentPost ? currentPost.title : '');

    // Si tous les posts sont terminés
    if (completedPosts >= postsToValidate.length) {
      log.info('Tous les posts sont terminés, finalisation...');
      finishJsValidation();
      return;
    }

    // Démarrer la validation du prochain post (pour maintenir PARALLEL_WORKERS actifs)
    validateNextPost();
  }

  /**
   * Créer une iframe pour valider un post
   */
  function createValidationIframe(postId, postTitle, postModified) {
    // Utiliser l'URL admin WordPress
    const adminUrl = diagnosticScannerData.ajaxurl.replace('/admin-ajax.php', '');
    const editorUrl = `${adminUrl}/post.php?post=${postId}&action=edit&js_validation=1`;

    log.info('Chargement de l\'iframe pour le post', postId, ':', editorUrl);

    // Créer l'iframe
    const $iframe = $('<iframe>', {
      id: `validation-iframe-${postId}`,
      src: editorUrl,
      'data-post-modified': postModified, // Stocker la date de modification dans l'iframe
      css: {
        position: 'absolute',
        left: '-9999px',
        width: '1px',
        height: '1px',
        border: 'none'
      }
    });

    // Ajouter l'iframe au DOM
    $('body').append($iframe);

    // Timeout de sécurité
    setTimeout(function() {
      const $existingIframe = $(`#validation-iframe-${postId}`);
      if ($existingIframe.length > 0) {
        log.error(`Timeout pour le post ${postId}`);
        $existingIframe.remove();

        validationResults[postId] = {
          success: false,
          error: 'Timeout - l\'éditeur n\'a pas répondu dans les 30 secondes'
        };

        // Notifier que ce post est terminé (pas de mise en cache pour les timeouts)
        onPostValidationComplete(postId);
      }
    }, VALIDATION_TIMEOUT);
  }

  /**
   * Gérer les messages postMessage des iframes
   */
  function handleValidationMessage(event) {
    // Vérifier que le message vient de notre iframe
    if (!event.data || event.data.type !== 'gutenberg_validation_complete') {
      return;
    }

    const data = event.data;
    log.info('Résultats reçus:', data);

    // Extraire l'ID du post depuis l'URL de l'iframe
    const postId = extractPostIdFromMessage(event);

    if (!postId) {
      log.error('Impossible d\'extraire l\'ID du post');
      return;
    }

    // Récupérer la date de modification depuis l'iframe
    const $iframe = $(`#validation-iframe-${postId}`);
    const postModified = $iframe.attr('data-post-modified');

    // Sauvegarder les résultats
    validationResults[postId] = data;

    // Sauvegarder dans le cache (seulement si la validation a réussi)
    if (data.success && postModified) {
      setCacheEntry(postId, postModified, data);
    }

    // Supprimer l'iframe
    $iframe.remove();

    // Notifier que ce post est terminé
    onPostValidationComplete(postId);
  }

  /**
   * Extraire l'ID du post depuis le message postMessage
   */
  function extractPostIdFromMessage(event) {
    // Essayer d'extraire depuis l'URL de la source
    try {
      const url = event.source.location.href;
      const match = url.match(/post=(\d+)/);
      return match ? match[1] : null;
    } catch (e) {
      // Erreur de sécurité cross-origin - chercher l'iframe correspondante
      const $iframes = $('iframe[id^="validation-iframe-"]');
      for (let i = 0; i < $iframes.length; i++) {
        const $iframe = $iframes.eq(i);
        if ($iframe[0].contentWindow === event.source) {
          const match = $iframe.attr('id').match(/validation-iframe-(\d+)/);
          return match ? match[1] : null;
        }
      }
    }
    return null;
  }

  /**
   * Afficher une modal de progression
   */
  function showProgressModal() {
    const html = `
      <div id="js-validation-modal">
        <h2>Détection des blocs en récupération en cours...</h2>
        <p id="js-validation-progress">Préparation...</p>
        <div class="progress-container">
          <div id="js-validation-progress-bar"></div>
        </div>
        <p class="progress-note">Ne fermez pas cette fenêtre pendant la validation.</p>
      </div>
      <div id="js-validation-overlay"></div>
    `;

    $('body').append(html);
  }

  /**
   * Mettre à jour la modal de progression
   */
  function updateProgressModal(current, total, postTitle) {
    const percent = Math.round((current / total) * 100);
    $('#js-validation-progress').text(`Détection ${current}/${total}: ${postTitle}`);
    $('#js-validation-progress-bar').css('width', `${percent}%`);
  }

  /**
   * Terminer la validation JavaScript
   */
  function finishJsValidation() {
    jsValidationInProgress = false;

    // Afficher les statistiques du cache
    const cacheStats = getCacheStats();
    const totalPosts = postsToValidate.length;
    const validatedPosts = totalPosts - cacheHits;

    log.info(`
╔═══════════════════════════════════════════════════════
║ 📊 STATISTIQUES DU SCAN
╠═══════════════════════════════════════════════════════
║ Total posts:          ${totalPosts}
║ Posts depuis cache:   ${cacheHits} (${Math.round(cacheHits / totalPosts * 100)}%)
║ Posts validés:        ${validatedPosts} (${Math.round(validatedPosts / totalPosts * 100)}%)
║
║ 📦 Cache actuel:      ${cacheStats.entries} entrées (${cacheStats.sizeKB} KB)
╚═══════════════════════════════════════════════════════
    `);

    // Fermer la modal
    $('#js-validation-modal, #js-validation-overlay').remove();

    // Masquer le progress
    hideScannerProgress();

    // Envoyer les résultats à BlockRecovery
    sendResultsToBlockRecovery();

    // Générer et afficher le tableau HTML des résultats
    displayValidationResultsAsTable();

    // Afficher un message de performance si le cache a aidé
    if (cacheHits > 0) {
      showCachePerformanceMessage(cacheHits, totalPosts);
    }
  }

  /**
   * Envoyer les résultats de validation à BlockRecovery
   */
  function sendResultsToBlockRecovery() {
    log.info('Envoi des résultats à BlockRecovery...');

    $.ajax({
      url: diagnosticScannerData.ajaxurl,
      type: 'POST',
      data: {
        action: 'save_js_validation_results',
        nonce: diagnosticScannerData.nonce,
        results: JSON.stringify(validationResults)
      },
      success: function(response) {
        if (response.success) {
          log.info('Résultats enregistrés dans BlockRecovery:', response.data);

          // Afficher les informations de backup XML si disponibles
          if (response.data.backup) {
            showBackupInfo(response.data.backup);
          }

          // Afficher un lien vers BlockRecovery si des problèmes ont été détectés
          if (response.data.posts_with_issues > 0) {
            showBlockRecoveryLink(response.data.posts_with_issues);
          }
        } else {
          log.error('Erreur lors de l\'enregistrement:', response.data?.message);
        }
      },
      error: function(xhr, status, error) {
        log.error('Erreur AJAX lors de l\'enregistrement:', error);
      }
    });
  }

  /**
   * Afficher un message sur les gains de performance du cache
   */
  function showCachePerformanceMessage(cacheHits, totalPosts) {
    const percentage = Math.round(cacheHits / totalPosts * 100);
    const timeSaved = Math.round((cacheHits * 30) / 60); // Estimation: 30s par post

    const html = `
      <div class="scanner-cache-performance">
        <h3>⚡ Cache activé - Performance optimisée</h3>
        <p>
          <strong>${cacheHits} post(s)</strong> sur <strong>${totalPosts}</strong> (${percentage}%)
          ont été chargés depuis le cache, car ils n'ont pas été modifiés depuis le dernier scan.
        </p>
        <p class="time-saved">
          ⏱️ Temps économisé estimé: <strong>~${timeSaved} minute(s)</strong>
        </p>
      </div>
    `;

    $('#scanner-results-content').prepend(html);
  }

  /**
   * Afficher les informations de sauvegarde XML
   */
  function showBackupInfo(backup) {
    let html = '';

    if (!backup.success) {
      // Afficher l'erreur de backup
      html = `
        <div class="scanner-backup-info backup-error">
          <h3>⚠️ Erreur de sauvegarde</h3>
          <p><strong>Erreur:</strong> ${backup.error || 'Erreur inconnue'}</p>
        </div>
      `;
    } else if (backup.posts_count === 0) {
      // Aucun backup nécessaire
      html = `
        <div class="scanner-backup-info backup-none">
          <h3>✅ Aucune sauvegarde nécessaire</h3>
          <p>Félicitations ! Aucun post avec problème détecté.</p>
        </div>
      `;
    } else {
      // Backup généré avec succès
      const downloadButton = backup.url ? `
        <p>
          <a href="${backup.url}" class="button button-secondary" download="${backup.filename}" target="_blank">
            📥 Télécharger la sauvegarde XML
          </a>
        </p>
      ` : '';

      html = `
        <div class="scanner-backup-info backup-success">
          <h3>💾 Sauvegarde automatique générée</h3>
          <ul>
            <li><strong>Fichier:</strong> ${backup.filename || 'N/A'}</li>
            <li><strong>Posts sauvegardés:</strong> ${backup.posts_count || 0}</li>
            ${backup.size ? `<li><strong>Taille:</strong> ${formatBytes(backup.size)}</li>` : ''}
            ${backup.created_at ? `<li><strong>Créé le:</strong> ${backup.created_at}</li>` : ''}
          </ul>
          ${downloadButton}
          <p class="backup-note">
            💡 Cette sauvegarde contient tous les posts avec problèmes détectés, incluant leur contenu complet et métadonnées.
          </p>
        </div>
      `;
    }

    $('#scanner-results-content').prepend(html);
  }

  /**
   * Formater la taille des fichiers en octets
   */
  function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  }

  /**
   * Afficher un lien vers la page BlockRecovery
   */
  function showBlockRecoveryLink(postsCount) {
    const blockRecoveryUrl = '/wp-admin/admin.php?page=diagnostic_block_recovery';
    const html = `
      <div class="scanner-block-recovery-notice">
        <div>
          <strong>🔧 Récupération disponible</strong>
          <p>
            ${postsCount} post(s) avec des blocs invalides ont été détectés.
            Utilisez la page Récupération de Blocs pour les corriger automatiquement.
          </p>
        </div>
        <a href="${blockRecoveryUrl}" class="button button-primary">
          Ouvrir Récupération de Blocs →
        </a>
      </div>
    `;

    $('#scanner-results-content').prepend(html);
  }


  /**
   * Afficher les résultats au format tableau HTML
   */
  function displayValidationResultsAsTable() {
    log.info('Génération du tableau HTML des résultats');

    // Analyser les résultats pour construire le tableau
    const postsWithIssues = [];

    for (const postId in validationResults) {
      const result = validationResults[postId];
      const post = postsToValidate.find(p => p.id == postId);

      if (!post) continue;

      if (result.success && result.invalidBlocks && result.invalidBlocks.length > 0) {
        postsWithIssues.push({
          id: postId,
          title: post.title,
          invalidBlocks: result.invalidBlocks
        });
      }
    }

    log.info('Posts avec blocs invalides:', postsWithIssues.length);

    // Générer le HTML du tableau
    let html = '';

    if (postsWithIssues.length === 0) {
      html = `
        <div class="scanner-results-summary">
          <div class="scanner-summary-success">
            <h3>✅ Analyse terminée - Aucun problème détecté</h3>
            <p>Tous les blocs sont valides dans les ${postsToValidate.length} post(s) analysé(s).</p>
          </div>
        </div>
      `;
    } else {
      // Résumé
      const totalInvalidBlocks = postsWithIssues.reduce((sum, p) => sum + p.invalidBlocks.length, 0);

      html += `
        <div class="scanner-results-summary">
          <div class="scanner-summary-warning">
            <h3>⚠️ Problèmes détectés</h3>
            <ul>
              <li><strong>${postsWithIssues.length}</strong> post(s) avec des blocs en recovery mode</li>
              <li><strong>${totalInvalidBlocks}</strong> bloc(s) invalide(s) au total</li>
              <li><strong>${postsToValidate.length}</strong> post(s) analysé(s)</li>
            </ul>
          </div>
        </div>
      `;

      // Tableau des posts avec problèmes
      html += `
        <div class="scanner-results-issues">
          <h3>Posts avec blocs invalides</h3>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th>Post</th>
                <th>ID</th>
                <th>Nb de blocs invalides</th>
                <th>Issues</th>
              </tr>
            </thead>
            <tbody>
      `;

      // Lignes du tableau
      postsWithIssues.forEach(post => {
        const editUrl = `/wp-admin/post.php?post=${post.id}&action=edit`;
        const issuesList = post.invalidBlocks.map(block => {
          // Formater les messages de validation
          let validationMessages = '';

          if (block.validationIssues && block.validationIssues.length > 0) {
            // Debug: logger la structure des validationIssues
            log.info('validationIssues pour bloc', block.name, ':', block.validationIssues);

            const messages = block.validationIssues.map(issue => {
              // Si l'issue est une chaîne, la retourner directement
              if (typeof issue === 'string') {
                // Ignorer les chaînes qui sont "[object Object]"
                if (issue === '[object Object]') {
                  return null;
                }
                return issue;
              }

              // Si c'est un objet
              if (issue && typeof issue === 'object') {
                // Vérifier si message existe et n'est pas "[object Object]"
                if (issue.message && issue.message !== '[object Object]') {
                  return issue.message;
                }

                // Si le message est "[object Object]", ignorer et essayer d'extraire d'autres infos
                const parts = [];

                // Essayer d'extraire le code d'erreur
                if (issue.code && issue.code !== null) {
                  parts.push(`Code: ${issue.code}`);
                }

                // Essayer d'extraire des propriétés utiles
                if (issue.args !== undefined && issue.args !== null) {
                  try {
                    const argsStr = JSON.stringify(issue.args);
                    if (argsStr !== '{}' && argsStr !== '[]') {
                      parts.push(`Attributs: ${argsStr}`);
                    }
                  } catch (e) {
                    // Ignorer si la sérialisation échoue
                  }
                }

                if (issue.expected !== undefined) {
                  parts.push(`Attendu: ${issue.expected}`);
                }

                if (issue.actual !== undefined) {
                  parts.push(`Reçu: ${issue.actual}`);
                }

                // Si on a trouvé des informations utiles, les retourner
                if (parts.length > 0) {
                  return parts.join(', ');
                }

                // Sinon, retourner null pour indiquer qu'on n'a pas de détails
                return null;
              }

              // Cas par défaut
              return String(issue);
            }).filter(msg => msg && msg.trim() !== '' && msg !== '[object Object]'); // Filtrer les messages vides et [object Object]

            if (messages.length > 0) {
              validationMessages = `<br><small>${messages.join('<br>')}</small>`;
            } else {
              // Aucun message détaillé disponible, afficher un message générique utile
              validationMessages = `<br><small>Le contenu du bloc ne correspond pas à sa définition actuelle</small>`;
            }
          }

          return `<div class="scanner-issue-item">
            <span class="issue-badge">
              <strong>${block.name}</strong>
              <br><small>⚠️ Bloc en mode récupération</small>
              ${validationMessages}
            </span>
          </div>`;
        }).join('');

        html += `
          <tr data-post-id="${post.id}" data-post-title="${post.title.replace(/"/g, '&quot;')}">
            <td>
              <a href="${editUrl}" target="_blank" class="scanner-post-edit-link">
                <strong>${post.title}</strong>
                <span class="scanner-edit-icon">✏️</span>
              </a>
            </td>
            <td>#${post.id}</td>
            <td>${post.invalidBlocks.length}</td>
            <td class="scanner-issues-cell">${issuesList}</td>
          </tr>
        `;
      });

      html += `
            </tbody>
          </table>
        </div>
      `;
    }

    // Afficher le tableau
    $('#scanner-results').show();
    $('#scanner-results-content').html(html);

    // Log dans la console pour les détails
    log.info('Résultats détaillés:', validationResults);
  }


  // Initialiser au chargement de la page
  $(document).ready(function() {
    initJsValidation();
  });

})(jQuery);
