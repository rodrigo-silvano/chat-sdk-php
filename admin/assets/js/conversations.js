function playAudioAlert(type) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (type === 'waiting') {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            osc.start();
            osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
            osc.stop(audioCtx.currentTime + 0.3);
        } else {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(659.25, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
            osc.start();
            osc.frequency.exponentialRampToValueAtTime(783.99, audioCtx.currentTime + 0.1);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
            osc.stop(audioCtx.currentTime + 0.2);
        }
    } catch (e) {
    }
}

function initConversationsList() {
    const listContainer = document.getElementById('conversationsList');
    if (!listContainer) return;

    const filterButtons = document.querySelectorAll('.filter-btn');
    let currentFilter = 'all';
    let prevWaitingCount = parseInt(document.getElementById('count-waiting')?.textContent || '0', 10);

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            applyFilter();
        });
    });

    function applyFilter() {
        const cards = document.querySelectorAll('.conversation-card');
        cards.forEach(card => {
            const status = card.getAttribute('data-status');
            if (currentFilter === 'all' || status === currentFilter) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    function renderConversations(conversations) {
        listContainer.innerHTML = '';
        if (conversations.length === 0) {
            listContainer.innerHTML = '<p class="empty-text">Nenhuma conversa encontrada.</p>';
            return;
        }

        conversations.forEach(conv => {
            let statusClass = '';
            let statusText = '';
            switch (conv.status) {
                case 'active_bot':
                    statusClass = 'badge badge-bot';
                    statusText = 'Bot';
                    break;
                case 'waiting_operator':
                    statusClass = 'badge badge-waiting';
                    statusText = 'Aguardando';
                    break;
                case 'active_operator':
                    statusClass = 'badge badge-operator';
                    statusText = 'Operador';
                    break;
                case 'closed':
                    statusClass = 'badge badge-closed';
                    statusText = 'Fechado';
                    break;
            }

            const card = document.createElement('div');
            card.className = 'conversation-card';
            card.setAttribute('data-id', conv.id);
            card.setAttribute('data-status', conv.status);
            
            const lastMsg = conv.last_message_content || 'Nenhuma mensagem trocada ainda.';
            const displayTime = conv.last_message_time ? formatTime(conv.last_message_time) : formatTime(conv.updated_at);

            let actionHtml = '';
            if (conv.status === 'waiting_operator' || conv.status === 'active_bot') {
                actionHtml = `<button class="btn btn-sm btn-primary takeover-btn" data-id="${conv.id}">Assumir</button>`;
            }

            card.innerHTML = `
                <div class="card-header">
                    <span class="session-id">Cliente #${conv.session_id.substring(0, 8)}</span>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="card-body">
                    <p class="last-message">${escapeHtml(lastMsg)}</p>
                </div>
                <div class="card-footer">
                    <span class="time-elapsed">${displayTime}</span>
                    <div class="card-actions">
                        ${actionHtml}
                        <a href="/admin/conversation-detail?id=${encodeURIComponent(conv.id)}" class="btn btn-sm btn-outline">Ver Chat</a>
                    </div>
                </div>
            `;

            card.addEventListener('click', (e) => {
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
                    window.location.href = `/admin/conversation-detail?id=${encodeURIComponent(conv.id)}`;
                }
            });

            listContainer.appendChild(card);
        });

        document.querySelectorAll('.takeover-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                takeoverConversation(btn.getAttribute('data-id'));
            });
        });

        applyFilter();
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr.replace(/-/g, '/'));
        const pad = (n) => n < 10 ? '0' + n : n;
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ' ' + pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function pollConversations() {
        fetch('/admin/api/conversations')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderConversations(data.conversations);
                    
                    const countWaitingEl = document.getElementById('count-waiting');
                    if (countWaitingEl) {
                        countWaitingEl.textContent = data.waiting_count;
                    }

                    if (data.waiting_count > prevWaitingCount) {
                        playAudioAlert('waiting');
                    }
                    prevWaitingCount = data.waiting_count;
                }
            })
            .catch(() => {});
    }

    document.querySelectorAll('.takeover-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            takeoverConversation(btn.getAttribute('data-id'));
        });
    });

    document.querySelectorAll('.conversation-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
                const id = card.getAttribute('data-id');
                window.location.href = `/admin/conversation-detail?id=${encodeURIComponent(id)}`;
            }
        });
    });

    setInterval(pollConversations, 4000);
}

function takeoverConversation(id) {
    fetch('/admin/api/takeover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = `/admin/conversation-detail?id=${encodeURIComponent(id)}`;
        }
    })
    .catch(() => {});
}

