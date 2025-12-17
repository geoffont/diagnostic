/**
 * Module de Filtrage du Scanner - Gestion des filtres par type d'issue
 *
 * Ce module gère le système de filtrage interactif permettant de
 * filtrer les résultats par type de problème en cliquant directement
 * sur les types dans le tableau récapitulatif.
 *
 * @file        scanner-filters.js
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-12
 * @modified    2025-09-12
 *
 * @responsibilities:
 * - Gestion des filtres par type d'issue
 * - Interface cliquable sur les types de problèmes
 * - Filtrage des données en temps réel
 * - Mise à jour des statuts et affichages
 * - Coordination avec la pagination
 *
 * @dependencies:
 * - DOM API
 * - scanner-pagination.js (affichage des résultats)
 * - scanner-core.js (utilitaires)
 *
 * @related_files:
 * - scanner-pagination.js (affichage)
 * - scanner-core.js (logique principale)
 * - scanner-interface.js (orchestration)
 */

window.ScannerFilters = (function() {
  'use strict';

  // Variables privées
  let originalPostsData = []; // Données originales sans filtre
  let currentFilterType = null; // Type de filtre actif

  /**
   * Initialiser les filtres par type d'issue
   */
  function initialize() {
    // Sauvegarder les données originales
    const paginationData = document.querySelector('.scanner-pagination-data');
    if (paginationData) {
      try {
        originalPostsData = JSON.parse(atob(paginationData.dataset.postsData));
      } catch (e) {
        console.error('Erreur lors du décodage des données:', e);
        return;
      }
    } else {
      console.error('Élément .scanner-pagination-data non trouvé');
      return;
    }

    // Ajouter les gestionnaires de clic aux lignes de types d'issues
    const filterRows = document.querySelectorAll('.scanner-filter-row');
    
    filterRows.forEach((row, index) => {
      row.addEventListener('click', function() {
        const issueType = this.dataset.issueType;
        toggleIssueTypeFilter(issueType);
      });

      // Effet visuel au survol
      row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f0f8ff';
      });
      
      row.addEventListener('mouseleave', function() {
        if (this.dataset.issueType !== currentFilterType) {
          this.style.backgroundColor = '';
        }
      });
    });

    // Gestionnaire pour le bouton "Effacer tous les filtres"
    const clearAllBtn = document.getElementById('clear-all-filters');
    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', clearAllFilters);
    }
  }

  /**
   * Basculer le filtre par type d'issue
   */
  function toggleIssueTypeFilter(issueType) {
    if (currentFilterType === issueType) {
      // Déjà filtré sur ce type, on supprime le filtre
      clearAllFilters();
    } else {
      // Appliquer le filtre sur ce type
      applyIssueTypeFilter(issueType);
    }
  }

  /**
   * Appliquer le filtre par type d'issue
   */
  function applyIssueTypeFilter(issueType) {
    currentFilterType = issueType;

    // Filtrer les données
    const filteredData = filterPostsByIssueType(originalPostsData, issueType);

    // Mettre à jour l'affichage
    updateDisplayWithFilteredData(filteredData);
    updateFilterStatusForType(issueType, filteredData.length, originalPostsData.length);
    updateIssueTypeRowStyles(issueType);
    updateTableHeaderForFilter(issueType);

    // Afficher le bouton pour effacer les filtres
    const clearAllBtn = document.getElementById('clear-all-filters');
    if (clearAllBtn) {
      clearAllBtn.style.display = 'inline-block';
    }
  }

  /**
   * Effacer tous les filtres
   */
  function clearAllFilters() {
    currentFilterType = null;

    // Restaurer les données originales
    if (originalPostsData.length > 0) {
      updateDisplayWithFilteredData(originalPostsData);
      updateFilterStatusClear();
      updateIssueTypeRowStyles(null);
      updateTableHeaderForFilter(null);
    }

    // Masquer le bouton pour effacer les filtres
    const clearAllBtn = document.getElementById('clear-all-filters');
    if (clearAllBtn) {
      clearAllBtn.style.display = 'none';
    }
  }

  /**
   * Filtrer les posts par type d'issue spécifique
   */
  function filterPostsByIssueType(posts, issueType) {
    return posts.filter(post => {
      if (!post.issues || post.issues.length === 0) {
        return false;
      }

      // Vérifier si le post a au moins une issue du type spécifié
      return post.issues.some(issue => issue.type === issueType);
    });
  }

  /**
   * Mettre à jour l'affichage avec les données filtrées
   */
  function updateDisplayWithFilteredData(filteredData) {
    const postsPerPage = 20;
    
    // Mettre à jour les données de pagination
    const paginationData = document.querySelector('.scanner-pagination-data');
    if (paginationData) {
      // Encoder les nouvelles données
      paginationData.dataset.postsData = btoa(JSON.stringify(filteredData));
      paginationData.dataset.totalPosts = filteredData.length;
      paginationData.dataset.currentPage = 1;
    }

    // Réafficher la première page avec les données filtrées
    if (window.ScannerPagination) {
      window.ScannerPagination.showPage(1, filteredData, postsPerPage);
    }
  }

  /**
   * Mettre à jour le statut des filtres pour un type spécifique
   */
  function updateFilterStatusForType(issueType, filteredCount, originalCount) {
    const statusDiv = document.getElementById('filter-status');
    if (!statusDiv) return;

    const displayName = getIssueTypeDisplayName(issueType);
    
    statusDiv.innerHTML = `<strong>🔍 Filtre actif:</strong> ${displayName}<br>` +
                         `<strong>Résultats:</strong> ${filteredCount} posts sur ${originalCount} posts avec problèmes`;
    statusDiv.style.color = '#0073aa';
    statusDiv.style.fontWeight = 'bold';
  }

  /**
   * Mettre à jour le statut quand les filtres sont effacés
   */
  function updateFilterStatusClear() {
    const statusDiv = document.getElementById('filter-status');
    if (!statusDiv) return;

    statusDiv.textContent = 'Aucun filtre appliqué - Affichage de tous les résultats';
    statusDiv.style.color = '#666';
    statusDiv.style.fontWeight = 'normal';
  }

  /**
   * Mettre à jour les styles des lignes de types d'issues
   */
  function updateIssueTypeRowStyles(activeType) {
    const filterRows = document.querySelectorAll('.scanner-filter-row');
    filterRows.forEach(row => {
      if (row.dataset.issueType === activeType) {
        row.style.backgroundColor = '#0073aa';
        row.style.color = 'white';
        const icon = row.querySelector('.dashicons-filter');
        if (icon) {
          icon.style.color = 'white';
        }
      } else {
        row.style.backgroundColor = '';
        row.style.color = '';
        const icon = row.querySelector('.dashicons-filter');
        if (icon) {
          icon.style.color = '#0073aa';
        }
      }
    });
  }

  /**
   * Mettre à jour l'en-tête du tableau selon le filtre
   */
  function updateTableHeaderForFilter(filterType) {
    const tableHeader = document.querySelector('#scanner-posts-table thead tr');
    if (!tableHeader) return;

    const problemsHeader = tableHeader.children[2]; // Colonne "Nb problèmes"
    const detailsHeader = tableHeader.children[3];  // Colonne "Détails des problèmes"

    if (filterType) {
      const displayName = getIssueTypeDisplayName(filterType);
      problemsHeader.innerHTML = 'Nb ' + displayName.toLowerCase();
      detailsHeader.innerHTML = displayName + ' <span style="color: #0073aa; font-size: 12px;">🔍</span>';
    } else {
      problemsHeader.innerHTML = 'Nb problèmes';
      detailsHeader.innerHTML = 'Détails des problèmes';
    }
  }

  /**
   * Obtenir le nom d'affichage d'un type d'issue
   */
  function getIssueTypeDisplayName(type) {
    const displayNames = {
      'BLOCK_RECOVERY_MODE': 'Blocs en mode récupération',
      'BLOCK_CONVERT_TO_HTML': 'Blocs à convertir en HTML',
      'CREATE_BLOCK_UNREGISTERED': 'Blocs non enregistrés',
      'BLOCK_MISSING': 'Blocs manquants',
      'BLOCK_ERROR': 'Blocs avec erreurs'
    };

    return displayNames[type] || type;
  }

  // API publique
  return {
    initialize: initialize,
    getCurrentFilter: function() { return currentFilterType; },
    clearAllFilters: clearAllFilters,
    getOriginalData: function() { return originalPostsData; }
  };
})();