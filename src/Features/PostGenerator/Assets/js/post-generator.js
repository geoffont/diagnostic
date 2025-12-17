/**
 * Interface JavaScript pour le générateur de posts de test
 *
 * Ce fichier gère l'interface utilisateur du générateur de posts, incluant
 * la configuration des paramètres, la génération via AJAX, la sauvegarde
 * des préférences et le suivi des performances. Il utilise jQuery pour
 * la manipulation DOM et la communication avec le backend WordPress.
 *
 * @file        post-generator.js
 * @package     Company\Diagnostic\Features\PostGenerator
 * @author      Geoffroy Fontaine
 * @copyright   2025 Company
 * @license     GPL-2.0+
 * @version     1.0.0
 * @since       1.0.0
 * @created     2025-09-11
 * @modified    2025-09-11
 *
 * @responsibilities:
 * - Interface de génération de posts
 * - Gestion des paramètres utilisateur
 * - Communication AJAX avec le backend
 * - Sauvegarde des préférences (localStorage)
 * - Validation des formulaires
 * - Suivi des performances
 *
 * @dependencies:
 * - jQuery (inclus avec WordPress)
 * - WordPress AJAX API
 * - LocalStorage API
 * - Performance API (optionnel)
 *
 * @related_files:
 * - ../UI/Screens/PostGeneratorScreen.php (backend)
 * - Feature.php (enregistrement des assets)
 * - ../Core/PostContentGenerator.php (génération)
 * - post-generator.css (styles)
 *
 * @global {Object} jQuery - Bibliothèque JavaScript WordPress
 * @global {Object} localStorage - API de stockage local
 * @global {Object} performance - API de performance (optionnel)
 */

