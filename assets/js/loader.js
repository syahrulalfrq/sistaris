/* Indikator memuat interaktif: bilah progres di atas + chip "Memuat..." saat pindah halaman / kirim form. */
(() => {
    if (window.pageLoader) return;
    const bar = Object.assign(document.createElement('div'), { className: 'nprog', innerHTML: '<i></i>' });
    const chip = Object.assign(document.createElement('div'), { className: 'pload', hidden: true, innerHTML: '<span class="spin"></span><b>Memuat\u2026</b>' });
    chip.setAttribute('role', 'status');
    chip.setAttribute('aria-live', 'polite');
    const pasang = () => document.body.append(bar, chip);
    document.body ? pasang() : addEventListener('DOMContentLoaded', pasang);

    const isi = bar.firstChild;
    let tm, tutup;
    const mulai = () => {
        clearInterval(tm); clearTimeout(tutup);
        let w = 8;
        isi.style.width = w + '%';
        bar.classList.add('on');
        chip.hidden = false;
        document.documentElement.classList.add('is-navigating');
        tm = setInterval(() => { w += (92 - w) * 0.1; isi.style.width = w + '%'; }, 120);
    };
    const selesai = () => {
        clearInterval(tm);
        isi.style.width = '100%';
        chip.hidden = true;
        document.documentElement.classList.remove('is-navigating');
        tutup = setTimeout(() => { bar.classList.remove('on'); isi.style.width = '0'; }, 300);
    };
    window.pageLoader = { mulai, selesai };

    // Saat halaman baru dibuka
    mulai();
    const tuntas = () => setTimeout(selesai, 250);
    document.readyState === 'complete' ? tuntas() : addEventListener('load', tuntas);
    addEventListener('pageshow', e => { if (e.persisted) selesai(); });

    // Klik tautan internal: tampilkan loading sebentar agar terasa interaktif
    document.addEventListener('click', e => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-konfirm') || a.hasAttribute('data-no-loader')) return;
        const h = a.getAttribute('href');
        if (!h || h[0] === '#' || /^(mailto:|tel:|javascript:)/i.test(h)) return;
        let u;
        try { u = new URL(a.href, location.href); } catch (_) { return; }
        if (u.origin !== location.origin) return;
        if (u.pathname === location.pathname && u.search === location.search && u.hash) return;
        e.preventDefault();
        mulai();
        setTimeout(() => { location.href = u.href; }, 280);
    });

    // Kirim form biasa (bukan pencarian langsung / dialog konfirmasi)
    document.addEventListener('submit', e => {
        const f = e.target;
        setTimeout(() => {
            if (e.defaultPrevented || f.hasAttribute('data-live') || f.hasAttribute('data-konfirm') || f.target === '_blank') return;
            mulai();
        }, 0);
    });
})();
