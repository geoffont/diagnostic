/**
 * Gutenberg Validation Script - Détection des blocs invalides
 *
 * Ce script s'exécute dans l'éditeur Gutenberg et détecte les blocs invalides
 * en utilisant l'API native de WordPress (block.isValid).
 *
 * WordPress calcule automatiquement isValid en comparant :
 * - Le HTML sauvegardé dans la base de données
 * - Le HTML régénéré par save.js avec les mêmes attributs
 *
 * @package     Company\Diagnostic\Features\Scanner
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 *
 * @workflow:
 * 1. Attendre le chargement de Gutenberg
 * 2. Récupérer tous les blocs du post
 * 3. Vérifier block.isValid pour chaque bloc
 * 4. Envoyer la liste des blocs invalides via postMessage
 * 5. Fermer l'iframe
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
      detectInvalidBlocks();
    }
  }, 100);

  function detectInvalidBlocks() {
    // Délai pour s'assurer que l'éditeur est complètement chargé
    setTimeout(function() {
      try {
        const { select } = wp.data;
        const inIframe = window.self !== window.top;

        // Obtenir tous les blocs du post
        const blocks = select('core/block-editor').getBlocks();

        // Détecter les blocs invalides récursivement
        const invalidBlocks = [];
        detectInvalidBlocksRecursive(blocks, invalidBlocks);

        // Envoyer les résultats au parent
        if (inIframe) {
          window.parent.postMessage({
            type: 'gutenberg_validation_complete',
            success: true,
            invalidBlocks: invalidBlocks,
            totalBlocks: countAllBlocks(blocks)
          }, '*');
        }

        // Fermer l'iframe après un court délai
        setTimeout(function() {
          if (inIframe) {
            window.close();
          }
        }, 100);

      } catch (error) {
        console.error('[Gutenberg Validation] Erreur:', error);

        // Envoyer l'erreur au parent
        if (window.self !== window.top) {
          window.parent.postMessage({
            type: 'gutenberg_validation_complete',
            success: false,
            error: error.message
          }, '*');
        }
      }
    }, 1000); // Délai de 1 seconde pour le chargement complet
  }

  /**
   * Détecter les blocs invalides récursivement (incluant innerBlocks)
   */
  function detectInvalidBlocksRecursive(blocks, invalidBlocks, path = []) {
    blocks.forEach((block, index) => {
      const currentPath = [...path, index];

      // Vérifier si le bloc est invalide
      if (!block.isValid) {
        // Sérialiser les données pour éviter DataCloneError avec postMessage
        // On ne peut pas envoyer des objets avec des références circulaires
        invalidBlocks.push({
          name: block.name,
          clientId: block.clientId,
          // Sérialiser les attributs (peut contenir des objets complexes)
          attributes: serializeAttributes(block.attributes),
          path: currentPath,
          // validationIssues peut contenir des objets non-clonables, on les convertit en string
          validationIssues: serializeValidationIssues(block.validationIssues)
        });
      }

      // Vérifier récursivement les innerBlocks
      if (block.innerBlocks && block.innerBlocks.length > 0) {
        detectInvalidBlocksRecursive(block.innerBlocks, invalidBlocks, currentPath);
      }
    });
  }

  /**
   * Sérialiser les attributs pour postMessage
   */
  function serializeAttributes(attributes) {
    if (!attributes) return {};

    try {
      // Convertir en JSON puis parser pour supprimer les références circulaires
      return JSON.parse(JSON.stringify(attributes));
    } catch (e) {
      // Si la sérialisation échoue, retourner un objet vide
      console.warn('[Gutenberg Validation] Impossible de sérialiser les attributs:', e);
      return {};
    }
  }

  /**
   * Sérialiser les validationIssues pour postMessage
   */
  function serializeValidationIssues(issues) {
    if (!issues || !Array.isArray(issues)) return [];

    try {
      // Convertir chaque issue en string ou objet simple
      return issues.map(issue => {
        if (typeof issue === 'string') return issue;
        if (typeof issue === 'object') {
          return {
            message: issue.message || String(issue),
            code: issue.code || null
          };
        }
        return String(issue);
      });
    } catch (e) {
      console.warn('[Gutenberg Validation] Impossible de sérialiser les validationIssues:', e);
      return [];
    }
  }

  /**
   * Compter tous les blocs (incluant innerBlocks)
   */
  function countAllBlocks(blocks) {
    let count = 0;
    blocks.forEach(block => {
      count++;
      if (block.innerBlocks && block.innerBlocks.length > 0) {
        count += countAllBlocks(block.innerBlocks);
      }
    });
    return count;
  }

})();