(function($) {
    'use strict';

    /**
     * Objet principal du PostGenerator
     */
    const PostGenerator = {
        
        /**
         * Initialisation
         */
        init: function() {
            this.bindEvents();
            this.initFormValidation();
            this.initProgressTracking();
            this.loadStoredPreferences();
        },

        /**
         * Lier les événements
         */
        bindEvents: function() {
            // Soumission du formulaire de génération
            $('.generation-form').on('submit', this.handleGenerationSubmit.bind(this));
            
            // Soumission du formulaire de nettoyage
            $('.cleanup-form').on('submit', this.handleCleanupSubmit.bind(this));
            
            // Changements dans le formulaire
            $('.generation-form input, .generation-form select').on('change', this.savePreferences.bind(this));
            
            // Calcul dynamique du temps estimé
            $('#post_count, #blocks_range').on('change', this.updateEstimation.bind(this));
            
            // Boutons d'aide
            $('.help-button').on('click', this.showHelp.bind(this));
        },

        /**
         * Validation du formulaire
         */
        initFormValidation: function() {
            const form = $('.generation-form');
            
            form.on('submit', function(e) {
                const postCount = parseInt($('#post_count').val());
                
                if (postCount > 50) {
                    const confirmed = confirm(
                        'Vous allez générer ' + postCount + ' posts. ' +
                        'Cela peut prendre du temps et utiliser beaucoup d\'espace. ' +
                        'Voulez-vous continuer ?'
                    );
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        },

        /**
         * Gestion de la soumission du formulaire de génération
         */
        handleGenerationSubmit: function(e) {
            const form = $(e.target);
            const submitButton = form.find('input[type="submit"]');
            
            // Désactiver le bouton et afficher le loading
            submitButton.prop('disabled', true);
            submitButton.val('⏳ Génération en cours...');
            
            // Ajouter une classe de loading au formulaire
            form.addClass('loading');
            
            // Afficher une estimation du temps
            this.showProgressEstimation();
        },

        /**
         * Gestion de la soumission du formulaire de nettoyage
         */
        handleCleanupSubmit: function(e) {
            const form = $(e.target);
            const submitButton = form.find('input[type="submit"]');
            
            // Confirmation supplémentaire
            const confirmed = confirm(
                'ATTENTION : Cette action supprimera définitivement tous les posts de test.\n\n' +
                'Cette action est irréversible. Êtes-vous absolument sûr de vouloir continuer ?'
            );
            
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
            
            // Désactiver le bouton
            submitButton.prop('disabled', true);
            submitButton.val('🗑️ Suppression en cours...');
            
            form.addClass('loading');
        },

        /**
         * Mettre à jour l'estimation de temps
         */
        updateEstimation: function() {
            const postCount = parseInt($('#post_count').val()) || 0;
            const blocksRange = $('#blocks_range').val().split(',');
            const avgBlocks = (parseInt(blocksRange[0]) + parseInt(blocksRange[1])) / 2;
            
            // Estimation basée sur des tests empiriques
            // ~0.1 seconde par post + 0.01 seconde par bloc
            const estimatedTime = (postCount * 0.1) + (postCount * avgBlocks * 0.01);
            
            let timeText = '';
            if (estimatedTime < 1) {
                timeText = 'Moins d\'une seconde';
            } else if (estimatedTime < 60) {
                timeText = Math.round(estimatedTime) + ' secondes';
            } else {
                const minutes = Math.floor(estimatedTime / 60);
                const seconds = Math.round(estimatedTime % 60);
                timeText = minutes + ' min ' + (seconds > 0 ? seconds + ' sec' : '');
            }
            
            // Afficher l'estimation
            let estimationDiv = $('.time-estimation');
            if (estimationDiv.length === 0) {
                estimationDiv = $('<div class="time-estimation"></div>');
                $('#post_count').closest('td').append(estimationDiv);
            }
            
            estimationDiv.html(
                '<small><strong>⏱️ Temps estimé :</strong> ' + timeText + 
                ' (' + (postCount * avgBlocks).toLocaleString() + ' blocs au total)</small>'
            );
        },

        /**
         * Afficher la progression estimée
         */
        showProgressEstimation: function() {
            const progressDiv = $('<div class="generation-progress"></div>');
            progressDiv.html(`
                <div class="progress-info">
                    <h4>🚀 Génération en cours...</h4>
                    <p>Veuillez patienter pendant que les posts sont créés.</p>
                    <div class="progress-tips">
                        <small>
                            💡 <strong>Conseil :</strong> Pendant ce temps, vous pouvez préparer le scanner 
                            pour analyser le nouveau contenu une fois la génération terminée.
                        </small>
                    </div>
                </div>
            `);
            
            $('.generation-form').after(progressDiv);
        },

        /**
         * Sauvegarder les préférences utilisateur
         */
        savePreferences: function() {
            const preferences = {
                post_count: $('#post_count').val(),
                post_type: $('#post_type').val(),
                blocks_range: $('#blocks_range').val(),
                include_problematic_blocks: $('#include_problematic_blocks').is(':checked'),
                add_categories: $('#add_categories').is(':checked'),
                add_tags: $('#add_tags').is(':checked')
            };
            
            localStorage.setItem('diagnostic_post_generator_prefs', JSON.stringify(preferences));
        },

        /**
         * Charger les préférences sauvegardées
         */
        loadStoredPreferences: function() {
            const stored = localStorage.getItem('diagnostic_post_generator_prefs');
            
            if (stored) {
                try {
                    const preferences = JSON.parse(stored);
                    
                    // Appliquer les préférences
                    if (preferences.post_count) $('#post_count').val(preferences.post_count);
                    if (preferences.post_type) $('#post_type').val(preferences.post_type);
                    if (preferences.blocks_range) $('#blocks_range').val(preferences.blocks_range);
                    
                    $('#include_problematic_blocks').prop('checked', preferences.include_problematic_blocks !== false);
                    $('#add_categories').prop('checked', preferences.add_categories !== false);
                    $('#add_tags').prop('checked', preferences.add_tags !== false);
                    
                    // Mettre à jour l'estimation
                    this.updateEstimation();
                    
                } catch (e) {
                    // Impossible de charger les préférences sauvegardées
                }
            }
        },

        /**
         * Suivi des performances
         */
        initProgressTracking: function() {
            // Tracking des performances de génération
            if (window.performance && window.performance.mark) {
                $('.generation-form').on('submit', function() {
                    performance.mark('diagnostic-generation-start');
                });
                
                // Quand la page se recharge (après génération)
                $(window).on('load', function() {
                    if (performance.getEntriesByName('diagnostic-generation-start').length > 0) {
                        performance.mark('diagnostic-generation-end');
                        performance.measure('diagnostic-generation-duration', 'diagnostic-generation-start', 'diagnostic-generation-end');
                        
                        const measure = performance.getEntriesByName('diagnostic-generation-duration')[0];
                        // Génération terminée
                    }
                });
            }
        },

        /**
         * Afficher l'aide contextuelle
         */
        showHelp: function(e) {
            e.preventDefault();
            
            const helpContent = `
                <div class="help-modal">
                    <h3>🔍 Guide du générateur de posts</h3>
                    <div class="help-sections">
                        <div class="help-section">
                            <h4>📊 Statistiques</h4>
                            <p>Consultez le nombre de posts de test déjà générés et leur répartition.</p>
                        </div>
                        
                        <div class="help-section">
                            <h4>🚀 Génération</h4>
                            <ul>
                                <li><strong>Nombre de posts :</strong> Commencez avec 10-20 posts pour tester</li>
                                <li><strong>Type de contenu :</strong> Articles ou pages selon vos besoins</li>
                                <li><strong>Blocs par post :</strong> Plus il y en a, plus le test sera intensif</li>
                                <li><strong>Blocs problématiques :</strong> Utiles pour tester la robustesse du scanner</li>
                            </ul>
                        </div>
                        
                        <div class="help-section">
                            <h4>🎯 Recommandations</h4>
                            <ul>
                                <li>Générez d'abord quelques posts pour tester</li>
                                <li>Utilisez le scanner après chaque génération</li>
                                <li>Nettoyez régulièrement les posts de test</li>
                                <li>Surveillez l'espace disque pour de gros volumes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;
            
            // Afficher la modal
            $('body').append('<div class="help-overlay">' + helpContent + '</div>');
            
            // Fermer au clic
            $('.help-overlay').on('click', function(e) {
                if (e.target === this) {
                    $(this).remove();
                }
            });
        }
    };

    /**
     * Utilitaires pour les animations
     */
    const AnimationUtils = {
        
        /**
         * Animer les compteurs
         */
        animateCounters: function() {
            $('.stat-number').each(function() {
                const $this = $(this);
                const finalValue = parseInt($this.text().replace(/[^\d]/g, ''));
                
                if (finalValue > 0) {
                    $this.text('0');
                    
                    $({ value: 0 }).animate({ value: finalValue }, {
                        duration: 1000,
                        easing: 'swing',
                        step: function() {
                            $this.text(Math.floor(this.value).toLocaleString());
                        },
                        complete: function() {
                            $this.text(finalValue.toLocaleString());
                        }
                    });
                }
            });
        },

        /**
         * Effet de apparition pour les cartes
         */
        fadeInCards: function() {
            $('.card').each(function(index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).delay(index * 100).animate({
                    opacity: 1
                }, 300).css('transform', 'translateY(0)');
            });
        }
    };

    /**
     * Validation côté client
     */
    const Validation = {
        
        /**
         * Valider les entrées en temps réel
         */
        init: function() {
            $('#post_count').on('input', this.validatePostCount);
        },

        /**
         * Valider le nombre de posts
         */
        validatePostCount: function() {
            const value = parseInt($(this).val());
            const $feedback = $('.post-count-feedback');
            
            if ($feedback.length === 0) {
                $(this).after('<div class="post-count-feedback"></div>');
            }
            
            if (value > 50) {
                $('.post-count-feedback').html(
                    '<small class="warning-text">⚠️ Un grand nombre de posts peut ralentir votre site</small>'
                ).addClass('warning');
            } else if (value > 100) {
                $('.post-count-feedback').html(
                    '<small class="error-text">❌ Maximum 100 posts autorisés</small>'
                ).addClass('error');
            } else {
                $('.post-count-feedback').html('').removeClass('warning error');
            }
        }
    };

    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        // Vérifier qu'on est sur la bonne page
        if ($('.diagnostic-post-generator').length > 0) {
            PostGenerator.init();
            Validation.init();
            
            // Animations d'entrée
            setTimeout(function() {
                AnimationUtils.animateCounters();
                AnimationUtils.fadeInCards();
            }, 100);
        }
    });

    /**
     * Exposer l'objet globalement pour le debug
     */
    window.DiagnosticPostGenerator = PostGenerator;

})(jQuery);
