/**
 * Interface JavaScript principale pour le Scanner de blocs Gutenberg
 *
 * Ce fichier coordonne l'ensemble des modules du scanner et gère
 * l'initialisation de l'interface utilisateur. Il orchestre les
 * interactions entre les modules Core, Pagination et Filters.
 *
 * @file        scanner-interface.js
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     3.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-12-02
 *
 * @responsibilities:
 * - Orchestration des modules du scanner
 * - Initialisation de l'interface utilisateur
 * - Coordination entre Core, Pagination et Filters
 * - Gestion des événements principaux
 * - Point d'entrée de l'application
 *
 * @dependencies:
 * - scanner-core.js (logique principale)
 * - scanner-pagination.js (affichage paginé)
 * - scanner-filters.js (système de filtrage)
 * - scanner-js-validation.js (validation JavaScript)
 * - jQuery
 * - DOM API
 *
 * @related_files:
 * - scanner-core.js (communication AJAX)
 * - scanner-pagination.js (gestion des pages)
 * - scanner-filters.js (filtrage des données)
 * - scanner-js-validation.js (validation JS native)
 * - ../UI/Screens/ScannerScreen.php (backend)
 * - Feature.php (enregistrement des assets)
 * - scanner-interface.css (styles)
 *
 * @global {Object} diagnosticScannerData - Données transmises par wp_localize_script
 * @global {string} diagnosticScannerData.nonce - Nonce de sécurité WordPress
 * @global {string} diagnosticScannerData.ajaxurl - URL AJAX WordPress
 * @global {Object} diagnosticScannerData.strings - Chaînes traduites
 *
 * @global {Object} window.ScannerCore - Module de logique principale
 * @global {Object} window.ScannerPagination - Module de pagination
 * @global {Object} window.ScannerFilters - Module de filtrage
 * @global {Object} window.ScannerJsValidation - Module de validation JavaScript
 */

