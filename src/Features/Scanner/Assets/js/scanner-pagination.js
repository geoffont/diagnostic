/**
 * Module de Pagination du Scanner - Gestion de l'affichage paginé
 *
 * Ce module gère l'affichage paginé des résultats du scanner,
 * incluant la navigation entre pages, la génération du HTML
 * des tableaux et la mise à jour des informations de pagination.
 *
 * @file        scanner-pagination.js
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
 * - Gestion de la pagination des résultats
 * - Génération du HTML des tableaux
 * - Navigation entre les pages
 * - Mise à jour des informations de pagination
 * - Gestion des boutons de navigation
 *
 * @dependencies:
 * - DOM API
 * - scanner-core.js (utilitaires)
 * - scanner-filters.js (données filtrées)
 *
 * @related_files:
 * - scanner-core.js (logique principale)
 * - scanner-filters.js (filtrage)
 * - scanner-interface.js (orchestration)
 */

window.ScannerPagination = (function() {
  'use strict';

  /**
   * Initialiser la pagination
   */
  function initialize() {
    // Récupérer les données de pagination
    const paginationData = document.querySelector('.scanner-pagination-data');
    if (!paginationData) {
      return;
    }
    
    const totalPosts = parseInt(paginationData.dataset.totalPosts);
    const postsPerPage = parseInt(paginationData.dataset.postsPerPage);
    const currentPage = parseInt(paginationData.dataset.currentPage);
    
    let postsData = [];
    try {
      postsData = JSON.parse(atob(paginationData.dataset.postsData));
    } catch (e) {
      console.error('Erreur lors du décodage des données de posts:', e);
      return;
    }
    
    // Approche plus simple : ajouter directement les gestionnaires aux boutons
    const paginationButtons = document.querySelectorAll('.scanner-pagination-btn');
    
    paginationButtons.forEach(function(btn, index) {
      // Supprimer les anciens gestionnaires
      btn.onclick = null;
      
      // Ajouter le nouveau gestionnaire
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetPage = parseInt(this.dataset.page);
        const totalPages = Math.ceil(totalPosts / postsPerPage);
        
        if (targetPage >= 1 && targetPage <= totalPages && !this.disabled) {
          showPage(targetPage, postsData, postsPerPage);
        }
      });
    });

    // Initialiser le fallback de pagination si nécessaire
    initializePaginationFallback();
  }

  /**
   * Initialiser le fallback de pagination
   */
  function initializePaginationFallback() {
    document.addEventListener('click', function(e) {
      if (e.target && e.target.classList.contains('scanner-pagination-btn')) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetPage = parseInt(e.target.dataset.page);
        if (targetPage) {
          // Essayer d'utiliser la fonction complète d'abord
          const paginationData = document.querySelector('.scanner-pagination-data');
          if (paginationData) {
            try {
              const postsData = JSON.parse(atob(paginationData.dataset.postsData));
              const postsPerPage = parseInt(paginationData.dataset.postsPerPage);
              showPage(targetPage, postsData, postsPerPage);
            } catch (e) {
              console.error('Erreur pagination fallback:', e);
            }
          }
        }
      }
    });
  }

  /**
   * Afficher une page spécifique
   */
  function showPage(pageNumber, postsData, postsPerPage) {
    const totalPages = Math.ceil(postsData.length / postsPerPage);
    
    // Validation de la page
    if (pageNumber < 1 || pageNumber > totalPages) {
      return;
    }
    
    // Calcul de l'index de début et de fin
    const startIndex = (pageNumber - 1) * postsPerPage;
    const endIndex = Math.min(startIndex + postsPerPage, postsData.length);
    const currentPagePosts = postsData.slice(startIndex, endIndex);
    
    // Cibler spécifiquement le tableau des posts (pas celui des types d'issues)
    const tableBody = document.querySelector('#scanner-posts-table tbody');
    if (tableBody) {
      const newHTML = generatePostsTableRows(currentPagePosts);
      tableBody.innerHTML = newHTML;
    }
    
    // Mettre à jour les informations de pagination
    updatePaginationInfo(pageNumber, totalPages, startIndex + 1, endIndex, postsData.length);
    
    // Mettre à jour les boutons de pagination
    updatePaginationButtons(pageNumber, totalPages, postsData, postsPerPage);
  }

  /**
   * Générer les lignes HTML du tableau des posts
   */
  function generatePostsTableRows(posts) {
    let rows = '';
    posts.forEach(function(post) {
      const issues = post.issues || [];
      
      let issuesList = '';
      issues.forEach(function(issue) {
        if (issue.type && issue.message) {
          // Si un filtre est actif, n'afficher que les issues du type filtré
          const currentFilterType = window.ScannerFilters ? window.ScannerFilters.getCurrentFilter() : null;
          if (currentFilterType === null || issue.type === currentFilterType) {
            // Ajouter une classe pour mettre en évidence le type filtré
            const highlightClass = (currentFilterType && issue.type === currentFilterType) ? 
              ' style="background-color: #e7f3ff; border-left: 3px solid #0073aa; padding-left: 5px;"' : '';
            
            const escapeHtml = window.ScannerCore ? window.ScannerCore.escapeHtml : function(text) { return text; };
            issuesList += '<span class="scanner-issue-badge"' + highlightClass + '>' + 
              escapeHtml(issue.type) + ': ' + escapeHtml(issue.message) + 
              '</span><br>';
          }
        }
      });
      
      const escapeHtml = window.ScannerCore ? window.ScannerCore.escapeHtml : function(text) { return text; };
      const postTitle = escapeHtml(post.title || 'Sans titre');
      const postId = parseInt(post.id || 0);
      const editUrl = post.editUrl || '';
      
      const postLink = editUrl ? 
        '<a href="' + escapeHtml(editUrl) + '" target="_blank" class="scanner-post-edit-link">' +
        '<strong>' + postTitle + '</strong> <span class="scanner-edit-icon">✏️</span></a>' :
        '<strong>' + postTitle + '</strong>';
      
      // Compter seulement les issues du type filtré si un filtre est actif
      let issueCount = issues.length;
      const currentFilterType = window.ScannerFilters ? window.ScannerFilters.getCurrentFilter() : null;
      if (currentFilterType !== null) {
        issueCount = issues.filter(issue => issue.type === currentFilterType).length;
      }
      
      rows += '<tr>' +
        '<td>' + postLink + '</td>' +
        '<td>#' + postId + '</td>' +
        '<td>' + issueCount + '</td>' +
        '<td class="scanner-issues-cell">' + issuesList + '</td>' +
        '</tr>';
    });
    
    return rows;
  }

  /**
   * Mettre à jour les informations de pagination
   */
  function updatePaginationInfo(currentPage, totalPages, startItem, endItem, totalItems) {
    const paginationInfo = document.querySelector('.scanner-pagination-info span');
    if (paginationInfo) {
      paginationInfo.textContent = 'Affichage de ' + startItem + '-' + endItem + ' sur ' + totalItems + ' posts avec problèmes';
    }
    
    const paginationPages = document.querySelector('.scanner-pagination-pages');
    if (paginationPages) {
      paginationPages.textContent = 'Page ' + currentPage + ' sur ' + totalPages;
    }
  }

  /**
   * Mettre à jour les boutons de pagination
   */
  function updatePaginationButtons(currentPage, totalPages, postsData, postsPerPage) {
    // Sélectionner les boutons par classe et position plus fiable
    const paginationControls = document.querySelector('.scanner-pagination-controls');
    if (!paginationControls) return;
    
    const prevBtn = paginationControls.querySelector('.scanner-pagination-btn:first-child');
    const nextBtn = paginationControls.querySelector('.scanner-pagination-btn:last-child');
    
    if (prevBtn) {
      prevBtn.disabled = currentPage <= 1;
      prevBtn.dataset.page = Math.max(1, currentPage - 1);
      
      // Supprimer l'ancien gestionnaire et ajouter le nouveau
      prevBtn.onclick = null;
      if (currentPage > 1) {
        prevBtn.addEventListener('click', function(e) {
          e.preventDefault();
          showPage(currentPage - 1, postsData, postsPerPage);
        });
      }
    }
    
    if (nextBtn) {
      nextBtn.disabled = currentPage >= totalPages;
      nextBtn.dataset.page = Math.min(totalPages, currentPage + 1);
      
      // Supprimer l'ancien gestionnaire et ajouter le nouveau
      nextBtn.onclick = null;
      if (currentPage < totalPages) {
        nextBtn.addEventListener('click', function(e) {
          e.preventDefault();
          showPage(currentPage + 1, postsData, postsPerPage);
        });
      }
    }
  }

  // API publique
  return {
    initialize: initialize,
    showPage: showPage,
    generateRows: generatePostsTableRows
  };
})();