<?php
header('Content-Type: application/json');
session_start();
require_once 'config/db.php';

$response = [
    'success' => false,
    'message' => 'No fue posible cargar el monitor.'
];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Autenticación requerida.';
    echo json_encode($response);
    exit;
}

try {
    $db = Database::getInstance();

    $user = $db->query('SELECT id, role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        $response['message'] = 'Acceso denegado. Solo administradores.';
        echo json_encode($response);
        exit;
    }

    $salesToday = $db->query(
        "SELECT
            COUNT(*) AS total_sales,
            COALESCE(SUM(total_amount), 0) AS total_amount,
            COALESCE(AVG(total_amount), 0) AS avg_ticket
         FROM sales
         WHERE status = 'completed' AND DATE(sale_time) = CURDATE()"
    ) ?: ['total_sales' => 0, 'total_amount' => 0, 'avg_ticket' => 0];

    $returnsToday = $db->query(
        "SELECT COALESCE(SUM(e.amount), 0) AS total_returns
         FROM expenses e
         INNER JOIN sales s ON s.id = e.sale_id
         WHERE e.sale_id IS NOT NULL AND s.status = 'completed' AND DATE(e.expense_time) = CURDATE()"
    ) ?: ['total_returns' => 0];

    $otherExpensesToday = $db->query(
        "SELECT COALESCE(SUM(amount), 0) AS total_other_expenses
         FROM expenses
         WHERE sale_id IS NULL AND DATE(expense_time) = CURDATE()"
    ) ?: ['total_other_expenses' => 0];

    $salesLastHour = $db->query(
        "SELECT COUNT(*) AS total
         FROM sales
         WHERE status = 'completed' AND sale_time >= (NOW() - INTERVAL 1 HOUR)"
    ) ?: ['total' => 0];

    $openShifts = $db->query(
        "SELECT
            COUNT(*) AS total,
            MIN(start_time) AS earliest_start
         FROM shifts
         WHERE status = 'open'"
    ) ?: ['total' => 0, 'earliest_start' => null];

    $lowStockStats = $db->query(
        "SELECT
            SUM(CASE WHEN is_active = 1 AND stock_level <= 0 THEN 1 ELSE 0 END) AS out_stock,
            SUM(CASE WHEN is_active = 1 AND stock_level > 0 AND stock_level <= min_stock_warning THEN 1 ELSE 0 END) AS low_stock
         FROM products"
    ) ?: ['out_stock' => 0, 'low_stock' => 0];

    $stockAlerts = $db->query(
        "SELECT id, name, stock_level, min_stock_warning, is_active
         FROM products
         WHERE is_active = 1 AND stock_level <= min_stock_warning
         ORDER BY stock_level ASC, name ASC
         LIMIT 12",
        [],
        true
    ) ?: [];

    $topProductsToday = $db->query(
        "SELECT
            p.name,
            SUM(GREATEST(si.quantity - si.quantity_returned, 0)) AS sold_qty
         FROM sale_items si
         INNER JOIN sales s ON s.id = si.sale_id
         INNER JOIN products p ON p.id = si.product_id
         WHERE s.status = 'completed' AND DATE(s.sale_time) = CURDATE()
         GROUP BY p.id, p.name
         HAVING sold_qty > 0
         ORDER BY sold_qty DESC, p.name ASC
         LIMIT 7",
        [],
        true
    ) ?: [];

    $latestOtherExpenses = $db->query(
        "SELECT id, description, amount, expense_time
         FROM expenses
         WHERE sale_id IS NULL
         ORDER BY expense_time DESC
         LIMIT 10",
        [],
        true
    ) ?: [];

    $paymentMixRows = $db->query(
        "SELECT payment_method, COUNT(*) AS qty, COALESCE(SUM(total_amount), 0) AS amount
         FROM sales
         WHERE status = 'completed' AND DATE(sale_time) = CURDATE()
         GROUP BY payment_method",
        [],
        true
    ) ?: [];

    $dailySalesRows = $db->query(
        "SELECT DATE(sale_time) AS day_key, COALESCE(SUM(total_amount), 0) AS sales_amount
         FROM sales
         WHERE status = 'completed' AND DATE(sale_time) >= (CURDATE() - INTERVAL 6 DAY)
         GROUP BY DATE(sale_time)",
        [],
        true
    ) ?: [];

    $dailyReturnRows = $db->query(
        "SELECT DATE(e.expense_time) AS day_key, COALESCE(SUM(e.amount), 0) AS returns_amount
         FROM expenses e
         INNER JOIN sales s ON s.id = e.sale_id
         WHERE e.sale_id IS NOT NULL AND s.status = 'completed' AND DATE(e.expense_time) >= (CURDATE() - INTERVAL 6 DAY)
         GROUP BY DATE(e.expense_time)",
        [],
        true
    ) ?: [];

    $dailyOtherExpenseRows = $db->query(
        "SELECT DATE(expense_time) AS day_key, COALESCE(SUM(amount), 0) AS other_expenses_amount
         FROM expenses
         WHERE sale_id IS NULL AND DATE(expense_time) >= (CURDATE() - INTERVAL 6 DAY)
         GROUP BY DATE(expense_time)",
        [],
        true
    ) ?: [];

    $salesByDay = [];
    foreach ($dailySalesRows as $row) {
        $salesByDay[$row['day_key']] = intval($row['sales_amount'] ?? 0);
    }

    $returnsByDay = [];
    foreach ($dailyReturnRows as $row) {
        $returnsByDay[$row['day_key']] = intval($row['returns_amount'] ?? 0);
    }

    $otherExpensesByDay = [];
    foreach ($dailyOtherExpenseRows as $row) {
        $otherExpensesByDay[$row['day_key']] = intval($row['other_expenses_amount'] ?? 0);
    }

    $activityTrend = [];
    for ($offset = 6; $offset >= 0; $offset--) {
        $day = date('Y-m-d', strtotime('-' . $offset . ' day'));
        $salesAmount = $salesByDay[$day] ?? 0;
        $returnsAmount = $returnsByDay[$day] ?? 0;
        $otherAmount = $otherExpensesByDay[$day] ?? 0;

        $activityTrend[] = [
            'day' => $day,
            'sales' => $salesAmount,
            'returns' => $returnsAmount,
            'other_expenses' => $otherAmount,
            'net' => $salesAmount - $returnsAmount - $otherAmount,
        ];
    }

    $salesAmountToday = intval($salesToday['total_amount'] ?? 0);
    $returnsAmountToday = intval($returnsToday['total_returns'] ?? 0);
    $otherExpensesAmountToday = intval($otherExpensesToday['total_other_expenses'] ?? 0);

    $paymentMix = [
        'cash' => ['qty' => 0, 'amount' => 0],
        'transfer' => ['qty' => 0, 'amount' => 0],
    ];

    foreach ($paymentMixRows as $row) {
        $method = $row['payment_method'] ?? '';
        if (!isset($paymentMix[$method])) {
            continue;
        }
        $paymentMix[$method] = [
            'qty' => intval($row['qty'] ?? 0),
            'amount' => intval($row['amount'] ?? 0),
        ];
    }

    $response['success'] = true;
    $response['message'] = 'Monitor cargado correctamente.';
    $response['data'] = [
        'kpis' => [
            'total_sales_today' => intval($salesToday['total_sales'] ?? 0),
            'gross_sales_today' => $salesAmountToday,
            'returns_today' => $returnsAmountToday,
            'other_expenses_today' => $otherExpensesAmountToday,
            'net_income_today' => $salesAmountToday - $returnsAmountToday - $otherExpensesAmountToday,
            'avg_ticket_today' => round(floatval($salesToday['avg_ticket'] ?? 0)),
            'sales_last_hour' => intval($salesLastHour['total'] ?? 0),
            'open_shifts' => intval($openShifts['total'] ?? 0),
            'open_shift_earliest_start' => $openShifts['earliest_start'] ?? null,
            'low_stock_count' => intval($lowStockStats['low_stock'] ?? 0),
            'out_stock_count' => intval($lowStockStats['out_stock'] ?? 0),
        ],
        'payment_mix_today' => $paymentMix,
        'stock_alerts' => $stockAlerts,
        'top_products_today' => $topProductsToday,
        'latest_other_expenses' => $latestOtherExpenses,
        'activity_trend_7d' => $activityTrend,
        'updated_at' => date('Y-m-d H:i:s')
    ];

} catch (Throwable $error) {
    error_log('Monitor summary API error: ' . $error->getMessage());
    $response['message'] = 'Error interno al construir el monitor.';
}

echo json_encode($response);
