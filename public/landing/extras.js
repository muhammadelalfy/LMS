/* Landing extras: network canvas, marquee, tour slider, floating shapes. */
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var gsap = window.gsap;

    // 0. The hero fills what the sticky header leaves of the first screen: keep that height exact.
    var topBar = document.getElementById('top-bar');
    if (topBar) {
        var setHeaderHeight = function () {
            document.documentElement.style.setProperty('--header-h', topBar.offsetHeight + 'px');
        };
        var hero = document.querySelector('.hero');
        var strip = document.querySelector('.marquee');
        var cue = document.querySelector('.scroll-cue');
        var setStripHeight = function () {
            if (strip) document.documentElement.style.setProperty('--marquee-h', strip.offsetHeight + 'px');
        };
        // The cue only helps when header + hero + strip are exactly one screen; when they run past
        // the fold (phones, short windows) the content already shows there is more below.
        var placeCue = function () {
            if (cue && hero) cue.classList.toggle('is-off', hero.offsetTop + hero.offsetHeight + (strip ? strip.offsetHeight : 0) > window.innerHeight + 1);
        };
        var layout = function () { setHeaderHeight(); setStripHeight(); placeCue(); };
        layout();
        window.addEventListener('resize', layout);
        if (window.ResizeObserver) {
            new ResizeObserver(layout).observe(topBar);
            if (strip) new ResizeObserver(layout).observe(strip);
            if (hero) new ResizeObserver(placeCue).observe(hero);
        }
    }

    // 1. Network of drifting points that react to the pointer.
    var canvas = document.getElementById('net');
    if (canvas && canvas.getContext && !reduce) {
        var ctx = canvas.getContext('2d');
        var pts = [], W = 0, H = 0, mouse = { x: -999, y: -999 }, running = true;
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var resize = function () {
            var r = canvas.getBoundingClientRect();
            W = r.width; H = r.height;
            canvas.width = W * dpr; canvas.height = H * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            var n = Math.round(Math.min(70, Math.max(24, W / 22)));
            pts = [];
            for (var i = 0; i < n; i++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35 });
        };
        resize();
        window.addEventListener('resize', resize);
        canvas.parentElement.addEventListener('pointermove', function (e) {
            var r = canvas.getBoundingClientRect();
            mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top;
        });
        canvas.parentElement.addEventListener('pointerleave', function () { mouse.x = mouse.y = -999; });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) { running = en[0].isIntersecting; }).observe(canvas);
        }
        (function frame() {
            requestAnimationFrame(frame);
            if (!running || document.hidden) return;
            ctx.clearRect(0, 0, W, H);
            for (var i = 0; i < pts.length; i++) {
                var p = pts[i];
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > W) p.vx *= -1;
                if (p.y < 0 || p.y > H) p.vy *= -1;
                var dx = p.x - mouse.x, dy = p.y - mouse.y, d = Math.sqrt(dx * dx + dy * dy);
                if (d < 120 && d > 0) { p.x += dx / d * 1.2; p.y += dy / d * 1.2; }
                ctx.beginPath();
                ctx.arc(p.x, p.y, 1.8, 0, 6.283);
                ctx.fillStyle = 'rgba(212,175,85,.9)';
                ctx.fill();
                for (var j = i + 1; j < pts.length; j++) {
                    var q = pts[j], ex = p.x - q.x, ey = p.y - q.y, dist = ex * ex + ey * ey;
                    if (dist < 130 * 130) {
                        ctx.strokeStyle = 'rgba(212,175,85,' + (0.28 * (1 - Math.sqrt(dist) / 130)).toFixed(3) + ')';
                        ctx.lineWidth = 1;
                        ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                    }
                }
            }
        })();
    }

    if (!gsap) return;

    // 2. Marquee: endless drift, faster when the page is scrolled.
    var track = document.querySelector('.marquee-track');
    if (track && !reduce) {
        var half = function () { return track.scrollWidth / 2; };
        var tween = gsap.to(track, { x: function () { return -half(); }, duration: 40, ease: 'none', repeat: -1 });
        if (window.ScrollTrigger) {
            window.ScrollTrigger.create({
                onUpdate: function (self) {
                    gsap.to(tween, { timeScale: 1 + Math.min(Math.abs(self.getVelocity()) / 400, 4), duration: 0.2, overwrite: true });
                    gsap.to(tween, { timeScale: 1, duration: 1.2, delay: 0.2, overwrite: false });
                }
            });
        }
        var strip = track.parentElement;
        strip.addEventListener('pointerenter', function () { gsap.to(tween, { timeScale: 0.2, duration: .5 }); });
        strip.addEventListener('pointerleave', function () { gsap.to(tween, { timeScale: 1, duration: .5 }); });
    }

    // 3. Tour slider: arrows, dots, drag/swipe, auto-play with progress.
    var root = document.getElementById('slider');
    if (root) {
        var viewport = root.querySelector('.slider-viewport');
        var strack = root.querySelector('.slider-track');
        var slides = Array.prototype.slice.call(strack.children);
        var dots = root.querySelector('.sl-dots');
        var bar = root.querySelector('.slider-bar i');
        var rtl = getComputedStyle(root).direction === 'rtl';
        var sign = rtl ? 1 : -1;
        var index = 0, delay = 5000, progress = null, paused = false;

        var perView = function () { return window.innerWidth <= 560 ? 1 : window.innerWidth <= 900 ? 2 : 3; };
        var maxIndex = function () { return Math.max(0, slides.length - perView()); };
        var step = function () { return slides[0].getBoundingClientRect().width + 20; };

        slides.forEach(function (_, i) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'sl-dot'; b.setAttribute('aria-label', 'الشريحة ' + (i + 1));
            b.addEventListener('click', function () { go(i); });
            dots.appendChild(b);
        });
        var dotEls = Array.prototype.slice.call(dots.children);

        function paint() {
            dotEls.forEach(function (d, i) { d.classList.toggle('on', i === index); d.style.display = i > maxIndex() ? 'none' : ''; });
        }
        function restartProgress() {
            if (progress) progress.kill();
            if (reduce) return;
            gsap.set(bar, { scaleX: 0 });
            progress = gsap.to(bar, { scaleX: 1, duration: delay / 1000, ease: 'none', paused: paused, onComplete: function () { go(index >= maxIndex() ? 0 : index + 1); } });
        }
        function go(i) {
            index = Math.max(0, Math.min(maxIndex(), i));
            gsap.to(strack, { x: sign * index * step(), duration: 0.8, ease: 'power3.inOut', overwrite: true });
            paint();
            restartProgress();
        }
        root.querySelectorAll('.sl-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = parseInt(btn.getAttribute('data-dir'), 10);
                go(dir > 0 ? (index >= maxIndex() ? 0 : index + 1) : (index <= 0 ? maxIndex() : index - 1));
            });
        });
        root.addEventListener('pointerenter', function () { paused = true; if (progress) progress.pause(); });
        root.addEventListener('pointerleave', function () { paused = false; if (progress) progress.resume(); });

        // Drag / swipe.
        var startX = 0, startPos = 0, dragging = false;
        viewport.addEventListener('pointerdown', function (e) {
            dragging = true; startX = e.clientX; startPos = gsap.getProperty(strack, 'x');
            viewport.classList.add('drag'); gsap.killTweensOf(strack);
            if (progress) progress.pause();
        });
        window.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            gsap.set(strack, { x: startPos + (e.clientX - startX) });
        });
        window.addEventListener('pointerup', function (e) {
            if (!dragging) return;
            dragging = false; viewport.classList.remove('drag');
            var moved = (e.clientX - startX) * sign * -1; // positive = towards the next slide
            var next = index + (Math.abs(moved) > 60 ? (moved > 0 ? 1 : -1) : 0);
            go(next);
        });
        window.addEventListener('resize', function () { go(index); });

        // Slides arrive staggered when the section scrolls in.
        if (window.ScrollTrigger && !reduce) {
            gsap.from(slides, { y: 60, opacity: 0, rotateX: -15, transformPerspective: 800, duration: 0.9, stagger: 0.12, ease: 'power3.out', scrollTrigger: { trigger: root, start: 'top 85%' } });
            gsap.to('.tour .ring.r1', { rotate: 360, duration: 60, ease: 'none', repeat: -1 });
            gsap.to('.tour .ring.r3', { rotate: -360, duration: 40, ease: 'none', repeat: -1 });
            gsap.to('.tour .ring.r2', { y: -30, x: 20, duration: 6, ease: 'sine.inOut', yoyo: true, repeat: -1 });
        }
        paint();
        restartProgress();
    }

    // 4. Floating shapes with parallax behind the light sections.
    if (!reduce) {
        var specs = [
            ['#platform', 's-gold', 360, '-80px', null, null, '-100px', -60],
            ['#app', 's-blue', 420, null, '-120px', '-120px', null, 70],
            ['#features', 's-ring', 220, '12%', null, null, '8%', -90],
            ['#features', 's-gold', 300, null, '-60px', '-100px', null, 60],
            ['#how', 's-blue', 340, '-100px', null, null, '-80px', -50]
        ];
        specs.forEach(function (sp) {
            var host = document.querySelector(sp[0]);
            if (!host) return;
            var el = document.createElement('span');
            el.className = 'shape ' + sp[1];
            el.setAttribute('aria-hidden', 'true');
            el.style.width = el.style.height = sp[2] + 'px';
            if (sp[3]) el.style.top = sp[3];
            if (sp[4]) el.style.bottom = sp[4];
            if (sp[5]) el.style.insetInlineStart = sp[5];
            if (sp[6]) el.style.insetInlineEnd = sp[6];
            host.appendChild(el);
            if (window.ScrollTrigger) {
                gsap.to(el, { y: sp[7], ease: 'none', scrollTrigger: { trigger: host, start: 'top bottom', end: 'bottom top', scrub: true } });
            }
            gsap.to(el, { scale: 1.12, rotate: 25, duration: 7 + Math.random() * 4, ease: 'sine.inOut', yoyo: true, repeat: -1 });
        });
    }
})();
