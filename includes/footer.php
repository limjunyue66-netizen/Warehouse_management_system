<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Common Footer
 */
?>
        </main>

        <footer class="app-footer">
            <div>
                &copy; <?php echo date('Y'); ?> <strong><?php echo e(APP_NAME); ?></strong>. All rights reserved.
            </div>
            <div>
                Built for High Reliability & Precision Inventory Control
            </div>
        </footer>
    </div>
</div>

<script src="<?php echo e(url('assets/js/app.js')); ?>"></script>
</body>
</html>
