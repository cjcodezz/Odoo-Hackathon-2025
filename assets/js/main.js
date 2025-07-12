// Enhanced JavaScript for StackIt Platform
class StackItApp {
    constructor() {
        this.notificationDropdown = null;
        this.userDropdown = null;
        this.mobileMenu = null;
        this.notificationInterval = null;
        this.init();
    }

    init() {
        this.initializeComponents();
        this.setupEventListeners();
        this.loadNotifications();
        this.startNotificationPolling();
        this.initializeScrollEffects();
    }

    initializeComponents() {
        this.notificationDropdown = document.getElementById('notificationDropdown');
        this.userDropdown = document.getElementById('userDropdown');
        this.mobileMenu = document.getElementById('mobileMenu');
        
        // Initialize TinyMCE if elements exist
        this.initializeTinyMCE();
    }

    initializeTinyMCE() {
        // Initialize TinyMCE for description textarea
        if (document.getElementById('description')) {
            tinymce.init({
                selector: '#description',
                height: 300,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
                skin: 'oxide',
                content_css: 'default',
                branding: false,
                promotion: false,
                setup: function(editor) {
                    editor.on('change', function() {
                        editor.save();
                    });
                }
            });
        }

        // Initialize TinyMCE for answer content textarea
        if (document.querySelector('textarea[name="content"]')) {
            tinymce.init({
                selector: 'textarea[name="content"]',
                height: 250,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
                skin: 'oxide',
                content_css: 'default',
                branding: false,
                promotion: false,
                setup: function(editor) {
                    editor.on('change', function() {
                        editor.save();
                    });
                }
            });
        }
    }

