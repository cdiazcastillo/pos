<?php
$activePage = $activePage ?? '';
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

    <a href="totals.php" class="nav-item <?php echo $activePage === 'totals' ? 'active' : ''; ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 20V10"/>
            <path d="M10 20V4"/>
            <path d="M16 20v-7"/>
            <path d="M22 20V8"/>
        </svg>
        <span>Totales</span>
    </a>

    <a href="expenses.php" class="fab" aria-label="Ir a otros gastos">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 5v14"/>
            <path d="M5 12h14"/>
        </svg>
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

    <a href="index.php" class="nav-item <?php echo $activePage === 'pos' ? 'active' : ''; ?>">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="2.5" y="5" width="19" height="14" rx="2"/>
            <path d="M2.5 10h19"/>
        </svg>
        <span>POS</span>
    </a>
</nav>
