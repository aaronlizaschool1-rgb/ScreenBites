    </main>

    <footer class="footer">
      ScreenBites &middot; Snacks And Chill &middot; <?= date('Y') ?>
    </footer>
  </div>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $src): ?>
<script src="<?= BASE_URL . htmlspecialchars($src) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html> 