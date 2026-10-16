/* Landing animations on GSAP + ScrollTrigger. Content stays visible without JS. */
(function () {
    if (!window.gsap) return;
    var gsap = window.gsap;
    if (window.ScrollTrigger) gsap.registerPlugin(window.ScrollTrigger);
    var $ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var mm = gsap.matchMedia();

    // Scroll progress + header that tucks away on the way down.
    var bar = document.getElementById('top-bar');
    var prog = document.getElementById('progress');
    if (window.ScrollTrigger && prog) {
        gsap.to(prog, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.2 } });
    }
    if (window.ScrollTrigger && bar) {
        window.ScrollTrigger.create({
            start: 0, end: 'max',
            onUpdate: function (self) {
                bar.classList.toggle('scrolled', self.scroll() > 12);
                var hide = self.direction === 1 && self.scroll() > 400;
                gsap.to(bar, { yPercent: hide ? -100 : 0, duration: 0.35, ease: 'power2.out', overwrite: true });
            }
        });
    }

    // Split the headline into words for a staggered entrance.
    var h1 = document.querySelector('.hero h1');
    if (h1) {
        (function split(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (n) {
                if (n.nodeType === 3) {
                    var frag = document.createDocumentFragment();
                    n.textContent.split(/(\s+)/).forEach(function (part) {
                        if (!part) return;
                        if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(' ')); return; }
                        var w = document.createElement('span');
                        w.className = 'w';
                        w.textContent = part;
                        frag.appendChild(w);
                    });
                    node.replaceChild(frag, n);
                } else if (n.nodeType === 1) {
                    split(n);
                }
            });
        })(h1);
    }

    mm.add('(prefers-reduced-motion: no-preference)', function () {
        // 1. Hero intro timeline.
        var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
        tl.from('.hero .eyebrow', { y: 20, opacity: 0, duration: 0.6 })
          .from('.hero h1 .w', { yPercent: 110, opacity: 0, rotate: 4, duration: 0.8, stagger: 0.07 }, '-=0.3')
          .from('.hero .lead', { y: 24, opacity: 0, duration: 0.7 }, '-=0.5')
          .from('.hero .cta .btn', { y: 20, opacity: 0, scale: 0.9, duration: 0.6, stagger: 0.12, ease: 'back.out(1.7)' }, '-=0.4')
          .from('.quote-card', { x: -60, opacity: 0, rotate: -3, duration: 1, ease: 'power4.out' }, '-=1.1');

        // 2. Floating quote card + drifting aurora blobs.
        gsap.to('.quote-card', { y: -12, duration: 3.2, ease: 'sine.inOut', yoyo: true, repeat: -1, delay: 1.5 });
        $('.hero .blob').forEach(function (b, i) {
            gsap.to(b, { x: (i % 2 ? -1 : 1) * 70, y: (i % 2 ? 1 : -1) * 50, scale: 1.18, duration: 9 + i * 3, ease: 'sine.inOut', yoyo: true, repeat: -1 });
        });
        if (window.ScrollTrigger) {
            gsap.to('.hero .grid-bg', { yPercent: 18, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
            gsap.to('.hero .hero-grid', { yPercent: -8, opacity: 0.4, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'center top', end: 'bottom top', scrub: true } });
        }

        // 3. Pointer parallax in the hero.
        var hero = document.querySelector('.hero');
        if (hero) {
            hero.addEventListener('pointermove', function (e) {
                var r = hero.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width - 0.5;
                var y = (e.clientY - r.top) / r.height - 0.5;
                gsap.to('.hero .b1', { x: x * 60, y: y * 40, duration: 1.2, overwrite: 'auto' });
                gsap.to('.hero .b2', { x: -x * 80, y: -y * 50, duration: 1.4, overwrite: 'auto' });
                gsap.to('.hero .quote-card', { rotateY: x * 8, rotateX: -y * 8, transformPerspective: 800, duration: 0.8, overwrite: 'auto' });
            });
        }

        if (!window.ScrollTrigger) return;
        var ST = window.ScrollTrigger;

        // 4. Section heads: number slides in, underline draws.
        $('.sec-head').forEach(function (head) {
            var t = gsap.timeline({ scrollTrigger: { trigger: head, start: 'top 85%' } });
            t.from(head.querySelector('.num'), { x: 40, opacity: 0, duration: 0.5 })
             .from(head.querySelector('h2'), { y: 30, opacity: 0, duration: 0.7, ease: 'power3.out' }, '-=0.25')
             .fromTo(head.querySelector('h2'), { '--u': 0 }, { '--u': 1, duration: 0.9, ease: 'power2.inOut' }, '-=0.4');
            var p = head.querySelector('p');
            if (p) t.from(p, { y: 20, opacity: 0, duration: 0.6 }, '-=0.6');
        });

        // 5. Cards rise in batches, with a small icon pop.
        ST.batch('.card', {
            start: 'top 88%',
            onEnter: function (els) {
                gsap.fromTo(els, { y: 50, opacity: 0, scale: 0.94 }, { y: 0, opacity: 1, scale: 1, duration: 0.8, stagger: 0.1, ease: 'power3.out', overwrite: true });
                gsap.fromTo(els.map(function (e) { return e.querySelector('.badge, .n'); }).filter(Boolean),
                    { scale: 0, rotate: -40 }, { scale: 1, rotate: 0, duration: 0.8, stagger: 0.1, delay: 0.2, ease: 'elastic.out(1, 0.55)' });
            }
        });
        gsap.set('.card', { opacity: 0 });

        // 6. The two product panels slide in from opposite sides; list items follow.
        $('.panel').forEach(function (panel, i) {
            var from = i % 2 === 0 ? 80 : -80;
            var t = gsap.timeline({ scrollTrigger: { trigger: panel, start: 'top 82%' } });
            t.from(panel, { x: from, opacity: 0, duration: 0.9, ease: 'power3.out' })
             .from(panel.querySelectorAll('li'), { x: from / 4, opacity: 0, duration: 0.5, stagger: 0.12 }, '-=0.4');
        });

        // 7. Stats: counters with a pop.
        $('.stats strong').forEach(function (el) {
            var m = el.textContent.match(/^(\+?)(\d+)$/);
            gsap.from(el.parentElement, { y: 40, opacity: 0, duration: 0.7, ease: 'power3.out', scrollTrigger: { trigger: el, start: 'top 90%' } });
            if (!m) return;
            var o = { v: 0 };
            gsap.to(o, {
                v: parseInt(m[2], 10), duration: 1.6, ease: 'power2.out', snap: { v: 1 },
                scrollTrigger: { trigger: el, start: 'top 90%' },
                onUpdate: function () { el.textContent = m[1] + o.v; }
            });
            el.textContent = m[1] + '0';
        });

        // 8. Download block scales in; buttons bounce.
        gsap.from('.download', { scale: 0.92, opacity: 0, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.download', start: 'top 85%' } });
        gsap.from('.download .btn', { y: 30, opacity: 0, duration: 0.6, stagger: 0.12, ease: 'back.out(1.8)', scrollTrigger: { trigger: '.download', start: 'top 70%' } });

        // 9. Footer columns.
        gsap.from('footer .cols > div', { y: 30, opacity: 0, duration: 0.7, stagger: 0.15, scrollTrigger: { trigger: 'footer', start: 'top 92%' } });

        // 10. Cards: 3D tilt + spotlight; icons bob gently.
        $('.card').forEach(function (card) {
            var qx = gsap.quickTo(card, 'rotateY', { duration: 0.4, ease: 'power2.out' });
            var qy = gsap.quickTo(card, 'rotateX', { duration: 0.4, ease: 'power2.out' });
            gsap.set(card, { transformPerspective: 700 });
            card.addEventListener('pointermove', function (e) {
                var r = card.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width;
                var y = (e.clientY - r.top) / r.height;
                card.style.setProperty('--mx', (x * r.width) + 'px');
                card.style.setProperty('--my', (y * r.height) + 'px');
                qx((x - 0.5) * 10);
                qy(-(y - 0.5) * 10);
            });
            card.addEventListener('pointerleave', function () { qx(0); qy(0); });
        });
        $('.badge').forEach(function (b, i) {
            gsap.to(b, { y: -5, duration: 1.8 + (i % 3) * 0.3, ease: 'sine.inOut', yoyo: true, repeat: -1, delay: i * 0.12 });
        });

        // 11. Magnetic buttons.
        $('.btn').forEach(function (btn) {
            var mx = gsap.quickTo(btn, 'x', { duration: 0.4, ease: 'power3.out' });
            var my = gsap.quickTo(btn, 'y', { duration: 0.4, ease: 'power3.out' });
            btn.addEventListener('pointermove', function (e) {
                var r = btn.getBoundingClientRect();
                mx((e.clientX - (r.left + r.width / 2)) * 0.25);
                my((e.clientY - (r.top + r.height / 2)) * 0.35);
            });
            btn.addEventListener('pointerleave', function () { mx(0); my(0); });
        });

        // 12. Nav links: underline slide on hover.
        $('.nav ul a').forEach(function (a) {
            a.addEventListener('pointerenter', function () { gsap.fromTo(a, { y: 0 }, { y: -2, duration: 0.2, yoyo: true, repeat: 1 }); });
        });

        // 13. Smooth anchor scrolling that respects the header.
        $('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var id = a.getAttribute('href');
                var target = id.length > 1 && document.querySelector(id);
                if (!target) return;
                e.preventDefault();
                var top = target.getBoundingClientRect().top + window.pageYOffset - 70;
                var o = { y: window.pageYOffset };
                gsap.to(o, { y: top, duration: 1, ease: 'power3.inOut', onUpdate: function () { window.scrollTo(0, o.y); } });
            });
        });

        return function () { ST.getAll().forEach(function (s) { s.kill(); }); };
    });
})();
