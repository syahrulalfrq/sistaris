<?php
/**
 * Footer informasi (dipakai halaman setelah login dan beranda):
 * logo, kontak, tautan website, tentang (rata kiri-kanan), media sosial, peta satelit, dan statistik pengunjung.
 */
$v    = pengunjung();
// URL peta lengkap dengan parameter &t=k (mode Satelit/Earth) dan &z=18 (zoom dekat)
$peta = 'https://maps.google.com/maps?q=' . urlencode(MAPS_QUERY) . '&t=k&z=18&ie=UTF8&iwloc=&output=embed';
$buka = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(MAPS_QUERY);
?>
<div class="foot-main noprint">
    <div class="container fgrid">
        <div class="f-brand">
            <div class="logos">
                <img src="assets/img/logo-kemenag.png" alt="Logo Kementerian Agama">
                <img src="assets/img/logo-mtsn1.png" alt="Logo <?= SEKOLAH ?>">
            </div>
            <h3><?= SEKOLAH ?></h3>
            <p class="motto"><?= MOTTO ?></p>
        </div>

        <div class="f-col">
            <h4>Kontak</h4>
            <p class="f-name"><?= SEKOLAH ?></p>
            <ul class="contact">
                <li><?= ikon('pin') ?><span><?= ALAMAT ?>, <?= DAERAH ?></span></li>
                <li><?= ikon('phone') ?><span>Telp/WA: <?= TELP ?></span></li>
                <li><?= ikon('mail') ?><span>Email: <a href="mailto:<?= EMAIL ?>"><?= EMAIL ?></a></span></li>
                <li><?= ikon('globe') ?><span>Website: <a href="<?= WEB_SEKOLAH ?>" target="_blank" rel="noopener">mtsn1klaten.sch.id</a></span></li>
            </ul>
        </div>

        <div class="f-col">
            <h4>Link Website</h4>
            <ul class="f-links">
                <?php foreach (LINK_WEB as [$nama, $url]): ?>
                    <li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($nama) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="f-col f-about">
            <h4>Tentang</h4>
            <p class="just"><?= TENTANG ?></p>
            <a class="more" href="<?= WEB_SEKOLAH ?>" target="_blank" rel="noopener"><b>Selengkapnya</b> &#8599;</a>
        </div>
    </div>

    <div class="container fgrid2">
        <div class="f-map">
            <h4>Lokasi Madrasah</h4>
            <div class="map-frame" style="height: 280px; min-height: 280px;">
                <iframe title="Peta lokasi <?= SEKOLAH ?>" src="<?= e($peta) ?>" style="width: 100%; height: 100%; border: 0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            <a class="map-open" href="<?= e($buka) ?>" target="_blank" rel="noopener">Buka di Google Maps &#8599;</a>
        </div>

        <div class="f-side">
            <div>
                <h4>Media Sosial Resmi</h4>
                <ul class="sosmed">
                    <?php foreach (SOSMED as [$ik, $nama, $akun, $url]): ?>
                        <li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= ikon($ik) ?><span><b><?= e($nama) ?></b><small><?= e($akun) ?></small></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Jam Layanan Lab</h4>
                <ul class="visitor">
                    <?php foreach (JAM_LAYANAN as [$hari, $jam]): ?>
                        <li><?= e($hari) ?> <b><?= e($jam) ?></b></li>
                    <?php endforeach; ?>
                </ul>
                <ul class="sosmed wa-one">
                    <li><a href="https://wa.me/<?= WA_ADMIN ?>?text=<?= rawurlencode('Halo Admin Lab, saya ingin bertanya tentang peminjaman barang laboratorium.') ?>" target="_blank" rel="noopener"><?= ikon('whatsapp') ?><span><b>Hubungi Admin Lab</b><small>Tanya peminjaman via WhatsApp</small></span></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>