/* =========================================================
   Inventaris Laboratorium - MTs Negeri 1 Klaten
   Interaksi umum: pencarian langsung, validasi form, dialog
   hapus, notifikasi, ripple, pengunjung, dan peta lokasi.
   ========================================================= */
(() => {
    'use strict';
    const $  = (s, r = document) => r.querySelector(s);     const $$ = (s, r = document) => [...r.querySelectorAll(s)];

    /* ---------- Notifikasi ---------- */
    function toast(teks, ms = 4200) {
        const box = $('#toasts');         if (!box) return;         const t = document.createElement('div');         t.className = 'toast';         t.setAttribute('role', 'status');         t.textContent = teks;         box.appendChild(t);         setTimeout(() => {             t.classList.add('out');             t.addEventListener('animationend', () => t.remove(), { once: true });         }, ms);     }     window.toast = toast;      // Pesan flash dari server tampil sebagai toast.     $$('[data-toast]').forEach(el => { toast(el.textContent.trim(), 5000); el.remove(); });

    /* ---------- Efek ripple pada tombol ---------- */
    document.addEventListener('pointerdown', e => {
        const b = e.target.closest('.btn');
        if (!b || b.disabled) return;
        const r = b.getBoundingClientRect(), d = Math.max(r.width, r.height);
        const s = document.createElement('span');
        s.className = 'ripple';
        s.style.cssText = `width:${d}px;height:${d}px;left:${e.clientX - r.left - d / 2}px;top:${e.clientY - r.top - d / 2}px`;
        b.appendChild(s);
        setTimeout(() => s.remove(), 650);
    });

    /* ---------- Kembali ke atas ---------- */
    const up = $('#toTop');
    if (up) {
        addEventListener('scroll', () => { up.hidden = scrollY < 420; }, { passive: true });
        up.addEventListener('click', () => scrollTo({ top: 0, behavior: 'smooth' }));
    }

    /* ---------- Tampilkan / sembunyikan password (Ikon SVG Mata) ---------- */
    const SVG_EYE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15c2.5-4.5 5.5-7 9-7s6.5 2.5 9 7"></path><circle cx="12" cy="15" r="2.8"></circle></svg>`;
    const SVG_EYE_OFF = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15c2.5-4.5 5.5-7 9-7s6.5 2.5 9 7"></path><circle cx="12" cy="15" r="2.8"></circle><line x1="4" y1="4" x2="20" y2="20"></line></svg>`;

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-toggle-pw]');
        if (!b) return;
        const i = document.getElementById(b.dataset.togglePw);
        if (!i) return;
        const tampil = i.type === 'password';
        i.type = tampil ? 'text' : 'password';
        b.innerHTML = tampil ? SVG_EYE_OFF : SVG_EYE;
        b.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
    });

    /* ---------- Pencarian langsung ---------- */
    const filter = $('form[data-live]');
    if (filter) {
        const target = $(filter.dataset.live);
        const cari = $('[name=cari]', filter);         let timer, ctrl;          const bangunUrl = () => {             const q = new URLSearchParams();             new FormData(filter).forEach((v, k) => { v = String(v).trim(); if (v !== '' && v !== '0') q.set(k, v); });             return location.pathname + (q.toString() ? '?' + q : '');         };          async function muat(url, gulir = false) {             ctrl && ctrl.abort();             ctrl = new AbortController();             const mine = ctrl, t0 = performance.now();             target.classList.add('loading');             try {                 const u = new URL(url, location.href);                 u.searchParams.set('partial', '1');                 const r = await fetch(u, { signal: ctrl.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });                 if (r.redirected && /login\.php/.test(r.url)) { location.href = 'login.php'; return; }                 if (!r.ok) throw new Error(r.status);                 const html = await r.text();                 const sisa = 450 - (performance.now() - t0);                 if (sisa > 0) await new Promise(ok => setTimeout(ok, sisa));                 if (mine.signal.aborted) return;                 target.innerHTML = html;                 u.searchParams.delete('partial');                 history.replaceState(null, '', u.pathname + u.search);                 if (gulir) target.scrollIntoView({ behavior: 'smooth', block: 'start' });             } catch (err) {                 if (err.name !== 'AbortError') toast('Gagal memuat data. Periksa koneksi lalu coba lagi.');             } finally {                 if (mine === ctrl) target.classList.remove('loading');             }         }          filter.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); muat(bangunUrl()); });         cari.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => muat(bangunUrl()), 300); });         $$('select', filter).forEach(s => s.addEventListener('change', () => muat(bangunUrl())));

        const reset = $('[data-reset]', filter);         reset && reset.addEventListener('click', e => {             e.preventDefault();             cari.value = '';             $$('select', filter).forEach(s => { s.selectedIndex = 0; });
            muat(bangunUrl());
            cari.focus();
        });

        target.addEventListener('click', e => {
            const a = e.target.closest('.pager a');
            if (!a) return;
            e.preventDefault();
            muat(a.getAttribute('href').replace(/^\?/, location.pathname + '?'), true);
        });

        // Pintasan: tekan "/" untuk fokus ke kolom cari.
        document.addEventListener('keydown', e => {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
            e.preventDefault();
            cari.focus();
            cari.select();
        });
    }

    /* ---------- Dialog konfirmasi (hapus, edit, simpan, setujui, dst.) ---------- */
    const JENIS = {
        hapus:   { ico: '\u{1F5D1}', warna: 'del',  judul: 'Hapus data ini?',        teks: 'Data yang dihapus tidak dapat dikembalikan.',          ya: 'Ya, Hapus' },
        edit:    { ico: '\u270E',    warna: 'edit', judul: 'Ubah data ini?',         teks: 'Anda akan membuka formulir untuk mengubah data.',      ya: 'Ya, Lanjutkan' },
        simpan:  { ico: '\u{1F4BE}', warna: 'edit', judul: 'Simpan perubahan?',      teks: 'Perubahan akan menggantikan data sebelumnya.',         ya: 'Ya, Simpan' },
        setuju:  { ico: '\u2714',    warna: 'ok',   judul: 'Setujui peminjaman?',    teks: 'Unit akan tercatat dipinjam dan tetap menjadi aset madrasah.', ya: 'Ya, Setujui' },
        tolak:   { ico: '\u2716',    warna: 'del',  judul: 'Tolak peminjaman?',      teks: 'Pengajuan ini akan ditandai ditolak.',                 ya: 'Ya, Tolak' },
        kembali: { ico: '\u21A9',    warna: 'ok',   judul: 'Tandai sudah kembali?',  teks: 'Stok tersedia akan bertambah kembali.',                ya: 'Ya, Sudah Kembali' },
        keluar:  { ico: '\u{1F6AA}', warna: 'edit', judul: 'Apakah Anda yakin ingin keluar?', teks: 'Sesi Anda akan diakhiri. Anda perlu masuk kembali untuk mengakses sistem.', ya: 'Ya, Keluar' },
        batal:   { ico: '\u26A0',    warna: 'del',  judul: 'Batalkan pengajuan?',    teks: 'Pengajuan pinjam ini akan dibatalkan.',                ya: 'Ya, Batalkan' },
    };
    let dlg = null, selesai = null;

    function konfirmasi(jenis, nama, o = {}) {
        const j = Object.assign({}, JENIS[jenis] || JENIS.hapus);
        if (o.judul) j.judul = o.judul;
        if (o.teks)  j.teks  = o.teks;
        return new Promise(res => {
            if (typeof HTMLDialogElement !== 'function') { res(confirm(j.judul + (nama ? '\n' + nama : ''))); return; }
            if (!dlg) {
                dlg = document.createElement('dialog');
                dlg.className = 'konfirm';
                dlg.setAttribute('aria-labelledby', 'kfJudul');
                dlg.innerHTML = `
                    <div class="in">
                        <div class="ico" aria-hidden="true"></div>
                        <h3 id="kfJudul"></h3>
                        <p class="kf-teks"></p>
                        <span class="nama"></span>
                    </div>
                    <div class="aksi">
                        <button type="button" class="btn alt" data-x="batal">Batal</button>
                        <button type="button" class="btn" data-x="ya"></button>
                    </div>`;
                document.body.appendChild(dlg);
                dlg.addEventListener('click', e => {
                    const x = e.target.dataset && e.target.dataset.x;
                    if (e.target === dlg || x === 'batal') { dlg.close('batal'); }
                    if (x === 'ya') { dlg.close('ya'); }
                });
                dlg.addEventListener('close', () => { const f = selesai; selesai = null; f && f(dlg.returnValue === 'ya'); });
            }
            $('.ico', dlg).textContent = j.ico;
            $('.ico', dlg).dataset.t = j.warna;
            $('h3', dlg).textContent = j.judul;
            $('.kf-teks', dlg).textContent = j.teks;
            const n = $('.nama', dlg);
            n.textContent = nama || '';
            n.hidden = !nama;
            const ya = $('[data-x=ya]', dlg);
            ya.textContent = j.ya;
            ya.className = 'btn ' + (j.warna === 'del' ? 'del' : j.warna === 'edit' ? 'edit' : '');
            dlg.returnValue = '';
            selesai = res;
            dlg.showModal();
            $('[data-x=batal]', dlg).focus();
        });
    }
    window.konfirmasi = konfirmasi;

    // Formulir dengan data-konfirm
    document.addEventListener('submit', async e => {
        const f = e.target;
        if (!f.matches || !f.matches('form[data-konfirm]') || e.defaultPrevented) return;
        if (f.dataset.ok === '1') { return; }
        e.preventDefault();
        const submitter = e.submitter;
        const ya = await konfirmasi(f.dataset.konfirm, f.dataset.nama, { judul: f.dataset.judul, teks: f.dataset.teks });
        if (ya) {
            f.dataset.ok = '1';
            const b = $('[data-loading]', f);
            if (b) { b.disabled = true; b.textContent = b.dataset.loading; }
            f.submit();
        } else {
            const b = $('[data-loading]', f);
            if (b) { b.disabled = false; b.textContent = b.dataset.asli || b.textContent; }
            submitter && submitter.focus();
        }
    });

    // Tautan dengan data-konfirm
    document.addEventListener('click', async e => {
        const a = e.target.closest('a[data-konfirm]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        if (await konfirmasi(a.dataset.konfirm, a.dataset.nama, { judul: a.dataset.judul, teks: a.dataset.teks })) {
            location.href = a.href;
        }
    });

    /* ---------- Kode barang otomatis mengikuti laboratorium ---------- */
    const selLab = $('#id_lab[data-kode-url]');
    const kodeEl = $('#kode_barang.auto');
    if (selLab && kodeEl) {
        let reqKode = 0;
        const ambil = async () => {
            const lab = selLab.value;
            if (!lab) { kodeEl.value = ''; return; }
            if (selLab.dataset.labAsli && selLab.value === selLab.dataset.labAsli) {
                kodeEl.value = selLab.dataset.kodeAsli;
                return;
            }
            const no = ++reqKode;
            kodeEl.value = 'Membuat kode...';
            try {
                const q = new URLSearchParams({ lab, id: selLab.dataset.id || 0 });
                const r = await fetch(selLab.dataset.kodeUrl + '?' + q, { credentials: 'same-origin', cache: 'no-store' });
                const d = await r.json();
                if (no !== reqKode) return;
                if (!d.ok) throw new Error(d.pesan);
                kodeEl.value = d.kode;
                kodeEl.classList.remove('flash'); void kodeEl.offsetWidth; kodeEl.classList.add('flash');
            } catch (_) {
                if (no === reqKode) { kodeEl.value = ''; toast('Gagal membuat kode otomatis. Kode akan dibuat saat disimpan.'); }
            }
        };
        selLab.addEventListener('change', ambil);
    }

    /* ---------- Validasi form ---------- */
    function pesan(el) {
        const v = el.validity;
        if (v.valid) return '';
        if (v.valueMissing)    return el.tagName === 'SELECT' ? 'Pilih salah satu opsi.' : 'Kolom ini wajib diisi.';
        if (v.patternMismatch) return el.title || 'Format isian tidak sesuai.';
        if (v.tooShort)        return 'Minimal ' + el.minLength + ' karakter.';
        if (v.tooLong)         return 'Maksimal ' + el.maxLength + ' karakter.';
        if (v.rangeUnderflow)  return 'Nilai minimal ' + el.min + '.';
        if (v.rangeOverflow)   return el.type === 'date' ? 'Tanggal tidak boleh melebihi hari ini.' : 'Nilai maksimal ' + el.max + '.';
        if (v.badInput || v.typeMismatch) return 'Isian tidak valid.';
        return 'Isian tidak valid.';
    }

    function tandai(el, teks) {
        const pemegang = el.closest('.pw') || el;
        let fe = null, n = pemegang.nextElementSibling;
        for (let i = 0; i < 2 && n; i++, n = n.nextElementSibling) {
            if (n.classList.contains('err')) { const x = n; n = n.nextElementSibling; x.remove(); i--; continue; }
            if (n.classList.contains('field-err')) { fe = n; break; }
        }
        if (!fe) {
            fe = document.createElement('small');
            fe.className = 'field-err';
            fe.setAttribute('role', 'alert');
            pemegang.after(fe);
        }
        fe.textContent = teks;
        fe.hidden = !teks;
        el.classList.toggle('invalid', !!teks);
        el.classList.toggle('valid', !teks && el.value !== '');
        el.setAttribute('aria-invalid', teks ? 'true' : 'false');
    }

    const cek = el => { const t = pesan(el); tandai(el, t); return !t; };
    const bidang = f => $$('input:not([type=hidden]):not([type=search]), select, textarea', f).filter(el => !el.disabled);      $$('form[data-validate]').forEach(form => {
        form.addEventListener('focusout', e => { if (e.target.matches('input,select,textarea')) cek(e.target); });
        form.addEventListener('input', e => { if (e.target.classList.contains('invalid') || e.target.classList.contains('valid')) cek(e.target); });
        form.addEventListener('change', e => { if (e.target.matches('select')) cek(e.target); });

        form.addEventListener('submit', e => {
            const salah = bidang(form).filter(el => !cek(el));
            if (salah.length) {
                e.preventDefault();
                salah[0].focus();
                toast('Periksa kembali isian yang bertanda merah (' + salah.length + ' kolom).');
                return;
            }
            const b = $('[data-loading]', form);
            if (b) b.dataset.asli = b.dataset.asli || b.textContent;
            if (b && !form.hasAttribute('data-konfirm')) setTimeout(() => { b.disabled = true; b.textContent = b.dataset.loading; }, 0);
        });
    });

    $$('[data-upper]').forEach(el => el.addEventListener('input', () => {         const a = el.selectionStart, z = el.selectionEnd;         el.value = el.value.toUpperCase();         el.setSelectionRange(a, z);     }));      $$
