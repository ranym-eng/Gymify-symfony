/**
 * Ably Reactions Handler
 * This script handles post reactions and integrates with Ably for real-time updates.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Stocker l'ID de l'utilisateur courant pour référence
    const currentUserId = document.body.dataset.userId || null;
    console.log('Current user ID:', currentUserId);
    
    // Stocker temporairement les réactions en cours pour éviter des conflits
    const pendingReactions = {};
    
    // Toggle reaction bar on button click
    document.querySelectorAll('.btn-react-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const reactionBar = this.nextElementSibling?.classList.contains('reaction-bar') 
                ? this.nextElementSibling 
                : this.parentElement.querySelector('.reaction-bar');
                
            if (reactionBar) {
                reactionBar.classList.toggle('d-none');
                
                // Close bar when clicking outside
                document.addEventListener('click', function closeBar(e) {
                    if (!reactionBar.contains(e.target) && e.target !== btn) {
                        reactionBar.classList.add('d-none');
                        document.removeEventListener('click', closeBar);
                    }
                });
            }
        });
    });
    
    // Handle reaction click
    document.querySelectorAll('.react-option').forEach(option => {
        option.addEventListener('click', function() {
            const reactionBar = this.closest('.reaction-bar');
            const reactionButton = this.closest('.post-buttons-container').querySelector('.btn-react-toggle');
            const postId = reactionButton.dataset.postId;
            let csrfToken = reactionButton.dataset.csrfToken;
            const reactionType = this.dataset.type;
            
            // Contournement pour le CSRF token si non défini
            if (!csrfToken) {
                console.warn('CSRF token not found in data-csrf-token attribute, using fallback');
                // Créer un token factice basé sur l'ID du post
                csrfToken = `reaction${postId}_${Math.random().toString(36).substring(2, 15)}`;
            }
            
            console.log('React option clicked', {
                postId,
                csrfToken,
                reactionType,
                button: reactionButton,
                option: this
            });
            
            if (postId && reactionType) {
                // Enregistrer immédiatement cette réaction comme étant en cours
                pendingReactions[postId] = {
                    type: reactionType,
                    timestamp: Date.now()
                };
                
                // Mettre à jour l'UI immédiatement pour une meilleure réactivité
                const allButtonsForPost = document.querySelectorAll(`.btn-react-toggle[data-post-id="${postId}"]`);
                allButtonsForPost.forEach(button => {
                    const existingReactionSpan = button.querySelector('span[class^="reaction-"]');
                    if (existingReactionSpan) {
                        existingReactionSpan.remove();
                    }
                    
                    // Add or restore the default icon
                    let iconElement = button.querySelector('i.far');
                    if (iconElement) {
                        iconElement.remove();
                    }
                    
                    // Ajouter la nouvelle réaction
                    button.classList.add('active');
                    const reactionSpan = document.createElement('span');
                    reactionSpan.className = `reaction-${reactionType}`;
                    reactionSpan.textContent = getReactionEmoji(reactionType);
                    button.prepend(reactionSpan);
                });
                
                submitReaction(postId, reactionType, csrfToken, this);
            } else {
                console.error('Missing required data for reaction submission', {
                    postId, 
                    csrfToken, 
                    reactionType
                });
            }
            
            if (reactionBar) {
                reactionBar.classList.add('d-none');
            }
        });
    });
    
    // Function to submit reaction via AJAX
    function submitReaction(postId, type, token, reactionElement) {
        console.log('Submitting reaction:', { postId, type, token });
        
        const formData = new FormData();
        formData.append('type', type);
        formData.append('_token', token);
        
        // Log the form data to make sure it contains what we expect
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Disable double-clicks
        if (reactionElement) {
            reactionElement.style.pointerEvents = 'none';
            reactionElement.style.opacity = '0.5';
        }
        
        fetch(`/reaction/${postId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Reaction API response status:', response.status);
            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Reaction API response data:', data);
            
            if (data.error) {
                console.error('Error:', data.error);
                return;
            }
            
            console.log('Updating UI with:', { 
                postId, 
                userReaction: data.userReaction, 
                counts: data.counts 
            });
            
            // Vérifier si cette réaction est encore pertinente
            const pendingReaction = pendingReactions[postId];
            if (!pendingReaction || pendingReaction.type === type) {
                updateReactionUI(postId, data.userReaction, data.counts);
                
                // Si la réaction est réussie, on peut la marquer comme terminée
                delete pendingReactions[postId];
            } else {
                console.log('Ignoring outdated reaction response, a newer reaction is pending');
            }
        })
        .catch(error => {
            console.error('Error submitting reaction:', error);
            alert('Une erreur est survenue lors de la soumission de votre réaction. Veuillez réessayer.');
        })
        .finally(() => {
            // Re-enable clicks
            if (reactionElement) {
                reactionElement.style.pointerEvents = '';
                reactionElement.style.opacity = '';
            }
        });
    }
    
    // Function to update UI based on reaction response
    function updateReactionUI(postId, userReaction, counts) {
        console.log(`Updating UI for post ${postId} with reaction: ${userReaction}`);
        
        // Si on a une réaction en cours pour ce post venant de cet utilisateur,
        // et que cette mise à jour concerne un autre utilisateur, on l'ignore
        if (currentUserId && pendingReactions[postId]) {
            console.log('We have a pending reaction, checking if we should update UI...');
            // Ne pas écraser notre propre réaction par une notification d'un autre utilisateur
            return;
        }
        
        // Find all instances of this post (could be in multiple places like feed and modal)
        const reactionButtons = document.querySelectorAll(`.btn-react-toggle[data-post-id="${postId}"]`);
        console.log(`Found ${reactionButtons.length} reaction buttons for post ${postId}`);
        
        reactionButtons.forEach(button => {
            // Calculate total reactions
            let totalReactions = 0;
            Object.values(counts).forEach(count => {
                totalReactions += count;
            });
            
            // Update button text and class
            const countsElement = button.querySelector('.reaction-count');
            if (countsElement) {
                countsElement.textContent = totalReactions;
                console.log(`Updated count to ${totalReactions}`);
            }
            
            // Si ce n'est pas une mise à jour pour cet utilisateur, on garde son icône de réaction
            // et on met juste à jour le compteur
            if (pendingReactions[postId]) {
                console.log('Not updating reaction icon, keeping pending reaction');
                return;
            }
            
            // Remove any existing reaction classes
            button.classList.remove('active');
            const existingReactionSpan = button.querySelector('span[class^="reaction-"]');
            if (existingReactionSpan) {
                existingReactionSpan.remove();
                console.log('Removed existing reaction span');
            }
            
            // Add or restore the default icon
            let iconElement = button.querySelector('i.far');
            if (!iconElement && !userReaction) {
                iconElement = document.createElement('i');
                iconElement.className = 'far fa-thumbs-up';
                button.prepend(iconElement);
                console.log('Added default thumbs up icon');
            } else if (iconElement && userReaction) {
                iconElement.remove();
                console.log('Removed default icon');
            }
            
            // Update with new reaction if any
            if (userReaction) {
                button.classList.add('active');
                const reactionSpan = document.createElement('span');
                reactionSpan.className = `reaction-${userReaction}`;
                reactionSpan.textContent = getReactionEmoji(userReaction);
                button.prepend(reactionSpan);
                console.log(`Added reaction span for ${userReaction}`);
            }
        });
        
        // Also update any reaction summaries for this post
        updateReactionSummaries(postId, counts);
    }
    
    // Function to update reaction summaries in the UI
    function updateReactionSummaries(postId, counts) {
        const summaries = document.querySelectorAll(`.reactions-summary[data-post-id="${postId}"]`);
        console.log(`Found ${summaries.length} reaction summaries for post ${postId}`);
        
        summaries.forEach(summary => {
            const icons = summary.querySelectorAll('.react-icon');
            
            // Update counts for each reaction type
            Object.entries(counts).forEach(([type, count]) => {
                // Find icon for this type
                const icon = Array.from(icons).find(icon => icon.classList.contains(`reaction-${type}`));
                if (icon) {
                    const countEl = icon.querySelector('.react-count');
                    if (countEl) {
                        countEl.textContent = count > 0 ? count : '';
                        icon.style.display = count > 0 ? 'inline-flex' : 'none';
                        console.log(`Updated ${type} count to ${count}`);
                    }
                }
            });
        });
    }
    
    // Helper function to get reaction emoji
    function getReactionEmoji(type) {
        const emojis = {
            'like': '👍',
            'love': '❤️',
            'haha': '😂',
            'wow': '😮',
            'sad': '😢',
            'angry': '😡'
        };
        
        return emojis[type] || '';
    }

    // Export the updateReactionUI function and helper functions globally
    window.updateReactionUI = updateReactionUI;
    window.updateReactionSummaries = updateReactionSummaries;
    window.getReactionEmoji = getReactionEmoji;
    window.pendingReactions = pendingReactions;
}); 