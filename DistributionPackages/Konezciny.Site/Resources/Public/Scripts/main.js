// konezciny.com: Handymenü und Header-Linie beim Scrollen
(function () {
    var header = document.querySelector('[data-kz-header]');
    var burger = document.querySelector('[data-kz-burger]');
    var nav = document.querySelector('[data-kz-nav]');

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        });
    }

    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
