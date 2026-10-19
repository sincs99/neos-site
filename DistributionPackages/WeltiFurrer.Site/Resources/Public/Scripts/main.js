// Welti-Furrer Entwurf: Menü auf dem Handy, Schatten beim Scrollen, Reiter im Offerten-Rechner
(function () {
    var header = document.querySelector('[data-wf-header]');
    var burger = document.querySelector('[data-wf-burger]');
    var nav = document.querySelector('[data-wf-nav]');

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        });
    }

    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 10);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    document.querySelectorAll('[data-wf-quote]').forEach(function (quote) {
        var tabs = quote.querySelectorAll('[data-wf-tab]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
            });
        });
    });
})();
