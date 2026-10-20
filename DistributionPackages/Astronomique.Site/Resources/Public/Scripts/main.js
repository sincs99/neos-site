// astronomique.ch: Menü auf dem Handy
(function () {
    var burger = document.querySelector('[data-as-burger]');
    var nav = document.querySelector('[data-as-nav]');
    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
})();
