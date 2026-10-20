// astronomique.ch: Sternenhimmel (Canvas), Handymenü, Header beim Scrollen
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // --- Sterne -------------------------------------------------------------
    var skies = [];
    document.querySelectorAll('[data-as-stars]').forEach(function (canvas) {
        var ctx = canvas.getContext('2d');
        var sky = { canvas: canvas, ctx: ctx, stars: [] };
        skies.push(sky);
        resize(sky);
    });

    function resize(sky) {
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var w = sky.canvas.offsetWidth, h = sky.canvas.offsetHeight;
        sky.canvas.width = w * dpr;
        sky.canvas.height = h * dpr;
        sky.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        var count = Math.round(w * h / 2600);
        sky.stars = [];
        for (var i = 0; i < count; i++) {
            sky.stars.push({
                x: Math.random() * w,
                y: Math.random() * h,
                r: Math.random() < 0.08 ? 1.3 + Math.random() * 0.8 : 0.3 + Math.random() * 0.8,
                a: 0.25 + Math.random() * 0.75,
                s: 0.4 + Math.random() * 1.6,
                p: Math.random() * Math.PI * 2,
                gold: Math.random() < 0.12
            });
        }
        sky.w = w; sky.h = h;
    }

    function draw(t) {
        skies.forEach(function (sky) {
            var ctx = sky.ctx;
            ctx.clearRect(0, 0, sky.w, sky.h);
            sky.stars.forEach(function (st) {
                var tw = reduce ? 1 : 0.65 + 0.35 * Math.sin(t / 1000 * st.s + st.p);
                ctx.globalAlpha = st.a * tw;
                ctx.fillStyle = st.gold ? '#f0d9a2' : '#ffffff';
                ctx.beginPath();
                ctx.arc(st.x, st.y, st.r, 0, Math.PI * 2);
                ctx.fill();
            });
        });
        if (!reduce) { requestAnimationFrame(draw); }
    }
    if (skies.length) { requestAnimationFrame(draw); }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { skies.forEach(resize); if (reduce) { draw(0); } }, 150);
    });

    // --- Menü & Header ------------------------------------------------------
    var header = document.querySelector('[data-as-header]');
    var burger = document.querySelector('[data-as-burger]');
    var nav = document.querySelector('[data-as-nav]');

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        });
    }
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 20); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