function initChatDetail() {
    if (typeof currentConversationId === 'undefined') return;

    const chatMessages = document.getElementById('chatMessages');
    const sendForm = document.getElementById('sendMessageForm');
    const messageInput = document.getElementById('messageTextInput');
    const chatInputArea = document.getElementById('chatInputArea');
    const inputOverlay = document.getElementById('inputOverlay');
    
    const takeoverBtn = document.getElementById('takeoverChatBtn');
    const releaseBtn = document.getElementById('releaseChatBtn');
    const closeBtn = document.getElementById('closeChatBtn');
    const statusBadgeContainer = document.getElementById('chat-status-badge');

    const msgRows = document.querySelectorAll('.message-row');
    let lastTime = '';
    if (msgRows.length > 0) {
        lastTime = msgRows[msgRows.length - 1].getAttribute('data-time') || '';
    }

    scrollToBottom();

    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    if (sendForm) {
        sendForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;

            messageInput.value = '';
            
            appendMessage({
                sender_type: 'operator',
                content: text,
                created_at: new Date().toISOString().replace('T', ' ').slice(0, 19)
            });
            scrollToBottom();

            fetch('/admin/api/send-message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: currentConversationId,
                    content: text
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                }
            })
            .catch(() => {});
        });
    }

    if (takeoverBtn) {
        takeoverBtn.addEventListener('click', () => {
            fetch('/admin/api/takeover', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: currentConversationId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                }
            });
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener('click', () => {
            fetch('/admin/api/release', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: currentConversationId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                }
            });
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            fetch('/admin/api/close', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: currentConversationId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                }
            });
        });
    }

    function appendMessage(msg) {
        const row = document.createElement('div');
        let senderClass = '';
        let senderTitle = '';
        switch (msg.sender_type) {
            case 'client':
                senderClass = 'msg-client';
                senderTitle = 'Cliente';
                break;
            case 'bot':
                senderClass = 'msg-bot';
                senderTitle = 'Assistente Bot';
                break;
            case 'operator':
                senderClass = 'msg-operator';
                senderTitle = 'Operador Humano';
                break;
        }

        row.className = `message-row ${senderClass}`;
        row.setAttribute('data-time', msg.created_at);
        
        const date = new Date(msg.created_at.replace(/-/g, '/'));
        const timeStr = isNaN(date.getTime()) ? 'Agora' : (d = date, (d.getHours() < 10 ? '0' : '') + d.getHours() + ':' + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes());

        row.innerHTML = `
            <div class="message-bubble">
                <div class="message-meta">${senderTitle} • ${timeStr}</div>
                <div class="message-content">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>
            </div>
        `;
        chatMessages.appendChild(row);
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function pollMessages() {
        fetch(`/admin/api/messages?conversation_id=${encodeURIComponent(currentConversationId)}&after=${encodeURIComponent(lastTime)}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const messages = data.messages;
                    const conv = data.conversation;

                    updateUIStatus(conv);

                    if (messages.length > 0) {
                        let hasNewClientMsg = false;
                        messages.forEach(msg => {
                            if (!isMessageInDom(msg.id, msg.created_at)) {
                                appendMessage(msg);
                                if (msg.sender_type === 'client') {
                                    hasNewClientMsg = true;
                                }
                            }
                            lastTime = msg.created_at;
                        });

                        scrollToBottom();

                        if (hasNewClientMsg) {
                            playAudioAlert('message');
                        }
                    }
                }
            })
            .catch(() => {});
    }

    function isMessageInDom(id, createdAt) {
        const msgElems = document.querySelectorAll('.message-row');
        for (let i = msgElems.length - 1; i >= 0 && i >= msgElems.length - 15; i--) {
            if (msgElems[i].getAttribute('data-time') === createdAt) {
                return true;
            }
        }
        return false;
    }

    function updateUIStatus(conv) {
        if (!statusBadgeContainer) return;

        let badgeClass = '';
        let statusText = '';
        switch (conv.status) {
            case 'active_bot':
                badgeClass = 'badge badge-bot';
                statusText = 'Ativa com Bot';
                break;
            case 'waiting_operator':
                badgeClass = 'badge badge-waiting';
                statusText = 'Aguardando Operador';
                break;
            case 'active_operator':
                badgeClass = 'badge badge-operator';
                statusText = 'Com Operador (' + (conv.operator_name || 'Humano') + ')';
                break;
            case 'closed':
                badgeClass = 'badge badge-closed';
                statusText = 'Fechada';
                break;
        }

        statusBadgeContainer.innerHTML = `<span class="${badgeClass}">${statusText}</span>`;

        const assignedToMe = (conv.assigned_operator_id === myOperatorId && conv.status === 'active_operator');

        if (assignedToMe) {
            takeoverBtn?.classList.add('hidden');
            releaseBtn?.classList.remove('hidden');
            chatInputArea?.classList.remove('disabled');
            messageInput?.removeAttribute('disabled');
            sendForm?.querySelector('button')?.removeAttribute('disabled');
            inputOverlay?.classList.add('hidden');
        } else {
            takeoverBtn?.classList.remove('hidden');
            releaseBtn?.classList.add('hidden');
            chatInputArea?.classList.add('disabled');
            messageInput?.setAttribute('disabled', 'true');
            sendForm?.querySelector('button')?.setAttribute('disabled', 'true');
            inputOverlay?.classList.remove('hidden');
        }

        if (conv.status === 'closed') {
            takeoverBtn?.classList.add('hidden');
            releaseBtn?.classList.add('hidden');
            closeBtn?.classList.add('hidden');
            chatInputArea?.classList.add('disabled');
            inputOverlay?.classList.remove('hidden');
            if (inputOverlay) {
                inputOverlay.querySelector('span').textContent = 'Esta conversa foi fechada.';
            }
        } else {
            closeBtn?.classList.remove('hidden');
        }
    }

    setInterval(pollMessages, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    initConversationsList();
    initChatDetail();
});
