// Fonction pour afficher une alerte
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.comments-container');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Supprimer l'alerte après 5 secondes
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Fonction pour ajouter un nouveau commentaire à la liste
function addCommentToDOM(commentData) {
    const commentHtml = `
        <div class="comment-box" data-comment-id="${commentData.id}">
            <div class="comment-header">
                <img src="${commentData.user.avatar}" alt="Avatar" class="comment-avatar">
                <div class="comment-info">
                    <span class="comment-username">${commentData.user.nom}</span>
                    <span class="comment-date">${commentData.createdAt}</span>
                </div>
            </div>
            
            <div class="comment-content ${commentData.toxicityScore > 0.4 ? 'sensitive-content' : ''}">
                ${commentData.content}
            </div>
            
            <div class="comment-actions">
                <button class="btn-edit-comment" data-token="${commentData.tokens.edit}">
                    <i class="fas fa-edit"></i> Modifier
                </button>
                <button class="btn-delete-comment" data-token="${commentData.tokens.delete}">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            </div>
        </div>
    `;
    
    const container = document.querySelector('.comments-container');
    container.insertAdjacentHTML('beforeend', commentHtml);
}

// Gestion de la soumission du formulaire de commentaire
document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.querySelector('#comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const postId = this.dataset.postId;
            
            fetch(`/comment/${postId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    if (data.toxicityScore > 0.6) {
                        showAlert('danger', 'Votre commentaire a été jugé trop toxique et a été supprimé. Un email vous a été envoyé avec les détails.');
                    } else {
                        showAlert('warning', data.error);
                    }
                } else {
                    addCommentToDOM(data);
                    showAlert('success', 'Votre commentaire a été ajouté avec succès.');
                    this.reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Une erreur est survenue lors de l\'envoi du commentaire.');
            });
        });
    }
    
    // Gestion de l'édition des commentaires
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-edit-comment')) {
            const commentBox = e.target.closest('.comment-box');
            const commentId = commentBox.dataset.commentId;
            const token = e.target.closest('.btn-edit-comment').dataset.token;
            
            // Afficher le formulaire d'édition
            const contentDiv = commentBox.querySelector('.comment-content');
            const originalContent = contentDiv.textContent.trim();
            
            const editForm = document.createElement('form');
            editForm.className = 'edit-comment-form';
            editForm.innerHTML = `
                <textarea class="form-control">${originalContent}</textarea>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <button type="button" class="btn btn-secondary cancel-edit">Annuler</button>
            `;
            
            contentDiv.innerHTML = '';
            contentDiv.appendChild(editForm);
            
            // Gestion de l'annulation
            editForm.querySelector('.cancel-edit').addEventListener('click', function() {
                contentDiv.textContent = originalContent;
            });
            
            // Gestion de la soumission
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const newContent = this.querySelector('textarea').value;
                
                fetch(`/comment/${commentId}/edit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        content: newContent,
                        _token: token
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        contentDiv.textContent = data.content;
                        showAlert('success', 'Commentaire modifié avec succès.');
                    } else {
                        showAlert('danger', data.error || 'Erreur lors de la modification du commentaire.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Une erreur est survenue lors de la modification du commentaire.');
                });
            });
        }
    });
}); 