/* Notifikasi (toast) tengah atas, banner koneksi, dan tombol loading. Berdiri sendiri, tidak bergantung app.js. */
(() => {
    const box = document.getElementById('toasts') || document.body.appendChild(Object.assign(document.createElement('div'), { id: 'toasts', className: 'toasts' }));

    window.notify = (teks, tipe = 'sukses', ms = 5000) => {
        const t = Object.assign(document.createElement('div'), { className: 'toast toast-' + tipe, textContent: teks });
        t.setAttribute('role', tipe === 'error' ? 'alert' : 'status');
        t.style.setProperty('--ms', ms + 'ms');
        const tutup = () => { t.classList.add('out'); setTimeout(() => t.remove(), 300); };
        t.onclick = tutup;
        box.appendChild(t);
        setTimeout(tutup, ms);
    };
    if (window.__flash) window.notify(...window.__flash);

    // Banner "harap coba lagi" saat koneksi terputus
    let banner;
    addEventListener('offline', () => {
        if (banner) return;
        banner = Object.assign(document.createElement('div'), { className: 'net-banner', innerHTML: '<span>\u26A0 Koneksi terputus. Harap periksa jaringan lalu coba lagi.</span><button type="button">Coba lagi</button>' });
        banner.lastChild.onclick = () => navigator.onLine && location.reload();
        document.body.prepend(banner);
    });
    addEventListener('online', () => { if (banner) { banner.remove(); banner = null; window.notify('Koneksi pulih. Anda kembali online.'); } });

    // Tombol kirim: tampilkan loading dan cegah klik ganda
    document.addEventListener('submit', e => {
        const f = e.target;
        if (e.defaultPrevented || f.id === 'loginForm') return;
        if (f.dataset.busy) return e.preventDefault();
        f.dataset.busy = 1;
        const b = e.submitter || f.querySelector('[type=submit]');
        if (b) b.classList.add('is-loading');
    });
    addEventListener('pageshow', () => document.querySelectorAll('form[data-busy]').forEach(f => {
        delete f.dataset.busy;
        f.querySelectorAll('.is-loading').forEach(b => b.classList.remove('is-loading'));
    }));
})();


// Menu mobile: ketuk "Laboratorium" / nama akun untuk membuka & menutup submenu
(() => {
    const mobile = matchMedia('(max-width: 960px)');
    document.addEventListener('click', e => {
        const a = e.target.closest('.nav li.has-sub > a');
        if (!a || !mobile.matches) return;
        e.preventDefault();
        const li = a.parentElement;
        const buka = !li.classList.contains('open');
        li.classList.toggle('open', buka);
        a.setAttribute('aria-expanded', buka);
    });
})();