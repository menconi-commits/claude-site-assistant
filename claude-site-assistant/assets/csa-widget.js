/**
 * Claude Site Assistant — widget chat per i visitatori.
 * JavaScript puro, nessuna dipendenza. La conversazione resta nel browser
 * (sessionStorage) e si azzera alla chiusura della scheda.
 */
(function () {
    'use strict';

    var cfg = window.CSA_CONFIG;
    if (!cfg || !cfg.restUrl) {
        return;
    }

    var STORAGE_KEY = 'csa_history';
    var history = loadHistory();
    var busy = false;

    // ---------- Struttura HTML ----------
    var root = el('div', 'csa-root csa-pos-' + (cfg.position === 'left' ? 'left' : 'right'));
    root.style.setProperty('--csa-color', cfg.color || '#cc0000');

    var toggle = el('button', 'csa-toggle');
    toggle.type = 'button';
    toggle.setAttribute('aria-label', 'Apri la chat con ' + cfg.botName);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z"/></svg>';

    var panel = el('div', 'csa-panel');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Chat con ' + cfg.botName);
    panel.hidden = true;

    var header = el('div', 'csa-header');
    var title = el('span', 'csa-title');
    title.textContent = cfg.botName;
    var resetBtn = el('button', 'csa-reset');
    resetBtn.type = 'button';
    resetBtn.textContent = 'Nuova chat';
    var closeBtn = el('button', 'csa-close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Chiudi la chat');
    closeBtn.innerHTML = '&times;';
    header.appendChild(title);
    header.appendChild(resetBtn);
    header.appendChild(closeBtn);

    var list = el('div', 'csa-messages');
    list.setAttribute('aria-live', 'polite');

    var form = el('form', 'csa-form');
    var input = el('textarea', 'csa-input');
    input.rows = 1;
    input.maxLength = cfg.maxChars || 1000;
    input.placeholder = 'Scrivi la tua domanda…';
    input.setAttribute('aria-label', 'La tua domanda');
    var send = el('button', 'csa-send');
    send.type = 'submit';
    send.textContent = 'Invia';
    form.appendChild(input);
    form.appendChild(send);

    var privacy = el('div', 'csa-privacy');
    privacy.textContent = cfg.privacyText || '';
    if (cfg.privacyUrl) {
        var pLink = el('a');
        pLink.href = cfg.privacyUrl;
        pLink.target = '_blank';
        pLink.rel = 'noopener';
        pLink.textContent = 'Privacy policy';
        privacy.appendChild(document.createTextNode(' '));
        privacy.appendChild(pLink);
    }

    panel.appendChild(header);
    panel.appendChild(list);
    panel.appendChild(form);
    panel.appendChild(privacy);
    root.appendChild(panel);
    root.appendChild(toggle);
    document.body.appendChild(root);

    renderAll();

    // ---------- Eventi ----------
    toggle.addEventListener('click', function () {
        setOpen(panel.hidden);
    });
    closeBtn.addEventListener('click', function () {
        setOpen(false);
    });
    resetBtn.addEventListener('click', function () {
        if (busy) {
            return;
        }
        history = [];
        saveHistory();
        renderAll();
        input.focus();
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        ask();
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) {
            setOpen(false);
        }
    });

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('csa-open', open);
        if (open) {
            input.focus();
            list.scrollTop = list.scrollHeight;
        }
    }

    // ---------- Invio domanda ----------
    function ask() {
        var text = input.value.trim();
        if (!text || busy) {
            return;
        }
        if (history.length + 1 > (cfg.maxMessages || 21)) {
            addBubble('system', 'Hai raggiunto il numero massimo di domande per questa chat. Clicca su "Nuova chat" per ricominciare.');
            return;
        }

        busy = true;
        send.disabled = true;
        input.value = '';
        history.push({ role: 'user', content: text });
        addBubble('user', text);
        var typing = addBubble('bot csa-typing', '');
        typing.innerHTML = '<span></span><span></span><span></span>';

        var headers = { 'Content-Type': 'application/json' };
        if (cfg.nonce) {
            headers['X-WP-Nonce'] = cfg.nonce;
        }

        fetch(cfg.restUrl, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify({ messages: history })
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }, function () {
                    return { ok: false, data: {} };
                });
            })
            .then(function (r) {
                typing.remove();
                if (!r.ok || !r.data || r.data.error) {
                    history.pop();
                    input.value = text;
                    addBubble('system', (r.data && r.data.error) || 'Si è verificato un problema. Riprova.');
                    return;
                }
                if (r.data.discard || !r.data.content) {
                    history.pop();
                    addBubble('system', r.data.reply || 'Non posso rispondere a questa domanda.');
                    return;
                }
                history.push({ role: 'assistant', content: r.data.content });
                saveHistory();
                addBubble('bot', r.data.reply, true);
            })
            .catch(function () {
                typing.remove();
                history.pop();
                input.value = text;
                addBubble('system', 'Connessione non riuscita. Controlla la rete e riprova.');
            })
            .then(function () {
                busy = false;
                send.disabled = false;
                input.focus();
            });
    }

    // ---------- Rendering ----------
    function renderAll() {
        list.innerHTML = '';
        if (cfg.welcome) {
            addBubble('bot', cfg.welcome, true);
        }
        history.forEach(function (m) {
            if (m.role === 'user') {
                addBubble('user', m.content);
            } else {
                addBubble('bot', textOf(m.content), true);
            }
        });
    }

    function textOf(blocks) {
        if (!Array.isArray(blocks)) {
            return '';
        }
        return blocks
            .filter(function (b) { return b && b.type === 'text'; })
            .map(function (b) { return b.text; })
            .join('');
    }

    function addBubble(kind, text, formatted) {
        var b = el('div', 'csa-msg csa-' + kind);
        if (formatted) {
            b.innerHTML = formatText(text);
        } else {
            b.textContent = text;
        }
        list.appendChild(b);
        list.scrollTop = list.scrollHeight;
        return b;
    }

    /**
     * Mini-markdown sicuro: prima si fa l'escape di tutto l'HTML, poi si
     * riconoscono solo grassetto, link http(s) ed elenchi con il trattino.
     */
    function formatText(text) {
        var html = escapeHtml(text || '');
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (m, label, url) {
            return '<a href="' + url + '" target="_blank" rel="noopener">' + label + '</a>';
        });
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        var lines = html.split('\n');
        var out = [];
        var inList = false;
        lines.forEach(function (line) {
            var m = line.match(/^\s*[-*•]\s+(.*)$/);
            if (m) {
                if (!inList) {
                    out.push('<ul>');
                    inList = true;
                }
                out.push('<li>' + m[1] + '</li>');
            } else {
                if (inList) {
                    out.push('</ul>');
                    inList = false;
                }
                out.push(line === '' ? '<br>' : line + '<br>');
            }
        });
        if (inList) {
            out.push('</ul>');
        }
        return out.join('').replace(/(<br>)+$/, '');
    }

    function escapeHtml(s) {
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ---------- Utilità ----------
    function el(tag, cls) {
        var n = document.createElement(tag);
        if (cls) {
            n.className = cls;
        }
        return n;
    }

    function loadHistory() {
        try {
            var h = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
            // Una cronologia valida termina sempre con una risposta.
            if (Array.isArray(h) && h.length % 2 === 0) {
                return h;
            }
        } catch (e) {}
        return [];
    }

    function saveHistory() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
        } catch (e) {}
    }
})();
