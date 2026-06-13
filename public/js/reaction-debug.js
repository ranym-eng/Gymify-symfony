/**
 * Script de débogage des réactions
 * Ce script aide à diagnostiquer et résoudre les problèmes avec le système de réactions
 */
(function() {
    console.log('Reaction Debug Helper script loaded');
    
    // Fonction pour intercepter l'API fetch originale et ajouter du logging
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        // Ne logger que les requêtes de réaction
        if (url && url.includes('/reaction/')) {
            console.log('%c📨 FETCH REQUEST:', 'color: blue; font-weight: bold', {
                url,
                method: options?.method || 'GET',
                body: options?.body,
                headers: options?.headers
            });
            
            // Si c'est une requête POST avec un FormData, afficher son contenu
            if (options?.method === 'POST' && options?.body instanceof FormData) {
                console.log('%c📝 FormData contents:', 'color: purple');
                for (const pair of options.body.entries()) {
                    console.log(`   ${pair[0]}: ${pair[1]}`);
                }
            }
            
            // Ajouter X-Requested-With si non présent
            if (options && !options.headers) {
                options.headers = {
                    'X-Requested-With': 'XMLHttpRequest'
                };
            } else if (options && options.headers && !options.headers['X-Requested-With']) {
                options.headers['X-Requested-With'] = 'XMLHttpRequest';
            }
        }
        
        return originalFetch.apply(this, arguments)
            .then(response => {
                if (url && url.includes('/reaction/')) {
                    console.log('%c📩 FETCH RESPONSE:', 'color: green; font-weight: bold', {
                        url,
                        status: response.status,
                        statusText: response.statusText,
                        ok: response.ok
                    });
                    
                    // Cloner la réponse pour pouvoir la lire et la retourner
                    const clone = response.clone();
                    clone.json().then(data => {
                        console.log('%c🔍 RESPONSE DATA:', 'color: orange', data);
                    }).catch(err => {
                        console.error('Could not parse response as JSON', err);
                    });
                }
                
                return response;
            })
            .catch(error => {
                if (url && url.includes('/reaction/')) {
                    console.error('%c❌ FETCH ERROR:', 'color: red; font-weight: bold', {
                        url,
                        error
                    });
                }
                
                throw error;
            });
    };
    
    // Observer les clics sur les boutons de réaction
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('react-option') || e.target.closest('.react-option')) {
            const target = e.target.classList.contains('react-option') ? e.target : e.target.closest('.react-option');
            console.log('%c👆 REACTION CLICK:', 'color: magenta; font-weight: bold', {
                target,
                type: target.dataset.type,
                postId: target.closest('.post-buttons-container')?.querySelector('.btn-react-toggle')?.dataset?.postId,
                csrfToken: target.closest('.post-buttons-container')?.querySelector('.btn-react-toggle')?.dataset?.csrfToken
            });
        }
    }, true);
    
    // Vérifier la présence et l'état des éléments de réaction sur la page
    function checkReactionElements() {
        console.log('%c🧐 CHECKING REACTION ELEMENTS:', 'color: teal; font-weight: bold');
        
        const toggleButtons = document.querySelectorAll('.btn-react-toggle');
        console.log(`Found ${toggleButtons.length} reaction toggle buttons`);
        
        toggleButtons.forEach((btn, index) => {
            console.log(`Button #${index+1}:`, {
                element: btn,
                postId: btn.dataset.postId,
                csrfToken: btn.dataset.csrfToken,
                active: btn.classList.contains('active')
            });
        });
        
        const reactionBars = document.querySelectorAll('.reaction-bar');
        console.log(`Found ${reactionBars.length} reaction bars`);
        
        const reactionOptions = document.querySelectorAll('.react-option');
        console.log(`Found ${reactionOptions.length} reaction options`);
    }
    
    // Exécuter la vérification après le chargement complet de la page
    window.addEventListener('load', checkReactionElements);
    
    // Exposer des fonctions utiles pour le débogage via la console
    window.reactionDebug = {
        checkElements: checkReactionElements,
        testReaction: function(postId, type) {
            console.log(`Testing reaction ${type} on post ${postId}`);
            const btn = document.querySelector(`.btn-react-toggle[data-post-id="${postId}"]`);
            if (!btn) {
                console.error(`No button found for post ${postId}`);
                return;
            }
            
            const token = btn.dataset.csrfToken || `reaction${postId}_debug`;
            const url = `/reaction/${postId}`;
            
            const formData = new FormData();
            formData.append('type', type);
            formData.append('_token', token);
            
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => console.log('Test reaction result:', data))
            .catch(err => console.error('Test reaction error:', err));
        },
        
        clearPendingReactions: function() {
            console.log('Clearing all pending reactions');
            if (window.pendingReactions) {
                for (const key in window.pendingReactions) {
                    delete window.pendingReactions[key];
                }
                console.log('All pending reactions cleared');
            } else {
                console.log('No pendingReactions object found');
            }
            return 'Done';
        },
        
        resetUI: function(postId) {
            console.log(`Resetting UI for post ${postId}`);
            const buttons = document.querySelectorAll(`.btn-react-toggle[data-post-id="${postId}"]`);
            
            if (!buttons.length) {
                console.error(`No buttons found for post ${postId}`);
                return;
            }
            
            buttons.forEach(button => {
                // Remove active class
                button.classList.remove('active');
                
                // Remove any reaction spans
                const spans = button.querySelectorAll('span[class^="reaction-"]');
                spans.forEach(span => span.remove());
                
                // Add default icon if not present
                if (!button.querySelector('i.far')) {
                    const icon = document.createElement('i');
                    icon.className = 'far fa-thumbs-up';
                    button.prepend(icon);
                }
                
                // Reset count
                const countSpan = button.querySelector('.reaction-count');
                if (countSpan) {
                    countSpan.textContent = '0';
                }
            });
            
            return 'UI reset complete';
        },
        
        forceReload: function() {
            console.log('Forcing page reload to refresh reaction state from server');
            location.reload();
        }
    };
    
    console.log('Reaction Debug Helper initialized. Use window.reactionDebug in console for debugging.');
})(); 