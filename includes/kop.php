<?php
/** Kop surat dokumen cetak (hanya tampil saat dicetak). Dipakai laporan.php dan pengguna.php. */
?>
<div class="kop only-print">
    <img src="assets/img/logo-kemenag.png" alt="">
    <div class="kop-teks">
        <div class="kop-1"><?= KOP_BARIS[0] ?></div>
        <div class="kop-2"><?= KOP_BARIS[1] ?></div>
        <div class="kop-3"><?= KOP_BARIS[2] ?></div>
        <div class="kop-4"><?= KOP_ALAMAT ?></div>
        <div class="kop-5">Web : <?= KOP_WEB ?> &nbsp;&nbsp;&nbsp;&nbsp; email : <?= EMAIL ?></div>
    </div>
    <img src="assets/img/logo-mtsn1.png" alt="">
</div>
