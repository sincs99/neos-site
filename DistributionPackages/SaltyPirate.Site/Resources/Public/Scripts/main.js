// Salty Pirate: Handymenü und Header-Linie beim Scrollen
(function () {
    var header = document.querySelector('[data-sp-header]');
    var burger = document.querySelector('[data-sp-burger]');
    var nav = document.querySelector('[data-sp-nav]');

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        });
    }
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();

// Salty Pirate: Bot-Status im Footer (/api/status, alle 5 Minuten vom Wächter auf dem Server geprüft)
(function () {
    var box = document.querySelector('[data-bot-status]');
    if (!box || !window.fetch) { return; }
    var text = box.querySelector('[data-bot-status-text]');
    var labels = { up: 'Bot online', down: 'Bot offline – we’re on it', unknown: 'Bot status unknown' };
    var poll = function () {
        if (document.hidden) { return; }
        fetch(box.getAttribute('data-bot-status'), { cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var state = data && labels[data.bot] ? data.bot : 'unknown';
                box.className = 'sp-footer__status is-' + state;
                text.textContent = labels[state];
                if (data && data.checkedAt) {
                    box.title = 'Checked ' + new Date(data.checkedAt * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' – click for the status page';
                }
                box.hidden = false;
            })
            .catch(function () {});
    };
    poll();
    setInterval(poll, 60000);
    document.addEventListener('visibilitychange', poll);
})();

// Salty Pirate: Kostenbalken live (ein Kaffee bei BMC = ein Grog auf der Seite)
// (Spenden über den Buy-Me-a-Coffee-Webhook, /api/funding)
(function () {
    var card = document.querySelector('[data-funding]');
    if (!card || !window.fetch) { return; }
    var url = card.getAttribute('data-funding');
    var goal = parseFloat(card.getAttribute('data-goal')) || 0;
    var currency = card.getAttribute('data-currency') || '$';
    var el = function (name) { return card.querySelector('[data-funding-' + name + ']'); };
    var known = null;
    var toastTimer = null;

    var money = function (v) { return currency + ' ' + v.toFixed(2); };
    var ago = function (at, now) {
        var s = Math.max(0, now - at);
        if (s < 60) { return 'just now'; }
        if (s < 3600) { return Math.floor(s / 60) + ' min ago'; }
        if (s < 86400) { return Math.floor(s / 3600) + ' h ago'; }
        var d = Math.floor(s / 86400);
        return d + (d === 1 ? ' day ago' : ' days ago');
    };
    // Betrag ohne ".00", wenn er rund ist ("$ 25", aber "$ 4.50")
    var amount = function (v) { return currency + ' ' + (v % 1 === 0 ? String(v) : v.toFixed(2)); };
    var what = function (r) {
        if (typeof r.amount === 'number' && r.amount > 0) {
            return r.monthly ? 'keeps ' + amount(r.amount) + ' of grog flowing every month' : 'poured ' + amount(r.amount) + ' of grog';
        }
        if (r.monthly) { return 'keeps the grog flowing every month'; }
        if (r.coffees) { return 'poured ' + r.coffees + (r.coffees === 1 ? ' grog' : ' grogs'); }
        return 'chipped in';
    };

    var celebrate = function (r) {
        var toast = el('toast');
        toast.textContent = 'Yo-ho, ' + r.name + '! A grog for the crew 🍺';
        toast.hidden = false;
        card.classList.remove('is-celebrating');
        void card.offsetWidth;
        card.classList.add('is-celebrating');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.hidden = true; card.classList.remove('is-celebrating'); }, 6000);
    };

    var render = function (data) {
        var percent = goal > 0 ? Math.min(100, Math.round(data.raised / goal * 100)) : 0;
        el('period').textContent = data.period;
        el('percent').textContent = percent + ' %';
        el('bar').setAttribute('aria-valuenow', percent);
        el('fill').style.width = percent + '%';
        el('ship').style.left = percent + '%';
        el('raised').textContent = money(data.raised);
        var count = el('count');
        count.textContent = data.supporters > 0 ? data.supporters + (data.supporters === 1 ? ' supporter' : ' supporters') + ' this month' : '';
        count.hidden = data.supporters === 0;
        el('live').hidden = false;
        if (el('updated')) { el('updated').hidden = true; }

        var list = el('recent');
        list.textContent = '';
        data.recent.forEach(function (r) {
            var li = document.createElement('li');
            var who = document.createElement('span');
            who.className = 'sp-funding__who';
            who.textContent = r.name;
            var text = document.createElement('span');
            text.className = 'sp-funding__what';
            text.textContent = what(r);
            var when = document.createElement('span');
            when.className = 'sp-funding__when';
            when.textContent = ago(r.at, data.now);
            li.appendChild(who);
            li.appendChild(text);
            li.appendChild(when);
            list.appendChild(li);
        });
        list.hidden = data.recent.length === 0;

        // Neue Einträge seit dem letzten Abruf feiern (nicht beim ersten Laden)
        var ids = data.recent.map(function (r) { return r.id; });
        if (known !== null) {
            var fresh = data.recent.filter(function (r) { return known.indexOf(r.id) === -1; });
            if (fresh.length) { celebrate(fresh[0]); }
        }
        known = ids;
    };

    var poll = function () {
        if (document.hidden) { return; }
        fetch(url, { cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data && typeof data.raised === 'number') { render(data); } })
            .catch(function () {});
    };
    poll();
    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', poll);
})();
