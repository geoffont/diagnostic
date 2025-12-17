/**
 * Module Core du Scanner - Logique principale
 *
 * Ce module gère la communication AJAX avec le serveur et coordonne
 * l'affichage des résultats du scanner PHP.
 *
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-12-02
 *
 * @responsibilities:
 * - Communication AJAX avec le serveur
 * - Initialisation des données diagnostiques
 * - Affichage des résultats du scanner PHP
 * - Coordination avec les modules Pagination et Filters
 *
 * @dependencies:
 * - diagnosticScannerData (variables localisées par WordPress)
 * - window.ScannerPagination (module optionnel)
 * - window.ScannerFilters (module optionnel)
 *
 * @related_files:
 * - ../Feature.php (backend AJAX)
 * - scanner-pagination.js (affichage paginé)
 * - scanner-filters.js (filtrage des données)
 * - scanner-interface.js (orchestration)
 */

window.ScannerCore = (function() {
  'use strict';

  /**
   * Système de logging conditionnel
   * Les logs ne s'affichent que si window.DEBUG_MODE est true
   */
  const log = {
    info: function(...args) {
      if (window.DEBUG_MODE) {
        console.log('[Scanner Core]', ...args);
      }
    },
    warn: function(...args) {
      if (window.DEBUG_MODE) {
        console.warn('[Scanner Core]', ...args);
      }
    },
    error: function(...args) {
      if (window.DEBUG_MODE) {
        console.error('[Scanner Core]', ...args);
      }
    }
  };

  /**
   * Initialiser les données diagnostiques
   *
   * Crée un objet fallback minimal si diagnosticScannerData n'est pas
   * défini par wp_localize_script. Ceci permet au scanner de fonctionner
   * même en cas d'échec du chargement des données WordPress.
   *
   * @return {void}
   */
  function initializeDiagnosticData() {
    if (typeof diagnosticScannerData === 'undefined') {
      window.diagnosticScannerData = {
        nonce: '',
        ajaxurl: '/wp-admin/admin-ajax.php',
        strings: {
          scanInProgress: 'Analyse en cours...',
          scanComplete: 'Analyse terminée',
          scanError: 'Erreur lors de l\'analyse'
        }
      };
      log.warn('diagnosticScannerData n\'était pas défini, utilisation des valeurs par défaut');
    }
  }

  /**
   * Initialiser le module Core
   *
   * Cette fonction doit être appelée au chargement de la page.
   * Elle initialise les données diagnostiques et prépare le module.
   *
   * @return {void}
   */
  function initialize() {
    initializeDiagnosticData();
    log.info('Scanner Core initialisé');
  }

  /**
   * Exécuter l'analyse complète via le scanner PHP
   *
   * Cette fonction envoie une requête AJAX au serveur pour lancer
   * l'analyse complète de tous les posts. Elle gère l'affichage
   * de la progression et des résultats.
   *
   * Note: Cette fonction n'est plus utilisée par défaut, le scanner
   * JavaScript (scanner-js-validation.js) est maintenant le mode
   * principal de validation.
   *
   * @async
   * @return {Promise<void>}
   */
  async function runScannerValidator() {
    const progressDiv = document.getElementById('scanner-progress');
    const resultsDiv = document.getElementById('scanner-results');
    const button = document.getElementById('run-scanner-validator');

    // Afficher la progression et désactiver le bouton
    if (progressDiv) progressDiv.style.display = 'block';
    if (resultsDiv) resultsDiv.style.display = 'none';
    if (button) button.disabled = true;

    // Configuration de l'analyse
    const config = {
      adminMode: true,
      postType: 'tous',    // Analyser tous les types de posts
      limit: -1,           // Pas de limite
      verbose: true        // Mode détaillé
    };

    try {
      updateProgress('Analyse en cours...');

      // Envoyer la requête AJAX au serveur
      const response = await fetch(diagnosticScannerData.ajaxurl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'run_scanner_validator',
          nonce: diagnosticScannerData.nonce,
          config: JSON.stringify(config)
        })
      });

      // Vérifier le statut HTTP
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      // Vérifier que la réponse est bien du JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        throw new Error(`Réponse non-JSON reçue. Type: ${contentType}`);
      }

      // Parser la réponse JSON
      const data = await response.json();
      log.info('Réponse du serveur:', data);

      if (data.success) {
        displayResults(data.data);
      } else {
        throw new Error(data.data || 'Erreur inconnue');
      }

    } catch (error) {
      log.error('Erreur lors du scan:', error);

      // Afficher un message d'erreur à l'utilisateur
      const errorMessage = error instanceof Error
        ? error.message
        : JSON.stringify(error);

      alert(`Erreur lors de l'analyse:\n\n${errorMessage}`);

    } finally {
      // Réinitialiser l'interface
      if (progressDiv) progressDiv.style.display = 'none';
      if (button) button.disabled = false;
    }
  }

  /**
   * Afficher les résultats du scanner
   *
   * Cette fonction injecte le HTML généré par le serveur dans la page
   * et initialise les modules de pagination et de filtrage.
   *
   * @param {Object} data - Données retournées par le serveur
   * @param {string} data.html - HTML des résultats à afficher
   * @return {void}
   */
  function displayResults(data) {
    const resultsDiv = document.getElementById('scanner-results');
    const contentDiv = document.getElementById('scanner-results-content');

    // Injecter le HTML des résultats
    if (contentDiv && data.html) {
      contentDiv.innerHTML = data.html;

      // Attendre un peu que le DOM soit mis à jour, puis initialiser les modules
      setTimeout(function() {
        // Initialiser le module de pagination s'il est disponible
        if (window.ScannerPagination && typeof window.ScannerPagination.initialize === 'function') {
          window.ScannerPagination.initialize();
        }

        // Initialiser le module de filtrage s'il est disponible
        if (window.ScannerFilters && typeof window.ScannerFilters.initialize === 'function') {
          window.ScannerFilters.initialize();
        }
      }, 200);
    }

    // Afficher la section des résultats
    if (resultsDiv) resultsDiv.style.display = 'block';
  }

  /**
   * Mettre à jour le message de progression
   *
   * Affiche un message dans la zone de progression pendant l'analyse.
   *
   * @param {string} message - Message à afficher
   * @return {void}
   */
  function updateProgress(message) {
    const progressDiv = document.getElementById('scanner-progress');
    if (progressDiv) {
      progressDiv.textContent = message;
    }
  }

  /**
   * Fonction utilitaire pour échapper le HTML
   *
   * Convertit les caractères spéciaux en entités HTML pour éviter
   * les injections XSS.
   *
   * @param {string} text - Texte à échapper
   * @return {string} Texte échappé
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========================================================================
  // API Publique
  // ========================================================================

  return {
    /**
     * Initialiser le module Core
     * @see initialize
     */
    initialize: initialize,

    /**
     * Lancer l'analyse via le scanner PHP
     * @see runScannerValidator
     */
    runValidator: runScannerValidator,

    /**
     * Fonction utilitaire pour échapper le HTML
     * @see escapeHtml
     */
    escapeHtml: escapeHtml
  };

})();
