/**
 * Gutenberg Recovery Script - Récupération dans l'éditeur
 *
 * Ce script s'exécute directement dans l'éditeur Gutenberg et effectue
 * la récupération automatique des blocs invalides. Il utilise les APIs
 * natives de Gutenberg pour créer et remplacer les blocs.
 *
 * @package     Company\Diagnostic\Features\BlockRecovery
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     2.0.0
 * @since       2.0.0
 * @created     2025-10-21
 * @modified    2025-10-21
 *
 * @responsibilities:
 * - Détection des blocs invalides dans l'éditeur
 * - Récupération via wp.blocks.createBlock() et replaceBlock()
 * - Sauvegarde automatique après récupération
 * - Communication avec iframe parent via postMessage
 * - Fermeture automatique après traitement
 *
 * @dependencies:
 * - wp.data (store Gutenberg)
 * - wp.blocks (APIs de blocs)
 * - wp.editor (sauvegarde)
 * - WordPress Block Editor (Gutenberg)
 *
 * @url_parameters:
 * - recovery_block : Nom du bloc à récupérer (requis)
 * - auto_save : Mode auto-save (1 = oui, 0 = non)
 *
 * @postMessage:
 * Envoie au parent :
 * {
 *   type: 'gutenberg_recovery_complete',
 *   success: true/false,
 *   recoveredCount: number,
 *   message: string (si échec),
 *   error: string (si erreur)
 * }
 *
 * @related_files:
 * - block-recovery-advanced.js (orchestration des iframes)
 * - ../../Feature.php (enqueue du script)
 * - ../../Core/BlockRecoveryService.php (logique métier)
 *
 * @workflow:
 * 1. Attente du chargement complet de Gutenberg
 * 2. Lecture des paramètres URL (recovery_block, auto_save)
 * 3. Récupération de tous les blocs du post
 * 4. Identification des blocs invalides
 * 5. Création de nouveaux blocs via wp.blocks.createBlock()
 * 6. Remplacement des blocs invalides via replaceBlock()
 * 7. Sauvegarde automatique si auto_save=1
 * 8. Envoi postMessage au parent
 * 9. Fermeture de la fenêtre/iframe
 *
 * @note:
 * Ce script DOIT s'exécuter dans le contexte de l'éditeur Gutenberg.
 * Il ne peut pas fonctionner en REST API ou en batch côté serveur.
 */
(function() {
  'use strict';

  // Attendre que Gutenberg soit chargé
  const waitForGutenberg = setInterval(function() {
    if (typeof wp !== 'undefined' && 
        typeof wp.data !== 'undefined' && 
        typeof wp.blocks !== 'undefined' &&
        typeof wp.data.select !== 'undefined') {
      
      clearInterval(waitForGutenberg);
      initBlockRecovery();
    }
  }, 100);

  function initBlockRecovery() {
    // Vérifier si on a un paramètre de récupération dans l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const recoveryBlockName = urlParams.get('recovery_block');
    const autoSave = urlParams.get('auto_save'); // Pour la récupération automatique multiple
    
    if (!recoveryBlockName) {
      return; // Pas de récupération à faire
    }

    // Attendre que l'éditeur soit complètement chargé - délai optimisé
    setTimeout(function() {
      attemptNativeRecovery(recoveryBlockName, autoSave === '1');
    }, 500); // Réduit de 800ms à 500ms
  }

  function attemptNativeRecovery(blockName, autoSave) {
    const { select, dispatch } = wp.data;
    const inIframe = window.self !== window.top;
    
    try {
      // Obtenir tous les blocs du post
      const blocks = select('core/block-editor').getBlocks();

      // Trouver les blocs en mode recovery
      let recoveredCount = 0;
      blocks.forEach((block, index) => {
        if (block.name === blockName && !block.isValid) {
          // Utiliser la fonction native de Gutenberg pour créer un nouveau bloc
          // C'est exactement ce que fait le bouton "Tentative de récupération"
          try {
            const blockType = wp.blocks.getBlockType(blockName);
            
            if (blockType) {
              // Créer un nouveau bloc avec les attributs récupérés
              const recoveredBlock = wp.blocks.createBlock(
                blockName,
                block.attributes || {}
              );
              
              // Remplacer le bloc invalide par le bloc récupéré
              dispatch('core/block-editor').replaceBlock(
                block.clientId,
                recoveredBlock
              );
              
              recoveredCount++;
            } else {
              console.error('[Block Recovery] Type de bloc introuvable:', blockName);
            }
          } catch (error) {
            console.error('[Block Recovery] Erreur lors de la récupération:', error);
          }
        }
      });

      // Afficher un message de confirmation
      if (recoveredCount > 0) {
        // Mode auto-save : pas de notification visuelle, juste sauvegarder
        if (autoSave) {
          dispatch('core/editor').savePost();
          
          // Timeout de sécurité : envoyer le message après 6 secondes MAX
          let messageSent = false;
          const safetyTimeout = setTimeout(function() {
            if (!messageSent) {
              messageSent = true;
              
              if (inIframe) {
                window.parent.postMessage({
                  type: 'gutenberg_recovery_complete',
                  success: true,
                  recoveredCount: recoveredCount
                }, '*');
              }
              
              if (inIframe || window.opener) {
                window.close();
              }
            }
          }, 6000);
          
          // Attendre la fin de la sauvegarde avec polling rapide
          const saveInterval = setInterval(function() {
            const isSaving = select('core/editor').isSavingPost();
            const hasFinishedSaving = !isSaving;
            
            if (hasFinishedSaving) {
              clearInterval(saveInterval);
              clearTimeout(safetyTimeout);
              
              if (!messageSent) {
                messageSent = true;
                
                // Notifier le parent (si dans iframe) que c'est terminé
                if (inIframe) {
                  window.parent.postMessage({
                    type: 'gutenberg_recovery_complete',
                    success: true,
                    recoveredCount: recoveredCount
                  }, '*');
                }
                
                // Fermer immédiatement
                if (inIframe || window.opener) {
                  window.close();
                }
              }
            }
          }, 50);
          
        } else {
          // Mode manuel : afficher notification
          dispatch('core/notices').createSuccessNotice(
            `${recoveredCount} bloc(s) récupéré(s) automatiquement. Vérifiez et sauvegardez.`,
            { type: 'snackbar', isDismissible: true }
          );
        }
      } else {
        // Aucun bloc récupéré
        if (autoSave) {
          // Notifier le parent même en cas de non-récupération
          if (inIframe) {
            window.parent.postMessage({
              type: 'gutenberg_recovery_complete',
              success: false,
              message: 'Aucun bloc à récupérer'
            }, '*');
          }
          
          if (inIframe || window.opener) {
            window.close();
          }
        } else {
          dispatch('core/notices').createWarningNotice(
            'Aucun bloc en mode recovery trouvé. Le bloc a peut-être déjà été récupéré.',
            { type: 'snackbar', isDismissible: true }
          );
        }
      }
      
    } catch (error) {
      dispatch('core/notices').createErrorNotice(
        'Erreur lors de la récupération automatique: ' + error.message,
        { type: 'snackbar', isDismissible: true }
      );
      
      // Notifier le parent de l'erreur
      if (inIframe) {
        window.parent.postMessage({
          type: 'gutenberg_recovery_complete',
          success: false,
          error: error.message
        }, '*');
        
        // Fermer quand même l'iframe
        setTimeout(function() {
          window.close();
        }, 500);
      }
    }
  }

})();
