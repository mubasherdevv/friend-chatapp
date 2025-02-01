$(document).ready(function() {
    const DEBUG = true;
    let ws = null;
    let wsConnected = false;
    let reconnectAttempt = 0;
    let maxReconnectAttempts = 5;
    let reconnectInterval = null;
    
    function log(...args) {
        if (DEBUG) console.log(...args);
    }
    
    let lastMessageId = 0;
    let isEditing = false;
    let editingMessageId = null;
    let typingTimeout = null;
    let lastMessageTimestamp = null;
    let pendingMessages = new Map();
    
    // Get initial last message timestamp
    const messages = $('#messages .message-container');
    if (messages.length > 0) {
        const lastMessage = messages.last();
        const messageTime = lastMessage.find('.message-time').attr('data-timestamp');
        if (messageTime) {
            lastMessageTimestamp = messageTime;
            log('Initial last message timestamp:', lastMessageTimestamp);
        }
    }
    
    const currentRoomId = window.roomId;
    const currentUserId = window.userId;
    const maxMessageLength = window.maxMessageLength;
    
    log('Initializing chat with:', { currentRoomId, currentUserId, maxMessageLength });
    
    const messageInput = $('#messageInput');
    const messagesDiv = $('#messages');
    const typingIndicator = $('#typingIndicator');
    const messageForm = $('#messageForm');
    
    function showConnectionStatus(status, isError = false) {
        const statusDiv = $('#connectionStatus');
        if (!statusDiv.length) {
            $('<div>')
                .attr('id', 'connectionStatus')
                .addClass('fixed top-0 left-0 right-0 text-center py-1')
                .appendTo('body');
        }
        
        statusDiv.removeClass('bg-yellow-500 bg-red-500 bg-green-500')
            .addClass(isError ? 'bg-red-500' : status === 'connected' ? 'bg-green-500' : 'bg-yellow-500')
            .addClass('text-white')
            .text(status === 'connected' ? 'Connected' : status === 'connecting' ? 'Connecting...' : 'Disconnected');
            
        if (status === 'connected') {
            setTimeout(() => {
                statusDiv.fadeOut();
            }, 2000);
        } else {
            statusDiv.show();
        }
    }
    
    // Initialize WebSocket with auto-reconnect
    function initWebSocket() {
        try {
            if (ws) {
                ws.close();
            }
            
            showConnectionStatus('connecting');
            
            const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsHost = window.location.hostname;
            ws = new WebSocket(`${wsProtocol}//${wsHost}:8080`);
            
            ws.onopen = function() {
                log('WebSocket connected');
                wsConnected = true;
                reconnectAttempt = 0;
                showConnectionStatus('connected');
                
                // Join the room
                ws.send(JSON.stringify({
                    type: 'join',
                    room_id: currentRoomId,
                    user_id: currentUserId,
                    username: window.username,
                    last_timestamp: lastMessageTimestamp
                }));
                
                // Resend any pending messages
                for (const [tempId, message] of pendingMessages) {
                    sendMessage(message, tempId);
                }
            };
            
            ws.onclose = function() {
                log('WebSocket disconnected');
                wsConnected = false;
                showConnectionStatus('disconnected');
                
                // Attempt to reconnect
                if (reconnectAttempt < maxReconnectAttempts) {
                    const timeout = Math.min(1000 * Math.pow(2, reconnectAttempt), 10000);
                    log(`Reconnecting in ${timeout}ms (attempt ${reconnectAttempt + 1}/${maxReconnectAttempts})`);
                    
                    if (reconnectInterval) {
                        clearTimeout(reconnectInterval);
                    }
                    
                    reconnectInterval = setTimeout(() => {
                        reconnectAttempt++;
                        initWebSocket();
                    }, timeout);
                } else {
                    showConnectionStatus('Failed to connect. Please refresh the page.', true);
                }
            };
            
            ws.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    log('Received message:', data);
                    
                    switch (data.type) {
                        case 'join_success':
                            log('Successfully joined room:', data.room_id);
                            break;
                            
                        case 'message':
                            appendMessage(data.message);
                            break;
                            
                        case 'message_confirm':
                            if (data.temp_id) {
                                const msgElement = $(`.message-container[data-temp-id="${data.temp_id}"]`);
                                msgElement.attr('data-message-id', data.message_id);
                                msgElement.find('.message-pending').remove();
                                pendingMessages.delete(data.temp_id);
                            }
                            break;
                            
                        case 'typing':
                            if (data.user_id !== currentUserId) {
                                if (data.is_typing) {
                                    showTypingIndicator(data.username);
                                } else {
                                    hideTypingIndicator(data.username);
                                }
                            }
                            break;
                            
                        case 'error':
                            log('Received error:', data.message);
                            if (data.temp_id) {
                                const msgElement = $(`.message-container[data-temp-id="${data.temp_id}"]`);
                                msgElement.addClass('error').find('.message-content')
                                    .append('<span class="text-red-500 ml-2">(Failed to send)</span>');
                                pendingMessages.delete(data.temp_id);
                            }
                            break;
                    }
                } catch (error) {
                    log('Error processing message:', error);
                }
            };
            
            ws.onerror = function(error) {
                log('WebSocket error:', error);
                wsConnected = false;
                showConnectionStatus('Connection error', true);
            };
            
        } catch (error) {
            log('Error initializing WebSocket:', error);
            wsConnected = false;
            showConnectionStatus('Connection error', true);
        }
    }
    
    function sendMessage(message, tempId = null) {
        if (!wsConnected) {
            showConnectionStatus('Not connected. Trying to reconnect...', true);
            return false;
        }
        
        try {
            tempId = tempId || 'msg_' + Date.now();
            const messageData = {
                type: 'message',
                room_id: currentRoomId,
                user_id: currentUserId,
                message: message,
                temp_id: tempId
            };
            
            ws.send(JSON.stringify(messageData));
            pendingMessages.set(tempId, message);
            
            // Show pending message immediately
            appendMessage({
                id: 'pending-' + tempId,
                temp_id: tempId,
                content: message,
                user_id: currentUserId,
                username: window.username,
                avatar: window.userAvatar,
                created_at: new Date().toISOString(),
                pending: true
            });
            
            return true;
        } catch (error) {
            log('Error sending message:', error);
            showConnectionStatus('Failed to send message', true);
            return false;
        }
    }
    
    // Initialize WebSocket connection
    initWebSocket();
    
    // Handle message input
    messageInput.on('input', function() {
        if (!wsConnected) return;
        
        if (!typingTimeout) {
            ws.send(JSON.stringify({
                type: 'typing',
                room_id: currentRoomId,
                user_id: currentUserId,
                is_typing: true
            }));
        }
        
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            if (wsConnected) {
                ws.send(JSON.stringify({
                    type: 'typing',
                    room_id: currentRoomId,
                    user_id: currentUserId,
                    is_typing: false
                }));
            }
            typingTimeout = null;
        }, 1000);
    });
    
    // Handle message form submission
    messageForm.on('submit', function(e) {
        e.preventDefault();
        
        if (!wsConnected) {
            showConnectionStatus('Not connected. Please wait...', true);
            return;
        }
        
        const message = messageInput.val().trim();
        if (!message) return;
        
        if (message.length > maxMessageLength) {
            alert(`Message too long. Maximum length is ${maxMessageLength} characters.`);
            return;
        }
        
        if (isEditing) {
            // Handle message editing
            const messageId = editingMessageId;
            ws.send(JSON.stringify({
                type: 'edit_message',
                message_id: messageId,
                content: message,
                room_id: currentRoomId,
                user_id: currentUserId
            }));
            
            isEditing = false;
            editingMessageId = null;
            messageInput.val('');
            $('.cancel-edit').remove();
        } else {
            // Send new message
            if (sendMessage(message)) {
                messageInput.val('');
            }
        }
    });
    
    // Message editing
    window.editMessage = function(messageId) {
        const messageElem = $(`.message-container[data-message-id="${messageId}"]`);
        const messageText = messageElem.find('.message-text').text().trim();
        
        messageInput.val(messageText);
        messageInput.focus();
        isEditing = true;
        editingMessageId = messageId;
        
        if (!$('.cancel-edit').length) {
            messageInput.after(
                $('<button>')
                    .addClass('cancel-edit ml-2 text-gray-500 hover:text-gray-700')
                    .text('Cancel')
                    .click(function(e) {
                        e.preventDefault();
                        isEditing = false;
                        editingMessageId = null;
                        messageInput.val('');
                        $(this).remove();
                    })
            );
        }
    };
    
    // Message deletion
    window.deleteMessage = function(messageId) {
        if (!wsConnected) {
            alert('Not connected to chat server. Please refresh the page.');
            return;
        }
        
        if (confirm('Are you sure you want to delete this message?')) {
            ws.send(JSON.stringify({
                type: 'delete_message',
                room_id: currentRoomId,
                user_id: currentUserId,
                message_id: messageId
            }));
        }
    };
    
    // Message reactions
    window.toggleReaction = function(messageId, reaction) {
        if (!wsConnected) {
            alert('Not connected to chat server. Please refresh the page.');
            return;
        }
        
        ws.send(JSON.stringify({
            type: 'toggle_reaction',
            room_id: currentRoomId,
            user_id: currentUserId,
            message_id: messageId,
            reaction: reaction
        }));
    };
    
    // Profile handling
    window.showProfile = function(userId) {
        const profileModal = $('#profileModal');
        const profileContent = $('#profileContent');
        
        // Show loading state
        profileContent.html('<div class="flex items-center justify-center h-[200px]"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>');
        profileModal.removeClass('hidden');
        
        // Fetch profile data
        fetch(`profile.php?id=${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            profileContent.html(html);
        })
        .catch(error => {
            profileContent.html('<div class="text-red-600 text-center">Error loading profile</div>');
            console.error('Error:', error);
        });
    };
    
    window.closeProfile = function() {
        const profileModal = $('#profileModal');
        profileModal.addClass('hidden');
    };
    
    // Close profile modal when clicking outside
    $(document).on('click', function(e) {
        const profileModal = $('#profileModal');
        const profileContent = $('#profileContent');
        
        if (!profileContent.is(e.target) && profileContent.has(e.target).length === 0 && 
            !$(e.target).closest('.message-content').length) {
            profileModal.addClass('hidden');
        }
    });
    
    // Handle image loading errors
    $(document).on('error', 'img', function() {
        $(this).attr('src', 'uploads/avatars/default.png');
    });
    
    // UI update functions
    function appendMessage(data) {
        log('Creating message HTML for:', data);
        const isCurrentUser = data.user_id === currentUserId;
        const avatarPath = `uploads/avatars/${data.avatar}`;
        const messageHtml = createMessageHTML(data, isCurrentUser, avatarPath);
        messagesDiv.append(messageHtml);
        scrollToBottom();
    }
    
    function updateMessage(data) {
        const messageElem = $(`.message-container[data-message-id="${data.message_id}"]`);
        if (messageElem.length) {
            // Create temporary div for animation
            const tempContent = $('<div>')
                .addClass('message-text-temp')
                .css({
                    position: 'absolute',
                    top: messageElem.find('.message-text').position().top,
                    left: messageElem.find('.message-text').position().left,
                    opacity: 1,
                    width: messageElem.find('.message-text').width()
                })
                .html(escapeHtml(data.message));

            // Add temp div and fade out old content
            messageElem.css('position', 'relative').append(tempContent);
            messageElem.find('.message-text').fadeOut(200, function() {
                // Update content
                $(this).html(escapeHtml(data.message)).css('opacity', 0).show();
                
                // Animate temp content up and fade out
                tempContent.animate({
                    opacity: 0,
                    top: '-=10'
                }, {
                    duration: 200,
                    easing: 'linear',
                    complete: function() {
                        // Remove temp content and fade in new content
                        $(this).remove();
                        messageElem.find('.message-text').animate({
                            opacity: 1
                        }, {
                            duration: 200,
                            easing: 'linear'
                        });
                    }
                });
            });

            // Update edit indicator and time
            if (!messageElem.find('.message-info .edited-indicator').length) {
                const editSpan = $('<span>')
                    .addClass('edited-indicator text-gray-500 text-xs ml-1 opacity-0')
                    .text('(edited)');
                
                messageElem.find('.message-info').prepend(editSpan);
                editSpan.animate({ opacity: 1 }, {
                    duration: 300,
                    easing: 'linear'
                });
            }

            // Update timestamp with fade
            const timeElem = messageElem.find('.message-time');
            const editedTime = new Date(data.edited_at).toLocaleTimeString();
            timeElem.fadeOut(200, function() {
                $(this).text(editedTime).fadeIn(200);
            });
        }
    }
    
    function deleteMessageUI(messageId) {
        const messageElem = $(`.message-container[data-message-id="${messageId}"]`);
        messageElem.find('.message-text').addClass('italic text-gray-500').text('This message was deleted');
        messageElem.find('.message-actions').remove();
    }
    
    function updateReactions(messageId) {
        $.get(`api/get_reactions.php?message_id=${messageId}`, function(data) {
            const reactionsContainer = $(`.message-container[data-message-id="${messageId}"] .message-reactions`);
            reactionsContainer.empty();
            data.reactions.forEach(reaction => {
                reactionsContainer.append(createReactionHTML(reaction));
            });
        });
    }
    
    function showTypingIndicator(username) {
        const existingUsers = typingIndicator.data('typing-users') || [];
        if (!existingUsers.includes(username)) {
            existingUsers.push(username);
            typingIndicator.data('typing-users', existingUsers);
            updateTypingIndicator();
        }
    }
    
    function hideTypingIndicator(username) {
        const existingUsers = typingIndicator.data('typing-users') || [];
        const index = existingUsers.indexOf(username);
        if (index > -1) {
            existingUsers.splice(index, 1);
            typingIndicator.data('typing-users', existingUsers);
            updateTypingIndicator();
        }
    }
    
    function updateTypingIndicator() {
        const users = typingIndicator.data('typing-users') || [];
        if (users.length === 0) {
            typingIndicator.addClass('hidden').text('');
        } else if (users.length === 1) {
            typingIndicator.removeClass('hidden').text(`${users[0]} is typing...`);
        } else if (users.length === 2) {
            typingIndicator.removeClass('hidden').text(`${users[0]} and ${users[1]} are typing...`);
        } else {
            typingIndicator.removeClass('hidden').text('Several people are typing...');
        }
    }
    
    function createMessageHTML(data, isCurrentUser, avatarPath) {
        const messageContainer = $('<div>')
            .addClass(`message-container flex ${isCurrentUser ? 'justify-end' : 'justify-start'} mb-4`)
            .attr('data-message-id', data.id);

        const messageContent = $('<div>')
            .addClass('message-content max-w-[70%]')
            .css('transform-origin', isCurrentUser ? 'right' : 'left');

        const messageInner = $('<div>')
            .addClass(`message-inner relative rounded-lg p-3 ${isCurrentUser ? 'bg-indigo-500 text-white' : 'bg-gray-100'} ${data.pending ? 'opacity-70' : ''}`);

        if (data.pending) {
            messageInner.append(
                $('<div>')
                    .addClass('message-pending absolute top-1 right-1 w-2 h-2 rounded-full bg-gray-400 animate-pulse')
            );
        }

        const messageText = $('<div>')
            .addClass('message-text break-words')
            .text(data.message);

        const messageInfo = $('<div>')
            .addClass('message-info flex items-center mt-1 space-x-2');

        const messageTime = $('<span>')
            .addClass('message-time text-xs text-gray-500')
            .attr('data-timestamp', data.created_at)
            .text(formatDate(data.created_at));

        messageInfo.append(messageTime);
        messageInner.append(messageText);
        messageInner.append(messageInfo);
        messageContent.append(messageInner);
        messageContainer.append(messageContent);

        // Show actions on hover
        messageInner.hover(
            function() { messageInner.find('.message-actions').css('opacity', 1); },
            function() { messageInner.find('.message-actions').css('opacity', 0); }
        );

        // Add entrance animation
        messageContainer.css({
            opacity: 0,
            transform: `translateY(20px) scale(0.95)`
        }).animate({
            opacity: 1,
            transform: 'translateY(0) scale(1)'
        }, 300, 'easeOutCubic');

        return messageContainer;
    }
    
    function createReactionHTML(reaction) {
        return `
            <button onclick="toggleReaction(${reaction.message_id}, '${reaction.type}')" 
                    class="reaction-btn px-2 py-1 rounded-full text-xs bg-gray-300 hover:bg-gray-400">
                ${reaction.type} ${reaction.count}
            </button>
        `;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleTimeString();
    }
    
    function scrollToBottom() {
        messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
    }
});
