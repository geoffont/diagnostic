/**
 * Block Recovery Advanced - Interface principale de récupération
 *
 * Ce fichier gère l'interface JavaScript pour la récupération des blocs Gutenberg
 * en mode recovery. Il orchestre la récupération batch via iframes, la validation
 * automatique, les filtres, et la mise à jour de l'UI en temps réel.
 *
 * @package     Company\Diagnostic\Features\BlockRecovery
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       2.0.0
 * @created     2025-10-21
 * @modified    2025-10-22
 *
 * @responsibilities:
 * - Gestion de l'interface de récupération batch
 * - Récupération multiple via iframes (taille batch ajustable)
 * - Validation automatique après récupération réussie
 * - Système de filtrage par type de bloc
 * - Pagination des résultats
 * - Affichage de la progression et estimation du temps
 * - Communication avec gutenberg-recovery.js via postMessage
 *
 * @configuration:
 * RECOVERY_BATCH_SIZE (ligne ~65) : Nombre de posts traités en parallèle
 *   - 1 : Séquentiel, 100% fiable (RECOMMANDÉ) ⭐
 *   - 2+ : Plus rapide mais risque d'erreurs de validation
 *
 * @note: Le traitement parallèle (>1) peut causer des problèmes :
 * - Messages postMessage perdus
 * - Validations AJAX échouées
 * - Posts marqués comme validés alors qu'ils ne le sont pas
 * → TOUJOURS tester avec 1 d'abord !
 *
 * @dependencies:
 * - Modal de progression avec statistiques en temps réel
 * - Communication avec les iframes via postMessage
 * - Mise à jour dynamique du tableau et des statuts
 *
 * @dependencies:
 * - jQuery
 * - blockRecoveryConfig (variables globales localisées)
 * - gutenberg-recovery.js (script exécuté dans les iframes)
 * - WordPress AJAX API
 *
 * @global_variables:
 * - blockRecoveryConfig.ajaxUrl : URL pour les requêtes AJAX
 * - blockRecoveryConfig.nonce : Nonce de sécurité
 * - blockRecoveryConfig.restUrl : URL de base REST API
 * - blockRecoveryConfig.restNonce : Nonce REST API
 *
 * @related_files:
 * - gutenberg-recovery.js (récupération dans éditeur)
 * - ../../Feature.php (configuration AJAX/REST)
 * - ../../UI/Screens/BlockRecoveryScreen.php (HTML de base)
 * - block-recovery.css (styles)
 *
 * @workflow:
 * 1. Utilisateur filtre par bloc et clique "Récupération Multiple"
 * 2. Vérification ≥2 validations (sécurité)
 * 3. Ouverture de 8 iframes en parallèle
 * 4. Chaque iframe charge Gutenberg avec gutenberg-recovery.js
 * 5. Réception postMessage de succès/échec
 * 6. Appel AJAX pour marquer comme validé si succès
 * 7. Mise à jour UI en temps réel
 * 8. Rafraîchissement page à la fin
 */

