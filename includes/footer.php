<?php /* Variabel opsional: $publik = true untuk beranda (tanpa pembuka <main>). */ ?>
<?php if (empty($publik)): ?></main><?php endif; ?>

<footer class="site-foot">
    <?php require __DIR__ . '/footer_info.php'; ?>
    <div class="copy"><div class="container"><?= copyright() ?></div></div>
</footer>

<div class="toasts noprint" id="toasts" aria-live="polite"></div>
<?= flash_js() ?>
<button type="button" class="to-top noprint" id="toTop" aria-label="Kembali ke atas" hidden>&#8593;</button>
</body>
</html>