('[data-counter]').forEach(el => {
        const c = document.createElement('span');
        c.className = 'counter';
        el.after(c);
        const hitung = () => {
            c.textContent = el.value.length + ' / ' + el.maxLength;
            c.classList.toggle('max', el.value.length >= el.maxLength);
        };
        el.addEventListener('input', hitung);
        hitung();
    });

    /* ---------- Penghitung pengunjung (diperbarui berkala) ---------- */
    const pv = $('#pengunjung');     if (pv) {         const fmt = n => Number(n).toLocaleString('id-ID');         const segarkan = async () => {             if (document.hidden) return;             try {                 const r = await fetch(pv.dataset.url, { cache: 'no-store', credentials: 'same-origin' });                 const d = await r.json();                 $$('[data-k]', pv).forEach(el => {
                    const baru = el.dataset.k === 'update' ? d.update : fmt(d[el.dataset.k]);
                    if (el.textContent !== baru) {
                        el.textContent = baru;
                        if (el.tagName === 'B') { el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); }
                    }
                });
            } catch (_) { /* abaikan */ }
        };
        setInterval(segarkan, 60000);
    }

    /* ---------- Inisialisasi Peta Leaflet (Utama: Jalan, Opsi: Satelit) ---------- */
    const mapEl = $('#map');
    if (mapEl && typeof L !== 'undefined') {
        const lat = parseFloat(mapEl.dataset.lat || -7.7051);
        const lng = parseFloat(mapEl.dataset.lng || 110.6012);
        const zoom = parseInt(mapEl.dataset.zoom || 15);

        const map = L.map('map').setView([lat, lng], zoom);

        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        });

        const satellite = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            attribution: '© Google Satellite'
        });

        osm.addTo(map);

        L.control.layers({
            "Peta Jalan": osm,
            "Peta Satelit": satellite
        }).addTo(map);

        if (mapEl.dataset.title) {
            L.marker([lat, lng]).addTo(map).bindPopup(mapEl.dataset.title).openPopup();
        }
    }
})();