(function($) {
  'use strict';

  let currentFilter = '';
  let currentPage = 1;
  let itemsPerPage = 20;
  let totalItems = 0;
  let totalPages = 1;
  let showingOnlyUnvalidated = false;

  // Configuration du batch de récupération
  // IMPORTANT : Laisser à 1 pour garantir 100% de fiabilité
  // Le traitement séquentiel (1 par 1) est plus lent mais parfaitement fiable
  const RECOVERY_BATCH_SIZE = 1;

  $(document).ready(function() {
    initializeFilters();
    initializeSingleRecovery();
    initializeValidation();
    initializeMassRecovery();
    initializePagination();
    initializeRefreshButton();
    initializeResetValidations();
    updateMassRecoveryButton();
    applyPagination();
  });

  /**
   * Marquer un post comme validé via AJAX
   */
  function markPostAsValidated(postId, blockName, callback) {
    $.ajax({
      url: blockRecoveryConfig.ajaxUrl,
      type: 'POST',
      data: {
        action: 'block_recovery_validate',
        nonce: blockRecoveryConfig.nonce,
        post_id: postId,
        block_name: blockName
      },
      timeout: 5000,
      success: function(response) {
        if (response.success) {
          // Mettre à jour la ligne dans le DOM immédiatement
          const $row = $('.block-row[data-post-id="' + postId + '"][data-block-name="' + blockName + '"]');
          if ($row.length > 0) {
            $row.attr('data-is-validated', '1');
            updateRowValidationStatus($row, true);
          }
          
          if (callback) callback(true);
        } else {
          if (callback) callback(false);
        }
      },
      error: function(xhr, status, error) {
        if (callback) callback(false);
      }
    });
  }

  /**
   * Initialiser le système de filtrage
   */
  function initializeFilters() {
    $('#filter-blocks-btn').on('click', function() {
      currentFilter = $('#block-filter').val();
      applyFilter();
    });

    $('#reset-filter-btn').on('click', function() {
      currentFilter = '';
      $('#block-filter').val('');
      applyFilter();
    });

    // Filtrage en temps réel sur changement de select
    $('#block-filter').on('change', function() {
      currentFilter = $(this).val();
      applyFilter();
    });
  }

  /**
   * Appliquer le filtre sur le tableau
   */
  function applyFilter() {
    const $rows = $('.block-row');
    
    if (!currentFilter) {
      $rows.show();
    } else {
      $rows.each(function() {
        const blockName = $(this).attr('data-block-name');
        if (blockName === currentFilter) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
    }

    // Réinitialiser à la page 1 après un filtrage
    currentPage = 1;
    applyPagination();
    updateMassRecoveryButton();
  }

  /**
   * Initialiser le système de pagination
   */
  function initializePagination() {
    // Sélecteur d'items par page
    $('#items-per-page-select').on('change', function() {
      itemsPerPage = parseInt($(this).val());
      currentPage = 1;
      applyPagination();
    });

    // Boutons de pagination du haut
    $('#first-page').on('click', function() {
      currentPage = 1;
      applyPagination();
    });

    $('#prev-page').on('click', function() {
      if (currentPage > 1) {
        currentPage--;
        applyPagination();
      }
    });

    $('#next-page').on('click', function() {
      if (currentPage < totalPages) {
        currentPage++;
        applyPagination();
      }
    });

    $('#last-page').on('click', function() {
      currentPage = totalPages;
      applyPagination();
    });

    // Input de page directe
    $('#current-page-input').on('change', function() {
      const page = parseInt($(this).val());
      if (page >= 1 && page <= totalPages) {
        currentPage = page;
        applyPagination();
      } else {
        $(this).val(currentPage);
      }
    });

    // Boutons de pagination du bas (dupliquer les événements)
    $('#first-page-bottom').on('click', function() {
      currentPage = 1;
      applyPagination();
    });

    $('#prev-page-bottom').on('click', function() {
      if (currentPage > 1) {
        currentPage--;
        applyPagination();
      }
    });

    $('#next-page-bottom').on('click', function() {
      if (currentPage < totalPages) {
        currentPage++;
        applyPagination();
      }
    });

    $('#last-page-bottom').on('click', function() {
      currentPage = totalPages;
      applyPagination();
    });

    $('#current-page-input-bottom').on('change', function() {
      const page = parseInt($(this).val());
      if (page >= 1 && page <= totalPages) {
        currentPage = page;
        applyPagination();
      } else {
        $(this).val(currentPage);
      }
    });
  }

  /**
   * Appliquer la pagination sur les lignes visibles
   */
  function applyPagination() {
    // Récupérer toutes les lignes
    let $rows = $('.block-row');

    // Appliquer le filtre par type de bloc
    if (currentFilter) {
      $rows = $rows.filter('[data-block-name="' + currentFilter + '"]');
    }

    // Appliquer le filtre par statut de validation
    if (showingOnlyUnvalidated) {
      $rows = $rows.filter('[data-is-validated="0"]');
    }

    totalItems = $rows.length;

    // Si "Tous" est sélectionné (-1)
    if (itemsPerPage === -1) {
      totalPages = 1;
      currentPage = 1;

      // Cacher toutes les lignes d'abord
      $('.block-row').hide();
      // Afficher seulement les lignes filtrées
      $rows.show();
    } else {
      totalPages = Math.ceil(totalItems / itemsPerPage);

      // Corriger la page si elle dépasse
      if (currentPage > totalPages) {
        currentPage = totalPages || 1;
      }

      // Cacher toutes les lignes d'abord
      $('.block-row').hide();

      // Afficher seulement les lignes de la page actuelle
      const start = (currentPage - 1) * itemsPerPage;
      const end = start + itemsPerPage;
      $rows.slice(start, end).show();
    }

    // Mettre à jour l'UI de pagination
    updatePaginationUI();

    // Scroll vers le haut du tableau
    $('html, body').animate({
      scrollTop: $('.wp-list-table').offset().top - 100
    }, 300);
  }

  /**
   * Mettre à jour l'interface de pagination
   */
  function updatePaginationUI() {
    // Mettre à jour les informations
    const start = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    
    let displayText = '';
    if (itemsPerPage === -1) {
      displayText = totalItems + ' élément' + (totalItems > 1 ? 's' : '');
    } else {
      displayText = 'Affichage de ' + start + ' à ' + end + ' sur ' + totalItems + ' élément' + (totalItems > 1 ? 's' : '');
    }
    
    $('.displaying-num').text(displayText);
    $('.total-pages').text(totalPages);
    $('.current-page').val(currentPage);
    
    // Désactiver/activer les boutons selon la page
    const $firstButtons = $('#first-page, #first-page-bottom');
    const $prevButtons = $('#prev-page, #prev-page-bottom');
    const $nextButtons = $('#next-page, #next-page-bottom');
    const $lastButtons = $('#last-page, #last-page-bottom');
    
    if (currentPage <= 1) {
      $firstButtons.prop('disabled', true);
      $prevButtons.prop('disabled', true);
    } else {
      $firstButtons.prop('disabled', false);
      $prevButtons.prop('disabled', false);
    }
    
    if (currentPage >= totalPages || totalPages <= 1) {
      $nextButtons.prop('disabled', true);
      $lastButtons.prop('disabled', true);
    } else {
      $nextButtons.prop('disabled', false);
      $lastButtons.prop('disabled', false);
    }
    
    // Si "Tous" est sélectionné, désactiver la navigation
    if (itemsPerPage === -1) {
      $firstButtons.prop('disabled', true);
      $prevButtons.prop('disabled', true);
      $nextButtons.prop('disabled', true);
      $lastButtons.prop('disabled', true);
    }
  }  /**
   * Initialiser la récupération simple (un bloc à la fois)
   */
  function initializeSingleRecovery() {
    $('.recover-block-btn').on('click', function(e) {
      const $btn = $(this);
      const $row = $btn.closest('tr');
      const postId = $row.attr('data-post-id');
      const blockName = $row.attr('data-block-name');

      // SOLUTION AU BLOCAGE DE POPUP :
      // Ouvrir la fenêtre IMMÉDIATEMENT (avant l'AJAX) pour éviter le blocage par le navigateur
      // Les navigateurs autorisent window.open() uniquement lors d'une interaction utilisateur directe
      const newWindow = window.open('about:blank', '_blank');

      if (!newWindow) {
        showMessage('error', '⚠️ Le popup a été bloqué par votre navigateur. Veuillez autoriser les popups pour ce site.');
        return;
      }

      // Afficher un message de chargement dans la nouvelle fenêtre
      newWindow.document.write(`
        <html>
          <head>
            <title>Chargement...</title>
            <style>
              .gutenberg-loading-window {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                background: #f0f0f1;
              }
              .loading-content { text-align: center; }
              .loading-icon { font-size: 48px; margin-bottom: 20px; }
              .loading-content h2 { color: #1d2327; margin: 0 0 10px 0; }
              .loading-content p { color: #50575e; margin: 0; }
            </style>
          </head>
          <body class="gutenberg-loading-window">
            <div class="loading-content">
              <div class="loading-icon">⏳</div>
              <h2>Ouverture de l'éditeur...</h2>
              <p>Préparation de la récupération automatique</p>
            </div>
          </body>
        </html>
      `);

      $btn.prop('disabled', true).text('Ouverture...');

      $.ajax({
        url: blockRecoveryConfig.ajaxUrl,
        type: 'POST',
        data: {
          action: 'block_recovery_single',
          nonce: blockRecoveryConfig.nonce,
          post_id: postId,
          block_name: blockName
        },
        success: function(response) {
          if (response.success) {
            const editorUrl = response.data.edit_url + '&recovery_block=' + encodeURIComponent(blockName);

            // Rediriger la fenêtre déjà ouverte vers l'éditeur
            newWindow.location.href = editorUrl;

            showMessage('success', '✅ Éditeur ouvert ! La récupération automatique va se lancer dans quelques instants.');

            $row.addClass('recovery-pending');
            $btn.removeClass('button-primary').addClass('button-secondary').text('Ouvrir à nouveau');
            $btn.prop('disabled', false);

            // Stocker l'URL pour pouvoir rouvrir
            $btn.data('editor-url', editorUrl);

            $btn.off('click').on('click', function() {
              const url = $(this).data('editor-url');
              window.open(url, '_blank');
            });
          } else {
            console.error('[BlockRecovery] Erreur:', response.data);
            newWindow.close();
            showMessage('error', response.data.message || 'Erreur lors de l\'ouverture');
            $btn.prop('disabled', false).text('Récupérer');
          }
        },
        error: function(xhr, status, error) {
          console.error('[BlockRecovery] Erreur AJAX:', status, error);
          newWindow.close();
          showMessage('error', 'Erreur de connexion: ' + error);
          $btn.prop('disabled', false).text('Récupérer');
        }
      });
    });
  }

  /**
   * Initialiser la validation manuelle des blocs
   */
  function initializeValidation() {
    $('.validate-block-btn').on('click', function() {
      const $btn = $(this);
      const $row = $btn.closest('tr');
      const postId = $row.attr('data-post-id');
      const blockName = $row.attr('data-block-name');

      if (!confirm('Confirmer que ce bloc a été récupéré et vérifié avec succès ?')) {
        return;
      }

      $btn.prop('disabled', true).text('Validation...');

      $.ajax({
        url: blockRecoveryConfig.ajaxUrl,
        type: 'POST',
        data: {
          action: 'block_recovery_validate',
          nonce: blockRecoveryConfig.nonce,
          post_id: postId,
          block_name: blockName
        },
        success: function(response) {
          if (response.success) {
            // Marquer cette ligne comme validée dans le DOM
            $row.attr('data-is-validated', '1');
            updateRowValidationStatus($row, true);
            
            // Mettre à jour le bouton de récupération multiple
            updateMassRecoveryButton();
            
            $btn.prop('disabled', false).text('Valider');
            
            // Message de succès adapté selon le statut
            if (response.data.can_auto_recover) {
              showMessage('success', '✓ Post validé avec succès ! La récupération automatique est désormais activée pour ce bloc');
            } else {
              showMessage('success', '✓ Post validé avec succès ! Validez au moins 2 posts pour débloquer la récupération automatique.');
            }
          } else {
            showMessage('error', response.data.message || 'Erreur lors de la validation');
            $btn.prop('disabled', false).text('Valider');
          }
        },
        error: function() {
          showMessage('error', 'Erreur de connexion');
          $btn.prop('disabled', false).text('Valider');
        }
      });
    });
  }

  /**
   * Mettre à jour le statut de validation d'une ligne
   */
  function updateRowValidationStatus($row, isValidated) {
    const $statusCell = $row.find('.validation-status');
    
    if (isValidated) {
      $statusCell.html(
        '<span class="status-badge validated">' +
        '<span class="dashicons dashicons-yes-alt"></span>' +
        'Validé' +
        '</span>'
      );
    } else {
      $statusCell.html(
        '<span class="status-badge not-validated">' +
        '<span class="dashicons dashicons-warning"></span>' +
        'Non validé' +
        '</span>'
      );
    }
  }

  /**
   * Initialiser la récupération multiple
   */
  function initializeMassRecovery() {
    $('#mass-recovery-btn').on('click', function() {
      if ($(this).prop('disabled')) {
        return;
      }

      const blockName = currentFilter;
      if (!blockName) {
        showMessage('error', 'Veuillez d\'abord sélectionner un bloc à récupérer');
        return;
      }

      // Compter les POSTS UNIQUES validés et non validés pour ce bloc
      const allRows = $('.block-row[data-block-name="' + blockName + '"]');
      const validatedPosts = new Set();
      const unvalidatedPosts = new Set();
      
      allRows.each(function() {
        const postId = $(this).attr('data-post-id');
        const isValidated = $(this).attr('data-is-validated');
        
        if (isValidated === '1') {
          validatedPosts.add(postId);
        } else {
          unvalidatedPosts.add(postId);
        }
      });
      
      const validatedCount = validatedPosts.size;
      const unvalidatedCount = unvalidatedPosts.size;
      
      if (validatedCount < 2) {
        showMessage('error', 'Ce bloc doit être validé sur au moins 2 posts différents avant la récupération automatique (actuellement : ' + validatedCount + ')');
        return;
      }

      // Calcul du temps estimé : ~3s par post maintenant (optimisé)
      const estimatedSeconds = unvalidatedCount * 3;
      const estimatedMinutes = Math.ceil(estimatedSeconds / 60);
      const timeDisplay = estimatedMinutes > 0 ? estimatedMinutes + ' minute' + (estimatedMinutes > 1 ? 's' : '') : (estimatedSeconds + ' secondes');
      
      if (!confirm('Récupérer automatiquement tous les blocs "' + blockName + '" non validés ?\n\n⚠️ Cette opération traitera ' + unvalidatedCount + ' post(s) UN PAR UN (séquentiel).\nChaque post sera récupéré, sauvegardé et validé avant de passer au suivant.\n\n⏱️ Temps estimé : ~' + timeDisplay + '\n\n💡 Traitement optimisé, rapide et 100% fiable.')) {
        return;
      }

      startMassRecovery(blockName);
    });
  }

  /**
   * Démarrer la récupération multiple
   */
  function startMassRecovery(blockName) {
    // Récupérer TOUS les rows du bloc (pas seulement les visibles)
    // IMPORTANT : Ne pas utiliser :visible car on veut traiter tous les posts du bloc
    const allRows = $('.block-row[data-block-name="' + blockName + '"]');
    
    // Filtrer les NON validés
    const postsToRecover = [];
    let validatedSkipped = 0;
    
    allRows.each(function() {
      const $row = $(this);
      const isValidated = $row.attr('data-is-validated');
      const postId = $row.attr('data-post-id');
      const postTitle = $row.find('td:eq(1) strong').text() || $row.find('td:eq(1) a').text();
      
      // Vérifier si NON validé (strictement : doit être différent de '1')
      if (isValidated !== '1') {
        postsToRecover.push({
          post_id: postId,
          post_title: postTitle,
          edit_url: $row.find('td:eq(1) a').attr('href')
        });
      } else {
        validatedSkipped++;
      }
    });

    if (postsToRecover.length === 0) {
      showMessage('info', 'Aucun post non validé à récupérer pour ce bloc');
      return;
    }

    // Utiliser la méthode iframe avec traitement séquentiel (100% fiable)
    showMassRecoveryModal(postsToRecover, blockName);
  }

  /**
   * Afficher la modal de progression et lancer les récupérations via iframes
   */
  function showMassRecoveryModal(posts, blockName) {
    const $modal = $('#mass-recovery-modal');
    const $progressFill = $modal.find('.progress-fill');
    const $progressText = $modal.find('.progress-text');
    const $processingText = $modal.find('.processing-text');
    const $timeEstimate = $modal.find('.time-estimate');
    const $log = $modal.find('.recovery-log');
    const $closeBtn = $modal.find('#close-modal-btn');
    const $cancelBtn = $modal.find('#cancel-recovery-btn');

    // Éléments des statistiques
    const $statSuccess = $('#stat-success');
    const $statFailed = $('#stat-failed');
    const $statRemaining = $('#stat-remaining');

    $modal.addClass('visible');
    $log.html('');
    $progressFill.css('width', '0%');
    $progressText.text('0 / ' + posts.length);

    // Initialiser les statistiques
    $statSuccess.text('0');
    $statFailed.text('0');
    $statRemaining.text(posts.length);

    // Configurer les boutons
    $closeBtn.prop('disabled', true).hide();
    $cancelBtn.prop('disabled', false).show();

    let completed = 0;
    let succeeded = 0;
    let failed = 0;
    let currentlyProcessing = 0;
    let isCancelled = false;

    // Utiliser la constante globale pour le batch
    const BATCH_SIZE = RECOVERY_BATCH_SIZE;
    
    const startTime = Date.now();

    // Créer un conteneur pour les iframes invisibles
    let $iframeContainer = $('#gutenberg-recovery-iframes');
    if ($iframeContainer.length === 0) {
      $iframeContainer = $('<div id="gutenberg-recovery-iframes"></div>');
      $('body').append($iframeContainer);
    }

    // Index du batch en cours
    let currentBatchIndex = 0;

    // Gérer le bouton annuler
    $cancelBtn.off('click').on('click', function() {
      if (confirm('Êtes-vous sûr de vouloir annuler la récupération en cours ?\n\nLes posts déjà traités resteront récupérés.')) {
        isCancelled = true;
        $cancelBtn.prop('disabled', true).text('Annulation...');
        $processingText.text('Annulation en cours...');

        // La récupération s'arrêtera à la fin du batch en cours
        showMessage('warning', 'Récupération annulée. Les posts déjà traités ont été récupérés.');
      }
    });

    function processNextBatch() {
      // Vérifier si annulé
      if (isCancelled) {
        finishRecovery(true);
        return;
      }

      const batchStart = currentBatchIndex * BATCH_SIZE;
      const batchEnd = Math.min(batchStart + BATCH_SIZE, posts.length);
      const batch = posts.slice(batchStart, batchEnd);

      if (batch.length === 0) {
        // Tous les posts ont été traités
        finishRecovery(false);
        return;
      }

      let batchCompleted = 0;
      currentlyProcessing = batch.length;

      // Mettre à jour l'affichage
      updateProgressInfo();

      // Traiter tous les posts du batch en parallèle
      batch.forEach(function(post) {
        // Afficher le nom du post en cours (pour le mode séquentiel)
        if (BATCH_SIZE === 1) {
          updateProgressInfo(post.post_title);
        }

        processPost(post, blockName, function(success) {
          batchCompleted++;
          currentlyProcessing = batch.length - batchCompleted;
          completed++;

          // Incrémenter les compteurs
          if (success) {
            succeeded++;
          } else {
            failed++;
          }

          // Mettre à jour les statistiques en temps réel
          $statSuccess.text(succeeded);
          $statFailed.text(failed);
          $statRemaining.text(posts.length - completed);
          
          // Mettre à jour la barre de progression
          const totalCompleted = batchStart + batchCompleted;
          const progress = (totalCompleted / posts.length) * 100;
          $progressFill.css('width', progress + '%');
          $progressText.text(totalCompleted + ' / ' + posts.length);
          
          // Calculer le temps restant (sans nom de post)
          updateProgressInfo();
          
          // Quand tout le batch est terminé, passer au suivant
          if (batchCompleted === batch.length) {
            currentlyProcessing = 0;
            currentBatchIndex++;
            
            // Délai réduit avant le prochain batch (200ms au lieu de 500ms)
            setTimeout(function() {
              processNextBatch();
            }, 200);
          }
        });
      });
    }
    
    function processPost(post, blockName, callback) {
      const editorUrl = post.edit_url + '&recovery_block=' + encodeURIComponent(blockName) + '&auto_save=1&in_iframe=1';
      
      // Créer un iframe invisible pour charger Gutenberg
      const $iframe = $('<iframe></iframe>')
        .attr('src', editorUrl)
        .addClass('recovery-iframe');
      
      let processingTimeout;
      let callbackCalled = false;
      
      // Détecter quand l'iframe a terminé (via message postMessage ou timeout)
      const messageHandler = function(event) {
        // Vérifier que le message vient de notre iframe
        if (event.data && event.data.type === 'gutenberg_recovery_complete') {
          if (!callbackCalled) {
            callbackCalled = true;
            clearTimeout(processingTimeout);
            window.removeEventListener('message', messageHandler);
            
            $iframe.remove();
            
            // Si succès, marquer comme validé via AJAX
            if (event.data.success) {
              $log.append('<p style="color: #46b450;">✓ Succès : ' + post.post_title + '</p>');
              markPostAsValidated(post.post_id, blockName, function(validationSuccess) {
                callback(true, post.post_title);
              });
            } else {
              $log.append('<p style="color: #dc3232;">✗ Échec : ' + post.post_title + '</p>');
              callback(false, post.post_title);
            }
          }
        }
      };
      
      window.addEventListener('message', messageHandler);
      
      // Timeout de sécurité : 10 secondes
      processingTimeout = setTimeout(function() {
        if (!callbackCalled) {
          callbackCalled = true;
          window.removeEventListener('message', messageHandler);
          $iframe.remove();

          // Timeout = échec, ne pas valider
          $log.append('<p style="color: #dc3232;">✗ Timeout : ' + post.post_title + '</p>');
          callback(false, post.post_title);
        }
      }, 10000);
      
      // Ajouter l'iframe au DOM pour démarrer le chargement
      $iframeContainer.append($iframe);
    }
    
    function updateProgressInfo(currentPostTitle) {
      // Afficher le nom du post en cours de traitement (mode séquentiel)
      if (currentPostTitle) {
        $processingText.html('Traitement de <strong>"' + currentPostTitle + '"</strong>');
      } else if (currentlyProcessing > 0) {
        $processingText.text('Traitement de ' + currentlyProcessing + ' post' + (currentlyProcessing > 1 ? 's' : '') + ' en parallèle');
      } else {
        $processingText.text('Préparation du prochain batch...');
      }
      
      // Calculer et afficher le temps restant estimé
      const totalCompleted = currentBatchIndex * BATCH_SIZE + (BATCH_SIZE - currentlyProcessing);
      const remaining = posts.length - totalCompleted;
      
      if (remaining > 0 && totalCompleted > 0) {
        const elapsed = (Date.now() - startTime) / 1000; // en secondes
        const avgTimePerPost = elapsed / totalCompleted;
        const estimatedRemaining = Math.ceil((remaining * avgTimePerPost) / 60); // en minutes
        
        if (estimatedRemaining > 0) {
          $timeEstimate.text('~' + estimatedRemaining + ' min restante' + (estimatedRemaining > 1 ? 's' : ''));
        } else {
          $timeEstimate.text('< 1 min');
        }
      }
    }
    
    function finishRecovery(wasCancelled) {
      const actualProgress = (completed / posts.length) * 100;
      $progressFill.css('width', actualProgress + '%');
      $progressText.text(completed + ' / ' + posts.length);

      // Masquer le spinner et afficher le statut final
      $modal.find('.processing-status').hide();
      $timeEstimate.text('');

      // Masquer le bouton annuler et afficher le bouton fermer
      $cancelBtn.hide();
      $closeBtn.prop('disabled', false).show();

      // Message adapté selon si c'est une annulation ou une fin normale
      let messageHtml = '';
      let notificationMessage = '';

      if (wasCancelled) {
        messageHtml = '<p class="log-warning">⚠ Récupération annulée : ' +
                     succeeded + ' / ' + completed + ' post' + (completed > 1 ? 's' : '') + ' récupéré' + (succeeded > 1 ? 's' : '') +
                     (failed > 0 ? ' (' + failed + ' échec' + (failed > 1 ? 's' : '') + ')' : '') +
                     '</p>';
        notificationMessage = 'Récupération annulée : ' + succeeded + ' post(s) récupéré(s)';
      } else if (succeeded > 0) {
        messageHtml = '<p class="log-success">✓ Terminé : ' +
                     succeeded + ' / ' + posts.length + ' post' + (posts.length > 1 ? 's' : '') + ' récupéré' + (succeeded > 1 ? 's' : '') +
                     (failed > 0 ? ' (' + failed + ' échec' + (failed > 1 ? 's' : '') + ')' : '') +
                     '</p>';
        notificationMessage = 'Récupération terminée : ' + succeeded + ' / ' + posts.length + ' post(s) récupéré(s)';
      } else {
        messageHtml = '<p class="log-error">✗ Échec : aucun post récupéré</p>';
        notificationMessage = 'Échec de la récupération : aucun post récupéré';
      }

      $log.html(messageHtml);
      showMessage(succeeded > 0 ? 'success' : 'error', notificationMessage);

      // Recharger la page sans toucher au cache
      // Les posts validés seront automatiquement filtrés par l'interface
      if (wasCancelled || succeeded > 0) {
        setTimeout(function() {
          location.reload();
        }, wasCancelled ? 2000 : 3000);
      }
    }

    // Démarrer le traitement par batch
    processNextBatch();

    // Gérer la fermeture de la modal
    $closeBtn.off('click').on('click', function() {
      $modal.hide();
    });
  }

  /**
   * Mettre à jour l'état du bouton de récupération multiple
   */
  function updateMassRecoveryButton() {
    const $btn = $('#mass-recovery-btn');
    const $status = $('#mass-recovery-status');
    
    if (!currentFilter) {
      $btn.prop('disabled', true);
      $status.text('Sélectionnez un bloc pour activer');
      return;
    }

    // Compter les POSTS UNIQUES par statut de validation
    const allRows = $('.block-row[data-block-name="' + currentFilter + '"]');
    const uniquePosts = new Set();
    const validatedPosts = new Set();
    const unvalidatedPosts = new Set();
    
    allRows.each(function() {
      const postId = $(this).attr('data-post-id');
      const isValidated = $(this).attr('data-is-validated');
      
      uniquePosts.add(postId);
      
      if (isValidated === '1' || isValidated === 1 || isValidated === true) {
        validatedPosts.add(postId);
      } else {
        unvalidatedPosts.add(postId);
      }
    });
    
    const validatedCount = validatedPosts.size;
    const unvalidatedCount = unvalidatedPosts.size;

    if (validatedCount < 2) {
      $btn.prop('disabled', true);
      $status.removeClass('ready info').addClass('warning')
        .html('<span class="dashicons dashicons-warning"></span> Validation requise : ' + validatedCount + '/2 posts validés');
      return;
    }

    if (unvalidatedCount === 0) {
      $btn.prop('disabled', true);
      $status.removeClass('warning ready').addClass('info')
        .html('<span class="dashicons dashicons-yes-alt"></span> Tous les posts sont déjà validés');
      return;
    }

    // Prêt pour récupération multiple
    $btn.prop('disabled', false);
    $status.removeClass('warning info').addClass('ready')
      .html('<span class="dashicons dashicons-yes-alt"></span> Prêt : ' + unvalidatedCount + ' post(s) à récupérer');
  }

  /**
   * Initialiser le bouton de rafraîchissement des données
   */
  function initializeRefreshButton() {
    $('#refresh-data-btn').on('click', function() {
      const $btn = $(this);

      // Toggle entre afficher tous les blocs et seulement les non validés
      if (showingOnlyUnvalidated) {
        // Réafficher tous les blocs
        showingOnlyUnvalidated = false;
        $btn.html('<span class="dashicons dashicons-update"></span> Afficher non validés');
        showMessage('info', 'Affichage de tous les blocs');
      } else {
        // Afficher uniquement les blocs non validés
        showingOnlyUnvalidated = true;
        $btn.html('<span class="dashicons dashicons-visibility"></span> Afficher tout');

        // Compter les blocs non validés (en tenant compte du filtre actuel)
        let $unvalidatedRows = $('.block-row[data-is-validated="0"]');
        if (currentFilter) {
          $unvalidatedRows = $unvalidatedRows.filter('[data-block-name="' + currentFilter + '"]');
        }
        const unvalidatedCount = $unvalidatedRows.length;

        showMessage('success', 'Affichage de ' + unvalidatedCount + ' bloc(s) non validé(s)');
      }

      // Réappliquer la pagination (qui gère maintenant le filtre de validation)
      currentPage = 1;
      applyPagination();
    });
  }

  /**
   * Initialiser le bouton de réinitialisation des validations
   */
  function initializeResetValidations() {
    $('#reset-validations-btn').on('click', function() {
      if (!confirm('Êtes-vous sûr de vouloir réinitialiser toutes les validations ? Cette action est irréversible.')) {
        return;
      }

      $.ajax({
        url: blockRecoveryConfig.ajaxUrl,
        type: 'POST',
        data: {
          action: 'block_recovery_reset_validations',
          nonce: blockRecoveryConfig.nonce
        },
        success: function(response) {
          if (response.success) {
            showMessage('success', response.data.message);
            // Rafraîchir la page après 2 secondes
            setTimeout(function() {
              location.reload();
            }, 2000);
          } else {
            showMessage('error', response.data.message || 'Erreur lors de la réinitialisation');
          }
        },
        error: function() {
          showMessage('error', 'Erreur de connexion lors de la réinitialisation');
        }
      });
    });
  }

  /**
   * Afficher un message
   */
  function showMessage(type, message) {
    const $messageDiv = $('#recovery-message');
    $messageDiv.html('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
    
    $('html, body').animate({
      scrollTop: $messageDiv.offset().top - 100
    }, 500);
    
    setTimeout(function() {
      $messageDiv.find('.notice').fadeOut();
    }, 5000);
  }

})(jQuery);
