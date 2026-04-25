<?php
header('Content-Type: application/json');
require_once 'includes/auth.php';
auth_require_api_role(['admin']);

$response = ['success' => false, 'message' => 'No se pudo generar el reporte diario.'];

try {
    $db = Database::getInstance();

    $summary = $db->query(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS gross_sales,
            COALESCE(SUM(CASE WHEN status = 'voided' THEN total_amount ELSE 0 END), 0) AS voided_sales,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS transactions_count
         FROM sales
         WHERE DATE(sale_time) = CURDATE()"
    ) ?: ['gross_sales' => 0, 'voided_sales' => 0, 'transactions_count' => 0];

    $operationalExpenses = $db->query(
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE DATE(expense_time) = CURDATE()
           AND sale_id IS NULL"
    ) ?: ['total' => 0];

    $returns = $db->query(
        "SELECT COALESCE(SUM(e.amount), 0) AS total
         FROM expenses e
         JOIN sales s ON s.id = e.sale_id
         WHERE DATE(e.expense_time) = CURDATE()"
    ) ?: ['total' => 0];

    $grossSales = floatval($summary['gross_sales'] ?? 0);
    $voidedSales = floatval($summary['voided_sales'] ?? 0);
    $returnsTotal = floatval($returns['total'] ?? 0);
    $operationalTotal = floatval($operationalExpenses['total'] ?? 0);
    $netIncome = $grossSales - $returnsTotal - $operationalTotal;

    $salesByShift = $db->query(
        "SELECT
            s.id AS shift_id,
            u.username AS seller,
            COUNT(sa.id) AS sales_count,
            COALESCE(SUM(sa.total_amount), 0) AS sales_amount
         FROM shifts s
         LEFT JOIN users u ON u.id = s.user_id
         LEFT JOIN sales sa ON sa.shift_id = s.id AND sa.status = 'completed' AND DATE(sa.sale_time) = CURDATE()
         WHERE DATE(s.start_time) = CURDATE() OR s.status = 'open'
         GROUP BY s.id, u.username
         ORDER BY s.id ASC",
        [],
        true
    ) ?: [];

    $generalSales = $db->query(
        "SELECT
            sa.id,
            sa.sale_time,
            sa.total_amount,
            u.username AS seller
         FROM sales sa
         JOIN shifts s ON s.id = sa.shift_id
         JOIN users u ON u.id = s.user_id
         WHERE sa.status = 'completed' AND DATE(sa.sale_time) = CURDATE()
         ORDER BY sa.sale_time ASC",
        [],
        true
    ) ?: [];

    $voidedSalesList = $db->query(
        "SELECT
            sa.id,
            sa.sale_time,
            sa.total_amount,
            u.username AS seller,
            (
                SELECT e.description
                FROM expenses e
                WHERE e.sale_id = sa.id
                ORDER BY e.id DESC
                LIMIT 1
            ) AS reason
         FROM sales sa
         JOIN shifts s ON s.id = sa.shift_id
         JOIN users u ON u.id = s.user_id
         WHERE sa.status = 'voided' AND DATE(sa.sale_time) = CURDATE()
         ORDER BY sa.sale_time ASC",
        [],
        true
    ) ?: [];

    $topProducts = $db->query(
        "SELECT
            p.name,
            COALESCE(SUM(GREATEST(si.quantity - si.quantity_returned, 0)), 0) AS qty,
            COALESCE(SUM(GREATEST(si.quantity - si.quantity_returned, 0) * si.price_per_unit), 0) AS total_amount
         FROM sale_items si
         JOIN sales sa ON sa.id = si.sale_id
         JOIN products p ON p.id = si.product_id
         WHERE sa.status = 'completed' AND DATE(sa.sale_time) = CURDATE()
         GROUP BY p.id, p.name
         HAVING qty > 0
         ORDER BY qty DESC, total_amount DESC
         LIMIT 8",
        [],
        true
    ) ?: [];

    $response['success'] = true;
    $response['data'] = [
        'date' => date('Y-m-d'),
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [
            'gross_sales' => $grossSales,
            'voided_sales' => $voidedSales,
            'returns' => $returnsTotal,
            'operational_expenses' => $operationalTotal,
            'net_income' => $netIncome,
            'transactions_count' => intval($summary['transactions_count'] ?? 0),
        ],
        'sales_by_shift' => array_map(static function ($row) {
            return [
                'shift_id' => intval($row['shift_id'] ?? 0),
                'seller' => (string)($row['seller'] ?? 'N/A'),
                'sales_count' => intval($row['sales_count'] ?? 0),
                'sales_amount' => floatval($row['sales_amount'] ?? 0),
            ];
        }, $salesByShift),
        'general_sales' => array_map(static function ($row) {
            return [
                'id' => intval($row['id'] ?? 0),
                'sale_time' => (string)($row['sale_time'] ?? ''),
                'amount' => floatval($row['total_amount'] ?? 0),
                'seller' => (string)($row['seller'] ?? 'N/A'),
            ];
        }, $generalSales),
        'voided_sales' => array_map(static function ($row) {
            return [
                'id' => intval($row['id'] ?? 0),
                'sale_time' => (string)($row['sale_time'] ?? ''),
                'amount' => floatval($row['total_amount'] ?? 0),
                'seller' => (string)($row['seller'] ?? 'N/A'),
                'reason' => (string)($row['reason'] ?: 'Anulación registrada'),
            ];
        }, $voidedSalesList),
        'top_products' => array_map(static function ($row) {
            return [
                'name' => (string)($row['name'] ?? 'Producto'),
                'qty' => intval($row['qty'] ?? 0),
                'total_amount' => floatval($row['total_amount'] ?? 0),
            ];
        }, $topProducts),
    ];
} catch (Throwable $error) {
    $response['message'] = 'Error al generar el reporte diario.';
}

echo json_encode($response);