    setupEventListeners() {
        // Close dropdowns when clicking outside
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.notification-wrapper')) {
                this.closeNotifications();
            }
            if (!event.target.closest('.user-menu')) {
                this.closeUserMenu();
            }
            if (!event.target.closest('.header-content') && this.mobileMenu) {
                this.closeMobileMenu();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeNotifications();
                this.closeUserMenu();
                this.closeMobileMenu();
            }
        });

        // Search functionality
        this.initializeSearch();

        // Form enhancements
        this.enhanceForms();
    }

    initializeScrollEffects() {
        const header = document.querySelector('.header');
        let lastScrollY = window.scrollY;

        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            
            if (currentScrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScrollY = currentScrollY;
        });
    }

    enhanceForms() {
        // Add floating label effect
        const inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            if (input.value) {
                input.classList.add('has-value');
            }

            input.addEventListener('focus', () => {
                input.classList.add('focused');
            });

            input.addEventListener('blur', () => {
                input.classList.remove('focused');
                if (input.value) {
                    input.classList.add('has-value');
                } else {
                    input.classList.remove('has-value');
                }
            });
        });
    }

    // Mobile menu functions
    toggleMobileMenu() {
        if (this.mobileMenu) {
            this.mobileMenu.classList.toggle('active');
        }
    }

    closeMobileMenu() {
        if (this.mobileMenu) {
            this.mobileMenu.classList.remove('active');
        }
    }

    // Notification functions
    toggleNotifications() {
        if (this.notificationDropdown) {
            if (this.notificationDropdown.classList.contains('active')) {
                this.closeNotifications();
            } else {
                this.openNotifications();
            }
        }
    }

    openNotifications() {
        if (this.notificationDropdown) {
            this.closeUserMenu();
            this.notificationDropdown.classList.add('active');
            this.markNotificationsAsRead();
        }
    }

    closeNotifications() {
        if (this.notificationDropdown) {
            this.notificationDropdown.classList.remove('active');
        }
    }

    async loadNotifications() {
        try {
            const response = await fetch('api/notifications.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateNotificationUI(data.notifications, data.unread_count);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    updateNotificationUI(notifications, unreadCount) {
        // Update notification count
        const countElement = document.getElementById('notificationCount');
        if (countElement) {
            countElement.textContent = unreadCount;
            if (unreadCount > 0) {
                countElement.classList.add('show');
            } else {
                countElement.classList.remove('show');
            }
        }
        
        // Update notification dropdown
        if (this.notificationDropdown) {
            let html = `
                <div class="notification-header">
                    <i class="fas fa-bell"></i>
                    Notifications
                    ${unreadCount > 0 ? `<span class="notification-count show">${unreadCount}</span>` : ''}
                </div>
            `;
            
            if (notifications.length > 0) {
                notifications.forEach(notification => {
                    const unreadClass = notification.is_read == 0 ? 'unread' : '';
                    html += `
                        <div class="notification-item ${unreadClass}" onclick="app.handleNotificationClick(${notification.id}, '${notification.type}', ${notification.related_id})">
                            <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                            <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                            <div class="notification-time">
                                <i class="fas fa-clock"></i>
                                ${this.timeAgo(notification.created_at)}
                            </div>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet</p>
                    </div>
                `;
            }
            
            this.notificationDropdown.innerHTML = html;
        }
    }

    async markNotificationsAsRead() {
        try {
            const response = await fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'mark_read' })
            });
            
            const data = await response.json();
            if (data.success) {
                // Update UI to show all notifications as read
                const unreadItems = this.notificationDropdown.querySelectorAll('.notification-item.unread');
                unreadItems.forEach(item => item.classList.remove('unread'));
                
                // Hide notification count
                const countElement = document.getElementById('notificationCount');
                if (countElement) {
                    countElement.classList.remove('show');
                }
            }
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }

    async handleNotificationClick(notificationId, type, relatedId) {
        // Navigate based on notification type
        if (type === 'answer' && relatedId) {
            try {
                const response = await fetch(`api/get_question_from_answer.php?answer_id=${relatedId}`);
                const data = await response.json();
                if (data.success && data.question_id) {
                    window.location.href = `question.php?id=${data.question_id}`;
                }
            } catch (error) {
                console.error('Error getting question from answer:', error);
            }
        }
        
        this.closeNotifications();
    }

    startNotificationPolling() {
        // Check for new notifications every 30 seconds
        this.notificationInterval = setInterval(() => {
            this.loadNotifications();
        }, 30000);
    }

    // User menu functions
    toggleUserMenu() {
        if (this.userDropdown) {
            if (this.userDropdown.classList.contains('active')) {
                this.closeUserMenu();
            } else {
                this.openUserMenu();
            }
        }
    }

    openUserMenu() {
        if (this.userDropdown) {
            this.closeNotifications();
            this.userDropdown.classList.add('active');
        }
    }

    closeUserMenu() {
        if (this.userDropdown) {
            this.userDropdown.classList.remove('active');
        }
    }

    // Enhanced voting functions
    async vote(votableId, votableType, voteType) {
        const button = event.target.closest('.vote-btn');
        if (button.disabled) return;

        // Add loading state
        button.disabled = true;
        const originalContent = button.innerHTML;
        button.innerHTML = '<div class="spinner"></div>';

        try {
            const response = await fetch('api/vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    votable_id: votableId,
                    votable_type: votableType,
                    vote_type: voteType
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Vote failed');
            }

            // Find the correct voting container
            let container = button.closest('.voting, .question-voting, .answer-voting');

            if (container) {
                // Update vote count with animation
                const countElement = container.querySelector('.vote-count');
                if (countElement) {
                    // Add animation class
                    countElement.style.transform = 'scale(1.3)';
                    countElement.style.transition = 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                    
                    setTimeout(() => {
                        countElement.textContent = data.vote_count;
                        
                        // Update count styling based on value
                        countElement.classList.remove('positive', 'negative');
                        if (data.vote_count > 0) {
                            countElement.classList.add('positive');
                        } else if (data.vote_count < 0) {
                            countElement.classList.add('negative');
                        }
                        
                        countElement.style.transform = 'scale(1)';
                    }, 150);
                }

                // Update button states with enhanced animations
                const upButton = container.querySelector('.vote-up');
                const downButton = container.querySelector('.vote-down');
                
                if (upButton && downButton) {
                    // Remove active class from both buttons
                    upButton.classList.remove('active');
                    downButton.classList.remove('active');
                    
                    // Add active class to the appropriate button
                    if (data.user_vote === 'up') {
                        upButton.classList.add('active');
                        this.animateButton(upButton, 'success');
                    } else if (data.user_vote === 'down') {
                        downButton.classList.add('active');
                        this.animateButton(downButton, 'error');
                    }
                }
            }

            // Show success feedback
            this.showNotification('Vote recorded successfully!', 'success');

        } catch (error) {
            console.error('Voting error:', error);
            this.showNotification(error.message || 'Failed to vote. Please try again.', 'error');
        } finally {
            // Restore button state
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    }

    animateButton(button, type) {
        button.style.transform = 'scale(0.9)';
        setTimeout(() => {
            button.style.transform = 'scale(1.1)';
        }, 100);
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 200);
    }

    // Accept answer function
    async acceptAnswer(answerId) {
        try {
            const response = await fetch('api/accept_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    answer_id: answerId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('Answer accepted successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Failed to accept answer');
            }

        } catch (error) {
            console.error('Accept answer error:', error);
            this.showNotification(error.message || 'Failed to accept answer. Please try again.', 'error');
        }
    }

    // Profile tab functions
    showTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => btn.classList.remove('active'));
        
        // Show selected tab content
        const selectedContent = document.getElementById(tabName + 'Tab');
        if (selectedContent) {
            selectedContent.classList.add('active');
        }
        
        // Add active class to clicked button
        const selectedButton = document.querySelector(`[onclick*="showTab('${tabName}')"]`);
        if (selectedButton) {
            selectedButton.classList.add('active');
        }
    }

    // Search functionality
    initializeSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const query = searchInput.value.trim();
                    if (query) {
                        window.location.href = `index.php?search=${encodeURIComponent(query)}`;
                    }
                }
            });

            // Add search suggestions (optional enhancement)
            searchInput.addEventListener('input', this.debounce((e) => {
                const query = e.target.value.trim();
                if (query.length > 2) {
                    // Implement search suggestions here if needed
                }
            }, 300));
        }
    }

    // Enhanced notification system
    showNotification(message, type = 'info', duration = 4000) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.toast-notification');
        existingNotifications.forEach(n => {
            n.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => n.remove(), 300);
        });
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'exclamation-circle' : 
                    type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        notification.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    timeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffInSeconds = Math.floor((now - past) / 1000);
        
        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';
        if (diffInSeconds < 31536000) return Math.floor(diffInSeconds / 2592000) + ' months ago';
        return Math.floor(diffInSeconds / 31536000) + ' years ago';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Tag input functionality
