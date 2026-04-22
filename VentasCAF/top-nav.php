<?php
$activePage = $activePage ?? '';
$currentRole = (string)($_SESSION['user_role'] ?? '');
$isCashier = ($currentRole === 'cashier');
$secondaryHref = $isCashier ? 'products.php' : 'totals.php';
$secondaryLabel = $isCashier ? 'Productos' : 'Totales';
$secondaryActive = in_array($activePage, ['totals', 'products'], true) ? 'active' : '';
$rightHref = $isCashier ? 'expenses.php' : 'index.php';
$rightLabel = 'POS';
$rightActive = in_array($activePage, ['pos', 'expenses'], true) ? 'active' : '';
?>
<style>
body {
    padding-top: calc(var(--nav-height, 3.8rem) + env(safe-area-inset-top, 0)) !important;
}
</style>
<nav class="top-nav" aria-label="Navegación principal">
    <a href="admin.php" class="nav-item <?php echo $activePage === 'admin' ? 'active' : ''; ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 10.5 12 3l9 7.5"/>
            <path d="M5 9.5V21h14V9.5"/>
        </svg>
        <span>Menú</span>
    </a>

    <a href="<?php echo $secondaryHref; ?>" class="nav-item <?php echo $secondaryActive; ?>">
        <?php if ($isCashier): ?>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/>
                <path d="M3 7.5V16.5L12 21l9-4.5V7.5"/>
            </svg>
        <?php else: ?>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 20V10"/>
                <path d="M10 20V4"/>
                <path d="M16 20v-7"/>
                <path d="M22 20V8"/>
            </svg>
        <?php endif; ?>
        <span><?php echo $secondaryLabel; ?></span>
    </a>

    <a href="index.php" class="fab" aria-label="Ir a Ventas POS">
        <span class="fab-label" aria-hidden="true">V</span>
    </a>

    <a href="sales_history.php" class="nav-item <?php echo $activePage === 'history' ? 'active' : ''; ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="3" width="16" height="18" rx="2"/>
            <path d="M8 7h8"/>
            <path d="M8 11h8"/>
            <path d="M8 15h5"/>
        </svg>
        <span>Historial</span>
    </a>

    <a href="<?php echo $rightHref; ?>" class="nav-item <?php echo $rightActive; ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="6" width="18" height="12" rx="2"/>
            <path d="M3 10h18"/>
            <path d="M7 14h4"/>
        </svg>
        <span><?php echo $rightLabel; ?></span>
    </a>
</nav>
