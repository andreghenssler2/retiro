</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= THEME_JS ?>admin/sidebar.js"></script>

<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= htmlspecialchars((string) $script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>

<?php foreach (($pageInlineScripts ?? []) as $script): ?>
    <script><?= $script ?></script>
<?php endforeach; ?>

</body>
</html>
