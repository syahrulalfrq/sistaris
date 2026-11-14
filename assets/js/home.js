/* =========================================================
   Beranda: animasi logo, partikel interaktif, ketikan motto,
   hitung naik, muncul saat digulir, menu, dan efek sorot.
   Semua animasi mengikuti pengaturan "kurangi gerakan".
   ========================================================= */
(() => {
    'use strict';
    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const kurangi = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const sentuh  = matchMedia('(hover: none)').matches;

    /* ---------- Navbar: bayangan, menu seluler, penanda bagian aktif ---------- */
    const nav = $('#pnav'), menu = $('#pmenu'), burger = $('#burger');
    const geser = () => nav.classList.toggle('scrolled', scrollY > 24);
    geser();
    addEventListener('scroll', geser, { passive: true });

    const tutup = () => { menu.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); };
    burger.addEventListener('click', () => {
        const buka = menu.classList.toggle('open');
        burger.setAttribute('aria-expanded', String(buka));
    });
    menu.addEventListener('click', e => { if (e.target.closest('a')) tutup(); });
    addEventListener('keydown', e => { if (e.key === 'Escape') tutup(); });

    const tautan = $$('#pmenu a[href^="#"]');
    const bagian = tautan.map(a => $(a.getAttribute('href'))).filter(Boolean);
    if ('IntersectionObserver' in window) {
        const spy = new IntersectionObserver(es => {
            es.forEach(en => {
                if (!en.isIntersecting) return;
                tautan.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + en.target.id));
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        bagian.forEach(s => spy.observe(s));
    }

    /* ---------- Muncul saat digulir + bar & hitung naik ---------- */
    const hitung = el => {
        const akhir = +el.dataset.count || 0;
        if (kurangi || akhir === 0) { el.textContent = akhir.toLocaleString('id-ID'); return; }
        const dur = 1400, t0 = performance.now();
        const ease = t => 1 - Math.pow(1 - t, 4);
        const step = now => {
            const p = Math.min(1, (now - t0) / dur);
            el.textContent = Math.round(akhir * ease(p)).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    const tampil = el => {
        el.classList.add('in');
        $$('[data-count]', el).concat(el.matches('[data-count]') ? [el] : []).forEach(hitung);
        $$('[data-w]', el).forEach(b => { b.style.width = b.dataset.w + '%'; });
    };

    const targets = $$('.rv');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(es => {
            es.forEach(en => { if (en.isIntersecting) { tampil(en.target); io.unobserve(en.target); } });
        }, { threshold: .15, rootMargin: '0px 0px -6% 0px' });
        targets.forEach(t => io.observe(t));
    } else {
        targets.forEach(tampil);
    }

    /* ---------- Efek sorot mengikuti kursor pada kartu ---------- */
    document.addEventListener('pointermove', e => {
        const k = e.target.closest && e.target.closest('.spot');
        if (!k) return;
        const r = k.getBoundingClientRect();
        k.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        k.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });

    /* ---------- Motto diketik bergantian ---------- */
    const kt = $('#ketik');
    if (kt && !kurangi) {
        const kata = JSON.parse(kt.dataset.kata || '[]');
        let i = 0, n = kata[0].length, hapus = true; // mulai dari kata pertama yang sudah tampil
        const jalan = () => {
            const k = kata[i];
            if (hapus) {
                n--;
                kt.textContent = k.slice(0, Math.max(n, 0));
                if (n <= 0) { hapus = false; i = (i + 1) % kata.length; n = 0; return setTimeout(jalan, 250); }
                return setTimeout(jalan, 55);
            }
            n++;
            kt.textContent = kata[i].slice(0, n);
            if (n >= kata[i].length) { hapus = true; return setTimeout(jalan, 1600); }
            setTimeout(jalan, 95);
        };
        setTimeout(jalan, 2200);
    }

    /* ---------- Logo: miring mengikuti kursor + ledakan saat diklik ---------- */
    const stage = $('#stage'), tilt = $('#tilt'), medal = $('#medal');
    if (stage && !kurangi) {
        const batas = 16;
        const arah = (x, y) => {
            const r = stage.getBoundingClientRect();
            const px = (x - r.left) / r.width - .5, py = (y - r.top) / r.height - .5;
            tilt.style.transform = `rotateY(${px * batas * 2}deg) rotateX(${-py * batas * 2}deg)`;
        };
        const hero = $('#beranda');
        hero.addEventListener('pointermove', e => { if (!sentuh) arah(e.clientX, e.clientY); });
        hero.addEventListener('pointerleave', () => { tilt.style.transform = ''; });
        stage.addEventListener('pointermove', e => { if (sentuh) arah(e.clientX, e.clientY); });
        stage.addEventListener('pointerup', () => { if (sentuh) tilt.style.transform = ''; });
    }

    if (medal) {
        let sibuk = false;
        // Setelah animasi masuk selesai, lepaskan agar tidak terulang saat animasi putar berakhir.
        const siap = () => medal.classList.add('ready');
        medal.addEventListener('animationend', e => { if (e.animationName === 'intro') siap(); });
        setTimeout(siap, 1700);
        medal.addEventListener('click', () => {
            if (sibuk || !medal.classList.contains('ready')) return;
            sibuk = true;
            medal.classList.remove('berputar'); void medal.offsetWidth; medal.classList.add('berputar');
            if (!kurangi) {
                for (let i = 0; i < 18; i++) {
                    const s = document.createElement('i');
                    s.className = 'spark' + (i % 3 === 0 ? ' w' : '');
                    const a = (Math.PI * 2 * i) / 18 + Math.random() * .3, d = 120 + Math.random() * 90;
                    s.style.cssText = `--dx:${Math.cos(a) * d}px;--dy:${Math.sin(a) * d}px;margin:-4px 0 0 -4px`;
                    stage.appendChild(s);
                    setTimeout(() => s.remove(), 1000);
                }
            }
            setTimeout(() => { sibuk = false; medal.classList.remove('berputar'); }, 1250);
        });
    }

    /* ---------- Partikel interaktif (kanvas) ---------- */
    const cv = $('#partikel');
    if (cv && !kurangi) {
        const ctx = cv.getContext('2d');
        let W = 0, H = 0, dpr = 1, ps = [], aktif = true, raf = 0;
        const mouse = { x: -999, y: -999 };

        const ukur = () => {
            dpr = Math.min(devicePixelRatio || 1, 2);
            W = cv.clientWidth; H = cv.clientHeight;
            cv.width = W * dpr; cv.height = H * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            const jml = Math.round(Math.min(sentuh ? 30 : 70, Math.max(sentuh ? 20 : 26, (W * H) / 20000))); // lebih sedikit di HP
            ps = Array.from({ length: jml }, () => ({
                x: Math.random() * W, y: Math.random() * H,
                vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35,
                r: Math.random() * 2 + .8,
            }));
        };

        const gambar = () => {
            ctx.clearRect(0, 0, W, H);
            for (let i = 0; i < ps.length; i++) {
                const p = ps[i];
                // Dorongan lembut menjauhi kursor
                const dx = p.x - mouse.x, dy = p.y - mouse.y, d2 = dx * dx + dy * dy;
                if (d2 < 14000) { const f = (1 - d2 / 14000) * .9; p.x += (dx / Math.sqrt(d2 || 1)) * f * 2.2; p.y += (dy / Math.sqrt(d2 || 1)) * f * 2.2; }
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > W) p.vx *= -1;
                if (p.y < 0 || p.y > H) p.vy *= -1;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, 6.2832);
                ctx.fillStyle = 'rgba(255,255,255,.7)';
                ctx.fill();
                for (let j = i + 1; j < ps.length; j++) {
                    const q = ps[j], ex = p.x - q.x, ey = p.y - q.y, e2 = ex * ex + ey * ey;
                    if (e2 < 11000) {
                        ctx.strokeStyle = `rgba(240,213,106,${(1 - e2 / 11000) * .35})`;
                        ctx.lineWidth = 1;
                        ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                    }
                }
            }
            if (aktif) raf = requestAnimationFrame(gambar);
        };

        const hero = $('#beranda');
        hero.addEventListener('pointermove', e => {
            const r = cv.getBoundingClientRect();
            mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top;
        });
        hero.addEventListener('pointerleave', () => { mouse.x = mouse.y = -999; });

        let t;
        addEventListener('resize', () => { clearTimeout(t); t = setTimeout(ukur, 150); });
        ukur();
        gambar();

        // Hemat baterai: berhenti saat hero tidak terlihat atau tab disembunyikan.
        const jeda = ok => { if (ok && !aktif) { aktif = true; gambar(); } else if (!ok) { aktif = false; cancelAnimationFrame(raf); } };
        new IntersectionObserver(es => jeda(es[0].isIntersecting && !document.hidden)).observe(hero);
        document.addEventListener('visibilitychange', () => jeda(!document.hidden));
    }
})();