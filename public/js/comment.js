document.addEventListener('DOMContentLoaded', () => {
    // Handler for comment forms in modals (index page)
    document.querySelectorAll('.comment-form').forEach(form => {
      form.addEventListener('submit', async e => {
        e.preventDefault();
  
        const postId = form.dataset.postId;
        const url    = form.action;
        const data   = new FormData(form);
  
        // AJAX submission
        try {
          const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
          });
    
          if (!resp.ok) {
            console.error('AJAX Error', resp);
            alert('Error submitting comment');
            return;
          }
    
          const json = await resp.json();
    
          // Build comment HTML
          const commentHtml = `
            <div class="d-flex align-items-start mb-3">
              <img 
                src="${json.user.avatar}" 
                alt="Avatar de ${json.user.nom}" 
                class="rounded-circle me-3" 
                style="width:40px;height:40px;object-fit:cover;"
              >
              <div>
                <strong>${json.user.nom}</strong>
                <p class="mb-1">${json.content}</p>
                <small class="text-muted">${json.createdAt}</small>
              </div>
            </div>`;
    
          // Insert into modal-body of corresponding post
          const modalBody = document
            .querySelector(`#commentsModal${postId} .modal-body`);
          modalBody.insertAdjacentHTML('beforeend', commentHtml);
    
          // Reset input field
          form.querySelector('input[name="comment[content]"]').value = '';
    
          // Update comment count in modal button
          updateIndexCommentCount(postId);
        } catch (error) {
          console.error('Error:', error);
          alert('Error submitting comment');
        }
      });
    });

    // Handler for comment forms in single post view (show.html.twig)
    document.querySelectorAll('.js-add-comment-form').forEach(form => {
      form.addEventListener('submit', async e => {
        e.preventDefault();
  
        const postId = form.dataset.postId;
        const url    = form.action;
        const data   = new FormData(form);
  
        // Disable form during submission
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi...';
        }
        
        try {
          // Send AJAX request
          const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
          });
    
          if (!resp.ok) {
            const errorsContainer = document.getElementById(`comment-errors-${postId}`);
            if (errorsContainer) {
              errorsContainer.textContent = 'Une erreur est survenue lors de l\'envoi du commentaire.';
              errorsContainer.style.display = 'block';
              setTimeout(() => {
                errorsContainer.style.display = 'none';
              }, 4000);
            }
            return;
          }
    
          const json = await resp.json();
    
          // Get the comments container
          const commentsContainer = document.querySelector('.comments-container');
          if (!commentsContainer) return;
          
          // Remove "no comments" message if it exists
          const noCommentsMsg = commentsContainer.querySelector('.text-center.text-muted');
          if (noCommentsMsg && noCommentsMsg.textContent.includes('Aucun commentaire')) {
            noCommentsMsg.remove();
          }
          
          // Create the new comment element
          const commentElement = document.createElement('div');
          commentElement.className = 'd-flex align-items-start mb-3';
          commentElement.style.animation = 'highlight-new-item 2s ease-out';
          
          // Set the HTML for the new comment
          commentElement.innerHTML = `
            <img 
              src="${json.user.avatar || json.user.imageUrl || '/img/screen/user.png'}" 
              alt="Avatar de ${json.user.nom}"
              class="rounded-circle me-2 comment-avatar"
              style="width:32px; height:32px; object-fit:cover;"
            >
            <div class="comment-container w-100 position-relative">
              <strong>${json.user.nom}</strong>
              <p class="comment-content mb-1">${json.content}</p>
              <small class="text-muted">${json.createdAt}</small>
              <div class="comment-actions">
                <button class="btn-icon edit js-edit-comment-btn" 
                        data-comment-id="${json.id}"
                        data-token="${json.tokens?.edit || ''}"
                        data-tooltip="Modifier">
                  <i class="fas fa-edit"></i>
                </button>
                <form action="/comment/delete/${json.id}" 
                      method="post" 
                      class="js-delete-comment-form d-inline" 
                      data-post-id="${postId}">
                  <input type="hidden" name="_token" value="${json.tokens?.delete || ''}">
                  <button type="submit" class="btn-icon delete" data-tooltip="Supprimer">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </div>
            </div>
          `;
          
          // Add it to the top of the comments
          if (commentsContainer.firstChild) {
            commentsContainer.insertBefore(commentElement, commentsContainer.firstChild);
          } else {
            commentsContainer.appendChild(commentElement);
          }
          
          // Reset the form
          form.querySelector('input[name="content"]').value = '';
          
          // Add event listeners to the new comment's buttons
          const editBtn = commentElement.querySelector('.js-edit-comment-btn');
          if (editBtn) {
            editBtn.addEventListener('click', handleEditComment);
          }
          
          const deleteForm = commentElement.querySelector('.js-delete-comment-form');
          if (deleteForm) {
            deleteForm.addEventListener('submit', handleDeleteComment);
          }
          
        } catch (error) {
          console.error('Error submitting comment:', error);
          const errorsContainer = document.getElementById(`comment-errors-${postId}`);
          if (errorsContainer) {
            errorsContainer.textContent = 'Une erreur est survenue: ' + error.message;
            errorsContainer.style.display = 'block';
          }
        } finally {
          // Re-enable form
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Ajouter';
          }
        }
      });
    });
    
    // Event handlers for edit and delete buttons
    function handleEditComment(e) {
      e.preventDefault();
      // Your existing edit comment logic
      // ...
    }
    
    function handleDeleteComment(e) {
      e.preventDefault();
      if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
        // Your existing delete comment logic
        // ...
      }
    }
    
    // Handler for comment deletion
    document.body.addEventListener('click', function(e) {
      if (e.target.closest('.js-delete-comment-form button')) {
        const form = e.target.closest('.js-delete-comment-form');
        if (form) {
          e.preventDefault();
          if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
            const commentElement = form.closest('.d-flex.align-items-start');
            const postId = form.dataset.postId;
            
            fetch(form.action, {
              method: 'POST',
              body: new FormData(form),
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                commentElement.remove();
                // Décrémenter le compteur de commentaires
                decrementCommentCount(postId);
              } else {
                alert(data.message || 'Erreur lors de la suppression');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Une erreur est survenue');
            });
          }
        }
      }
    });
    
    // Function to update comment count in index page
    function updateIndexCommentCount(postId) {
      // Mettre à jour le compteur dans le bouton d'ouverture du modal
      const btn = document.querySelector(`button[data-bs-toggle="modal"][data-bs-target="#commentsModal${postId}"]`);
      if (btn) {
        const countElement = btn.querySelector('.comment-count');
        if (countElement) {
          // Incrémenter le compteur
          const currentCount = parseInt(countElement.textContent.trim()) || 0;
          countElement.textContent = currentCount + 1;
          
          // Ajouter une animation
          countElement.classList.add('highlight');
          setTimeout(() => {
            countElement.classList.remove('highlight');
          }, 800);
        } else {
          // S'il n'y a pas d'élément dédié au compteur, mettre à jour le texte du bouton
          const currentText = btn.textContent.trim();
          const match = currentText.match(/Commentaires?\s*\((\d+)\)/i);
          
          if (match) {
            // Incrémenter le compteur existant
            const newCount = parseInt(match[1]) + 1;
            btn.textContent = currentText.replace(/\(\d+\)/, `(${newCount})`);
          } else if (currentText.toLowerCase().includes('commentaire')) {
            // Ajouter un compteur s'il n'y a que le texte "Commentaire(s)"
            btn.textContent = `${currentText} (1)`;
          }
        }
      }
    }
    
    // Function to decrement comment count
    function decrementCommentCount(postId) {
      // Décrémenter le compteur dans le bouton d'ouverture du modal
      const btns = document.querySelectorAll(`button[data-bs-toggle="modal"][data-bs-target="#commentsModal${postId}"]`);
      btns.forEach(btn => {
        const countElement = btn.querySelector('.comment-count');
        if (countElement) {
          // Décrémenter le compteur
          const currentCount = parseInt(countElement.textContent.trim()) || 0;
          if (currentCount > 0) {
            countElement.textContent = currentCount - 1;
          }
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
                <div class="comment-content mb-1 ${isSensitive ? 'sensitive-content' : ''}">
                    ${commentData.content}
                </div>
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
  });
  