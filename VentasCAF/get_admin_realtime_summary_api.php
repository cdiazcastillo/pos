<?php
header('Content-Type: application/json');
require_once 'includes/auth.php';
auth_require_api_role(['admin']);

$response = [
    'success' => false,
    'message' => 'No se pudo obtener el resumen en tiempo real.'
];

try {
    $db = Database::getInstance();

    $salesSummary = $db->query(
        "SELECT COALESCE(SUM(total_amount), 0) AS gross_sales
         FROM sales
         WHERE status = 'completed'"
    ) ?: ['gross_sales' => 0];

    $returnsSummary = $db->query(
        "SELECT COALESCE(SUM(e.amount), 0) AS total_returns
         FROM expenses e
         INNER JOIN sales s ON s.id = e.sale_id
         WHERE e.sale_id IS NOT NULL AND s.status = 'completed'"
    ) ?: ['total_returns' => 0];

    $otherExpensesSummary = $db->query(
        "SELECT COALESCE(SUM(amount), 0) AS total_other_expenses
         FROM expenses
         WHERE sale_id IS NULL"
    ) ?: ['total_other_expenses' => 0];

    $otherExpensesList = $db->query(
        "SELECT id, description, amount, expense_time
         FROM expenses
         WHERE sale_id IS NULL
         ORDER BY expense_time DESC
         LIMIT 20",
        [],
        true
    ) ?: [];

    $productSales = $db->query(
        "SELECT
            p.id,
            p.name,
            COALESCE(SUM(
                CASE
                    WHEN s.status = 'completed' THEN GREATEST(si.quantity - si.quantity_returned, 0)
                    ELSE 0
                END
            ), 0) AS sold_qty
         FROM products p
         LEFT JOIN sale_items si ON si.product_id = p.id
         LEFT JOIN sales s ON s.id = si.sale_id
         GROUP BY p.id, p.name",
        [],
        true
    ) ?: [];

    $soldProducts = array_values(array_filter($productSales, static function ($row) {
        return intval($row['sold_qty'] ?? 0) > 0;
    }));

    usort($soldProducts, static function ($a, $b) {
        return intval($b['sold_qty']) <=> intval($a['sold_qty']);
    });

    $topProducts = array_slice($soldProducts, 0, 6);

    $leastProducts = $soldProducts;
    usort($leastProducts, static function ($a, $b) {
        return intval($a['sold_qty']) <=> intval($b['sold_qty']);
    });
    $leastProducts = array_slice($leastProducts, 0, 6);

    $grossSales = intval($salesSummary['gross_sales'] ?? 0);
    $totalReturns = intval($returnsSummary['total_returns'] ?? 0);
    $totalOtherExpenses = intval($otherExpensesSummary['total_other_expenses'] ?? 0);

    $netSalesBeforeExpenses = $grossSales - $totalReturns;
    $netIncomeAfterExpenses = $netSalesBeforeExpenses - $totalOtherExpenses;

    $response['success'] = true;
    $response['message'] = 'Resumen en tiempo real cargado.';
    $response['data'] = [
        'income' => [
            'gross_sales' => $grossSales,
            'returns' => $totalReturns,
            'net_sales_before_expenses' => $netSalesBeforeExpenses,
            'other_expenses' => $totalOtherExpenses,
            'net_income_after_expenses' => $netIncomeAfterExpenses,
        ],
        'other_expenses_notes' => $otherExpensesList,
        'top_products' => $topProducts,
        'least_products' => $leastProducts,
        'updated_at' => date('Y-m-d H:i:s')
    ];
} catch (Throwable $error) {
    error_log('Realtime admin summary error: ' . $error->getMessage());
    $response['message'] = 'Ocurrió un error del servidor.';
}

echo json_encode($response);
