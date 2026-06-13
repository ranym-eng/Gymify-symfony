document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la soumission du formulaire de commentaire
    const commentForm = document.querySelector('.comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const postId = this.dataset.postId;
            
            try {
                const response = await fetch(`/comment/${postId}`, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showAlert(data.error, 'error');
                } else {
                    // Ajouter le commentaire à la liste
                    addCommentToDOM(data);
                    showAlert('Commentaire ajouté avec succès', 'success');
                    // Réinitialiser le formulaire
                    this.reset();
                }
            } catch (error) {
                showAlert('Une erreur est survenue', 'error');
                console.error('Erreur:', error);
            }
        });
    }
    
    // Fonction pour ajouter un commentaire au DOM
    function addCommentToDOM(commentData) {
        const commentBox = document.createElement('div');
        commentBox.className = 'd-flex align-items-start mb-3 comment-box';
        commentBox.dataset.commentId = commentData.id;
        
        const isSensitive = commentData.toxicityScore > 0.4;
        
        commentBox.innerHTML = `
            <img 
                src="${commentData.user.avatar}" 
                alt="Avatar commentateur" 
                class="rounded-circle me-2 comment-avatar" 
                style="width:32px; height:32px; object-fit:cover;"
            >
            <div class="w-100">
                <strong class="comment-username">${commentData.user.nom}</strong>
                <p class="mb-1 comment-content ${isSensitive ? 'sensitive-content' : ''}">${commentData.content}</p>
                <small class="text-muted comment-date">${commentData.createdAt}</small>
                
                <div class="mt-1 comment-actions">
                    <button class="btn-edit-comment" data-comment-id="${commentData.id}" data-token="${commentData.tokens.edit}">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <button class="btn-delete-comment" data-comment-id="${commentData.id}" data-token="${commentData.tokens.delete}">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
            </div>
        `;
        
        // Ajouter le commentaire au début de la liste
        const commentsContainer = document.querySelector('.comments-container');
        if (commentsContainer) {
            commentsContainer.insertBefore(commentBox, commentsContainer.firstChild);
        }
    }
    
    // Gestion des boutons d'édition et de suppression
    document.body.addEventListener('click', function(e) {
        // Gestion du bouton d'édition
        if (e.target.closest('.btn-edit-comment')) {
            const button = e.target.closest('.btn-edit-comment');
            const commentId = button.dataset.commentId;
            const token = button.dataset.token;
            const commentBox = button.closest('.comment-box');
            const contentElement = commentBox.querySelector('.comment-content');
            const originalContent = contentElement.textContent.trim();
            
            // Créer un formulaire d'édition
            const form = document.createElement('form');
            form.className = 'edit-form';
            form.innerHTML = `
                <textarea class="form-control mb-2">${originalContent}</textarea>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    <button type="button" class="btn btn-secondary btn-sm cancel-edit">Annuler</button>
                </div>
            `;
            
            // Remplacer le contenu par le formulaire
            contentElement.innerHTML = '';
            contentElement.appendChild(form);
            
            // Annulation de l'édition
            form.querySelector('.cancel-edit').addEventListener('click', function() {
                contentElement.innerHTML = originalContent;
            });
            
            // Soumission du formulaire d'édition
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const newContent = this.querySelector('textarea').value;
                
                try {
                    const response = await fetch(`/comment/${commentId}/edit`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            content: newContent,
                            _token: token
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        contentElement.innerHTML = data.content;
                        showAlert('Commentaire modifié avec succès', 'success');
                    } else {
                        contentElement.innerHTML = originalContent;
                        showAlert(data.error || 'Erreur lors de la modification', 'error');
                    }
                } catch (error) {
                    contentElement.innerHTML = originalContent;
                    showAlert('Une erreur est survenue', 'error');
                    console.error('Erreur:', error);
                }
            });
        }
        
        // Gestion du bouton de suppression
        if (e.target.closest('.btn-delete-comment')) {
            const button = e.target.closest('.btn-delete-comment');
            const commentId = button.dataset.commentId;
            const token = button.dataset.token;
            const commentBox = button.closest('.comment-box');
            
            if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
                deleteComment(commentId, token, commentBox);
            }
        }
    });
    
    // Fonction pour supprimer un commentaire
    async function deleteComment(commentId, token, commentBox) {
        try {
            const formData = new FormData();
            formData.append('_token', token);
            
            const response = await fetch(`/comment/delete/${commentId}`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                commentBox.remove();
                showAlert('Commentaire supprimé avec succès', 'success');
            } else {
                showAlert(data.message || 'Erreur lors de la suppression', 'error');
            }
        } catch (error) {
            showAlert('Une erreur est survenue', 'error');
            console.error('Erreur:', error);
        }
    }
});

// Fonction pour afficher les alertes
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    // Supprimer l'alerte après 3 secondes
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            alertDiv.remove();
        }, 300);
    }, 3000);
} 