(function($) {
  'use strict';

  /**
   * Initialisation principale de l'interface au chargement du DOM
   */
  document.addEventListener('DOMContentLoaded', function() {
    initializeInterface();
    checkModulesStatus();

    // Exposer l'interface pour le debugging si nécessaire
    window.ScannerInterface = {
      initialize: initializeInterface,
      checkModules: checkModulesStatus
    };
  });

  /**
   * Initialiser l'interface du scanner
   *
   * Cette fonction orchestre l'initialisation de tous les modules
   * et configure les gestionnaires d'événements principaux.
   *
   * @return {void}
   */
  function initializeInterface() {
    // Initialiser les données diagnostiques avec fallback
    initializeDiagnosticData();

    // Vérifier la disponibilité du module Core (requis)
    if (!window.ScannerCore) {
      if (window.DEBUG_MODE) {
        console.error('[Scanner Interface] Module ScannerCore non trouvé. Assurez-vous que scanner-core.js est chargé.');
      }
      return;
    }

    // Initialiser le module Core
    window.ScannerCore.initialize();

    // Initialiser le gestionnaire du bouton principal
    initializeScannerButton();

    // Initialiser le fallback de pagination si le module n'est pas disponible
    if (!window.ScannerPagination) {
      if (window.DEBUG_MODE) {
        console.warn('[Scanner Interface] Module ScannerPagination non trouvé. Initialisation du fallback.');
      }
      initializePaginationFallback();
    }
  }

  /**
   * Initialiser les données diagnostiques si elles ne sont pas disponibles
   *
   * Crée un objet fallback minimal si wp_localize_script a échoué.
   *
   * @return {void}
   */
  function initializeDiagnosticData() {
    if (typeof diagnosticScannerData === 'undefined') {
      // Fallback minimal si wp_localize_script a échoué
      window.diagnosticScannerData = {
        nonce: '',
        ajaxurl: '/wp-admin/admin-ajax.php',
        strings: {
          scanInProgress: 'Analyse en cours...',
          scanComplete: 'Analyse terminée',
          scanError: 'Erreur lors de l\'analyse'
        }
      };

      if (window.DEBUG_MODE) {
        console.warn('[Scanner Interface] diagnosticScannerData non défini, utilisation du fallback.');
      }
    }
  }

  /**
   * Initialiser le gestionnaire d'événement du bouton principal
   *
   * Le bouton principal lance maintenant la validation JavaScript
   * via le module ScannerJsValidation au lieu du scanner PHP.
   *
   * @return {void}
   */
  function initializeScannerButton() {
    const scannerButton = document.getElementById('run-scanner-validator');

    if (!scannerButton) {
      if (window.DEBUG_MODE) {
        console.error('[Scanner Interface] Bouton run-scanner-validator non trouvé.');
      }
      return;
    }

    // Le comportement du bouton est géré par scanner-js-validation.js
    // Ce module remplace automatiquement le comportement du bouton
    // pour utiliser la validation JavaScript native de WordPress.

    if (window.DEBUG_MODE) {
      console.log('[Scanner Interface] Bouton principal initialisé (géré par scanner-js-validation.js).');
    }
  }

  /**
   * Initialiser le fallback de pagination
   *
   * Ce fallback est utilisé uniquement si le module ScannerPagination
   * n'est pas disponible. Il délègue au module si possible.
   *
   * @return {void}
   */
  function initializePaginationFallback() {
    document.addEventListener('click', function(e) {
      // Vérifier si c'est un bouton de pagination
      if (!e.target || !e.target.classList.contains('scanner-pagination-btn')) {
        return;
      }

      e.preventDefault();
      e.stopPropagation();

      const targetPage = parseInt(e.target.dataset.page);
      if (!targetPage) {
        return;
      }

      // Récupérer les données de pagination
      const paginationData = document.querySelector('.scanner-pagination-data');
      if (!paginationData) {
        return;
      }

      try {
        const postsData = JSON.parse(atob(paginationData.dataset.postsData));
        const postsPerPage = parseInt(paginationData.dataset.postsPerPage);

        // Essayer d'utiliser le module ScannerPagination s'il est disponible
        if (window.ScannerPagination && typeof window.ScannerPagination.showPage === 'function') {
          window.ScannerPagination.showPage(targetPage, postsData, postsPerPage);
        } else if (window.DEBUG_MODE) {
          console.warn('[Scanner Interface] ScannerPagination.showPage() non disponible.');
        }
      } catch (error) {
        if (window.DEBUG_MODE) {
          console.error('[Scanner Interface] Erreur pagination fallback:', error);
        }
      }
    });
  }

  /**
   * Vérifier l'état des modules et afficher des avertissements si nécessaire
   *
   * Cette fonction de diagnostic vérifie la présence de tous les modules
   * requis et optionnels, et affiche des avertissements en mode debug.
   *
   * @return {void}
   */
  function checkModulesStatus() {
    // Ne vérifier que si le mode debug est activé
    if (!window.DEBUG_MODE) {
      return;
    }

    const modules = {
      'ScannerCore': { file: 'scanner-core.js', required: true },
      'ScannerPagination': { file: 'scanner-pagination.js', required: false },
      'ScannerFilters': { file: 'scanner-filters.js', required: false },
      'ScannerJsValidation': { file: 'scanner-js-validation.js', required: false }
    };

    const missingModules = [];
    const missingRequired = [];

    Object.keys(modules).forEach(moduleName => {
      if (!window[moduleName]) {
        const moduleInfo = modules[moduleName];
        missingModules.push(`${moduleName} (${moduleInfo.file})`);

        if (moduleInfo.required) {
          missingRequired.push(moduleName);
        }
      }
    });

    if (missingRequired.length > 0) {
      console.error('[Scanner Interface] Modules requis manquants:', missingRequired.join(', '));
      console.error('[Scanner Interface] Le scanner ne fonctionnera pas correctement.');
    }

    if (missingModules.length > 0) {
      console.warn('[Scanner Interface] Modules manquants:', missingModules.join(', '));
      console.warn('[Scanner Interface] Certaines fonctionnalités peuvent être limitées.');
    } else {
      console.log('[Scanner Interface] Tous les modules sont chargés correctement.');
    }
  }

})(jQuery);