class TagInput {
    constructor(container, existingTags = []) {
        this.container = container;
        this.existingTags = existingTags;
        this.selectedTags = [];
        this.init();
    }

    init() {
        this.tagInput = this.container.querySelector('#tagInput');
        this.selectedTagsContainer = this.container.querySelector('#selectedTags');
        this.suggestionsContainer = this.container.querySelector('#tagSuggestions');
        
        this.setupEventListeners();
    }

    setupEventListeners() {
        this.tagInput.addEventListener('input', (e) => {
            const value = e.target.value.trim().toLowerCase();
            if (value.length > 0) {
                const suggestions = this.existingTags.filter(tag => 
                    tag.toLowerCase().includes(value) && 
                    !this.selectedTags.includes(tag)
                ).slice(0, 5);
                
                this.showSuggestions(suggestions, value);
            } else {
                this.hideSuggestions();
            }
        });

        this.tagInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const tag = this.tagInput.value.trim();
                if (tag) {
                    this.addTag(tag);
                    this.tagInput.value = '';
                    this.hideSuggestions();
                }
            }
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.tag-input-container')) {
                this.hideSuggestions();
            }
        });
    }

    showSuggestions(suggestions, input) {
        this.suggestionsContainer.innerHTML = '';
        
        if (suggestions.length > 0) {
            suggestions.forEach(tag => {
                const div = document.createElement('div');
                div.className = 'tag-suggestion';
                div.textContent = tag;
                div.onclick = () => {
                    this.addTag(tag);
                    this.tagInput.value = '';
                    this.hideSuggestions();
                    this.tagInput.focus();
                };
                this.suggestionsContainer.appendChild(div);
            });
        }

        // Add option to create new tag
        if (input && !this.existingTags.includes(input) && !this.selectedTags.includes(input)) {
            const div = document.createElement('div');
            div.className = 'tag-suggestion create-new';
            div.innerHTML = `Create new tag: "<strong>${app.escapeHtml(input)}</strong>"`;
            div.onclick = () => {
                this.addTag(input);
                this.tagInput.value = '';
                this.hideSuggestions();
                this.tagInput.focus();
            };
            this.suggestionsContainer.appendChild(div);
        }

        if (this.suggestionsContainer.children.length > 0) {
            this.suggestionsContainer.style.display = 'block';
        } else {
            this.hideSuggestions();
        }
    }

    hideSuggestions() {
        this.suggestionsContainer.style.display = 'none';
    }

    addTag(tag) {
        tag = tag.trim();
        if (tag && !this.selectedTags.includes(tag) && this.selectedTags.length < 5) {
            this.selectedTags.push(tag);
            this.renderSelectedTags();
        }
    }

    removeTag(tag) {
        this.selectedTags = this.selectedTags.filter(t => t !== tag);
        this.renderSelectedTags();
        this.tagInput.focus();
    }

    renderSelectedTags() {
        this.selectedTagsContainer.innerHTML = '';
        this.selectedTags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'selected-tag';
            span.innerHTML = `
                ${app.escapeHtml(tag)}
                <button type="button" onclick="tagInput.removeTag('${tag.replace(/'/g, "\\'")}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
            this.selectedTagsContainer.appendChild(span);
        });

        // Add hidden inputs for form submission
        this.selectedTags.forEach(tag => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tags[]';
            input.value = tag;
            this.selectedTagsContainer.appendChild(input);
        });
    }

    setTags(tags) {
        this.selectedTags = tags;
        this.renderSelectedTags();
    }
}

// Global functions for backward compatibility
function toggleMobileMenu() {
    app.toggleMobileMenu();
}

function toggleNotifications() {
    app.toggleNotifications();
}

function toggleUserMenu() {
    app.toggleUserMenu();
}

function vote(votableId, votableType, voteType) {
    app.vote(votableId, votableType, voteType);
}

function acceptAnswer(answerId) {
    app.acceptAnswer(answerId);
}

function showTab(tabName) {
    app.showTab(tabName);
}

// Initialize app when DOM is loaded
let app;
let tagInput;

document.addEventListener('DOMContentLoaded', function() {
    app = new StackItApp();
    
    // Initialize tag input if container exists
    const tagContainer = document.querySelector('.tag-input-container');
    if (tagContainer && window.existingTags) {
        tagInput = new TagInput(tagContainer, window.existingTags);
        
        // Set existing tags if any
        if (window.selectedTags) {
            tagInput.setTags(window.selectedTags);
        }
    }
});