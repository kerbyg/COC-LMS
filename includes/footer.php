<?php
/**
 * ============================================================
 * CIT-LMS Footer Include
 * ============================================================
 * Contains: Closing tags, JavaScript files
 * ============================================================
 */
?>

    </div><!-- End .wrapper -->

    <!-- Global JavaScript -->
    <script src="<?= BASE_URL ?>/js/app.js"></script>

    <!-- Page-specific JavaScript (optional) -->
    <?php if (isset($pageJS) && !empty($pageJS)): ?>
    <script src="<?= BASE_URL ?>/js/<?= $pageJS ?>.js"></script>
    <?php endif; ?>
    
    <!-- Additional inline scripts from page (optional) -->
    <?php if (isset($inlineJS) && !empty($inlineJS)): ?>
    <script>
        <?= $inlineJS ?>
    </script>
    <?php endif; ?>
    
</body>
</html>