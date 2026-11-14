<?php
/**
 * Hasil daftar barang (kartu ringkasan + tabel + halaman).
 * Dipakai oleh barang.php, baik saat dimuat penuh maupun saat pencarian langsung.
 */
?>
<div class="cards mini">
    <div class="card"><b><?= $total['jenis'] ?></b><span>Jenis Barang</span></div>
    <div class="card"><b><?= $total['stok'] ?></b><span>Total Aset (unit)</span></div>
    <div class="card pinjam"><b><?= $total['dipinjam'] ?></b><span>Dipinjam (di luar MTsN 1 Klaten)</span></div>
    <div class="card"><b><?= $total['tersedia'] ?></b><span>Tersedia di Lab</span></div>
    <?php if ($cari !== ''): ?>
        <div class="card info"><b>&ldquo;<?= e($cari) ?>&rdquo;</b><span>Kata kunci</span></div>
    <?php endif; ?>
</div>
<p class="note-aset">Unit yang sedang dipinjam <b>tetap dihitung sebagai aset</b> <?= SEKOLAH ?> dan tercantum pada kolom Jumlah.</p>

<section class="panel">
    <h2><?= $lab ? 'Daftar Barang ' . e($title) : 'Daftar Seluruh Barang' ?></h2>
    <div class="scroll">
    <table class="r">
        <thead><tr>
            <th>No</th><th>Kode</th><th>Nama Barang</th><th>Merk</th><th>Kategori</th>
            <?php if (!$lab): ?><th>Laboratorium</th><?php endif; ?>
            <th>Jumlah (Aset)</th><th>Kondisi</th><th>Tgl Masuk</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
            $dip = (int) $r['dipinjam'];
            $ada = max(0, (int) $r['jumlah'] - $dip);
        ?>
            <tr>
                <td data-label="No"><?= $offset + $i + 1 ?></td>
                <td data-label="Kode"><b><?= hl($r['kode_barang'], $cari) ?></b></td>
                <td data-label="Nama Barang"><?= hl($r['nama_barang'], $cari) ?></td>
                <td data-label="Merk"><?= $r['merk'] ? hl($r['merk'], $cari) : '-' ?></td>
                <td data-label="Kategori"><?= hl($r['nama_kategori'], $cari) ?></td>
                <?php if (!$lab): ?><td data-label="Laboratorium"><?= hl($r['nama_lab'], $cari) ?></td><?php endif; ?>
                <td data-label="Jumlah (Aset)" class="jml">
                    <?= $r['jumlah'] . ' ' . e($r['satuan']) ?>
                    <?php if ($dip > 0): ?>
                        <small class="pinjam-ket"><span class="badge b-info"><?= $dip ?> dipinjam</span> &middot; tersedia <?= $ada ?></small>
                    <?php endif; ?>
                </td>
                <td data-label="Kondisi"><span class="badge b-<?= KONDISI[$r['kondisi']] ?>"><?= e($r['kondisi']) ?></span></td>
                <td data-label="Tgl Masuk"><?= tgl($r['tanggal_masuk']) ?></td>
                <td data-label="Aksi" class="acts">
                <?php if ($admin): ?>
                    <a class="btn sm edit" href="form.php?id=<?= $r['id_barang'] ?>" data-konfirm="edit" data-nama="<?= e($r['nama_barang'] . ' (' . $r['kode_barang'] . ')') ?>">Edit</a>
                    <?php if (is_superadmin()): ?>
                    <form method="post" action="hapus.php" class="hapus" data-konfirm="hapus" data-nama="<?= e($r['nama_barang'] . ' (' . $r['kode_barang'] . ')') ?>">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>">
                        <input type="hidden" name="id" value="<?= $r['id_barang'] ?>">
                        <input type="hidden" name="lab" value="<?= $lab ?>">
                        <input type="hidden" name="cari" value="<?= e($cari) ?>">
                        <input type="hidden" name="kondisi" value="<?= e(isset(KONDISI[$kon]) ? $kon : '') ?>">
                        <input type="hidden" name="page" value="<?= $page ?>">
                        <button class="btn sm del">Hapus</button>
                    </form>
                    <?php endif; ?>
                <?php elseif ($ada > 0 && $r['kondisi'] !== 'Rusak Berat'): ?>
                    <a class="btn sm" href="pinjam.php?id=<?= $r['id_barang'] ?>">Pinjam</a>
                <?php else: ?>
                    <span class="muted">Tidak tersedia</span>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $kolom ?>" class="empty">
                <?= $cari !== '' ? 'Tidak ada barang yang cocok dengan &ldquo;' . e($cari) . '&rdquo;. Coba kata kunci lain.' : 'Data barang tidak ditemukan.' ?>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($rows): ?>
    <div class="panel-foot">
        <span class="muted">Menampilkan <?= $offset + 1 ?>&ndash;<?= $offset + count($rows) ?> dari <?= $total['jenis'] ?> data</span>
        <?= pagination($page, $pages) ?>
    </div>
    <?php endif; ?>
</section>