document.addEventListener('DOMContentLoaded', function() {
    // Create the dropdown element for suggestions
    const mentionDropdown = document.createElement('div');
    mentionDropdown.className = 'mention-dropdown';
    mentionDropdown.style.display = 'none';
    document.body.appendChild(mentionDropdown);

    // Input fields to watch for @ mentions (both in modals and on post page)
    const commentInputs = document.querySelectorAll('.comment-form input[name="comment[content]"], .js-add-comment-form input[name="content"]');
    
    // Track the current input being used
    let currentInput = null;
    let mentionStart = -1;
    let mentionText = '';
    
    // Apply the mention functionality to each input
    commentInputs.forEach(input => {
        input.addEventListener('input', handleInput);
        input.addEventListener('keydown', handleKeydown);
        input.addEventListener('blur', function(e) {
            // Don't hide immediately to allow clicking on the dropdown
            setTimeout(() => {
                if (!mentionDropdown.contains(document.activeElement)) {
                    mentionDropdown.style.display = 'none';
                }
            }, 200);
        });
    });
    
    // Handle input to detect @ mentions
    function handleInput(e) {
        currentInput = e.target;
        const cursorPos = currentInput.selectionStart;
        const text = currentInput.value;
        
        // Find the @ character before the cursor
        const beforeCursor = text.substring(0, cursorPos);
        mentionStart = beforeCursor.lastIndexOf('@');
        
        // If we have an @ and it's either at the start or has a space before it
        if (mentionStart >= 0 && (mentionStart === 0 || beforeCursor[mentionStart-1] === ' ')) {
            mentionText = beforeCursor.substring(mentionStart + 1);
            
            // If there's text after the @, fetch matching users
            if (mentionText.length > 0) {
                fetchUsers(mentionText);
            } else {
                mentionDropdown.style.display = 'none';
            }
        } else {
            mentionDropdown.style.display = 'none';
        }
    }
    
    // Handle keyboard navigation in the dropdown
    function handleKeydown(e) {
        if (mentionDropdown.style.display === 'none') return;
        
        // If dropdown is visible, handle navigation
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            
            const items = mentionDropdown.querySelectorAll('.mention-item');
            const active = mentionDropdown.querySelector('.mention-item.active');
            let index = -1;
            
            if (active) {
                index = Array.from(items).indexOf(active);
                active.classList.remove('active');
            }
            
            if (e.key === 'ArrowDown') {
                index = (index + 1) % items.length;
            } else {
                index = (index - 1 + items.length) % items.length;
            }
            
            items[index].classList.add('active');
            items[index].scrollIntoView({ block: 'nearest' });
        } 
        // Select the current item on Enter
        else if (e.key === 'Enter' && mentionDropdown.style.display !== 'none') {
            e.preventDefault();
            const active = mentionDropdown.querySelector('.mention-item.active');
            if (active) {
                selectUser(active.dataset.username);
            }
        }
        // Close dropdown on Escape
        else if (e.key === 'Escape') {
            mentionDropdown.style.display = 'none';
        }
    }
    
    // Fetch users matching the query
    function fetchUsers(query) {
        // Use a simple fetch to get users that match the query
        fetch(`/api/users/search?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(users => {
                if (users.length > 0) {
                    displayUsers(users);
                } else {
                    mentionDropdown.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                mentionDropdown.style.display = 'none';
            });
    }
    
    // Display the matching users in the dropdown
    function displayUsers(users) {
        // Clear previous results
        mentionDropdown.innerHTML = '';
        
        // Position the dropdown below the current input
        const inputRect = currentInput.getBoundingClientRect();
        mentionDropdown.style.position = 'absolute';
        mentionDropdown.style.top = (window.scrollY + inputRect.bottom) + 'px';
        mentionDropdown.style.left = inputRect.left + 'px';
        mentionDropdown.style.width = inputRect.width + 'px';
        mentionDropdown.style.maxHeight = '200px';
        mentionDropdown.style.overflowY = 'auto';
        mentionDropdown.style.backgroundColor = '#fff';
        mentionDropdown.style.border = '1px solid #ddd';
        mentionDropdown.style.borderRadius = '4px';
        mentionDropdown.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        mentionDropdown.style.zIndex = '1050';
        
        // Add users to the dropdown
        users.forEach((user, index) => {
            const item = document.createElement('div');
            item.className = 'mention-item';
            if (index === 0) item.classList.add('active');
            
            // Create the full display name
            const fullName = user.prenom 
                ? `${user.nom} ${user.prenom}` 
                : user.nom;
            
            // Store the full name in the dataset
            item.dataset.username = fullName;
            
            item.innerHTML = `
                <img src="${user.imageUrl || '/img/screen/user.png'}" 
                     alt="Avatar" 
                     style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px;">
                <span>${fullName}</span>
            `;
            
            item.style.padding = '8px 12px';
            item.style.cursor = 'pointer';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.transition = 'background-color 0.2s';
            
            item.addEventListener('mouseenter', () => {
                mentionDropdown.querySelectorAll('.mention-item.active').forEach(el => {
                    el.classList.remove('active');
                });
                item.classList.add('active');
            });
            
            item.addEventListener('click', () => {
                selectUser(fullName);
            });
            
            mentionDropdown.appendChild(item);
        });
        
        mentionDropdown.style.display = 'block';
    }
    
    // Select a user from the dropdown and update the input
    function selectUser(username) {
        if (!currentInput) return;
        
        const text = currentInput.value;
        const beforeMention = text.substring(0, mentionStart);
        const afterMention = text.substring(currentInput.selectionStart);
        
        // Replace the @mention with the selected username
        currentInput.value = beforeMention + '@' + username + ' ' + afterMention;
        
        // Place cursor after the inserted mention
        const newCursorPos = mentionStart + username.length + 2; // +2 for @ and space
        currentInput.setSelectionRange(newCursorPos, newCursorPos);
        
        // Hide the dropdown
        mentionDropdown.style.display = 'none';
        
        // Focus back on the input
        currentInput.focus();
    }
});

// Add CSS for the mention items
document.head.insertAdjacentHTML('beforeend', `
<style>
    .mention-item {
        padding: 8px 12px;
        cursor: pointer;
    }
    .mention-item:hover,
    .mention-item.active {
        background-color: #f0f7ff;
    }
    
    /* Animation for dropdown */
    .mention-dropdown {
        animation: fadeIn 0.2s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
`); 