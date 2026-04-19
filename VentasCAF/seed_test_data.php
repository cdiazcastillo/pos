<?php
require_once 'config/db.php';

$db = Database::getInstance();

try {
    //
    // Shift 1
    //
    echo "Creating Shift 1...\n";
    $db->execute("INSERT INTO shifts (user_id, initial_cash) VALUES (?, ?)", [1, 10000]);
    $shift1_id = $db->lastInsertId();
    echo "Shift 1 created with ID: $shift1_id\n";

    // Sales for Shift 1
    echo "Adding sales for Shift 1...\n";
    $db->execute("INSERT INTO sales (shift_id, total_amount, payment_method) VALUES (?, ?, ?)", [$shift1_id, 5000, 'cash']);
    $db->execute("INSERT INTO sales (shift_id, total_amount, payment_method) VALUES (?, ?, ?)", [$shift1_id, 3000, 'transfer']);
    echo "Sales added for Shift 1.\n";

    // Expenses for Shift 1
    echo "Adding expenses for Shift 1...\n";
    $db->execute("INSERT INTO expenses (shift_id, description, amount) VALUES (?, ?, ?)", [$shift1_id, 'Cleaning supplies', 500]);
    echo "Expenses added for Shift 1.\n";

    // Close Shift 1
    echo "Closing Shift 1...\n";
    $db->execute("UPDATE shifts SET end_time = CURRENT_TIMESTAMP, final_cash = ?, status = 'closed' WHERE id = ?", [14500, $shift1_id]);
    echo "Shift 1 closed.\n";


    //
    // Shift 2
    //
    echo "Creating Shift 2...\n";
    $db->execute("INSERT INTO shifts (user_id, initial_cash) VALUES (?, ?)", [1, 12000]);
    $shift2_id = $db->lastInsertId();
    echo "Shift 2 created with ID: $shift2_id\n";

    // Sales for Shift 2
    echo "Adding sales for Shift 2...\n";
    $db->execute("INSERT INTO sales (shift_id, total_amount, payment_method) VALUES (?, ?, ?)", [$shift2_id, 8000, 'cash']);
    $db->execute("INSERT INTO sales (shift_id, total_amount, payment_method) VALUES (?, ?, ?)", [$shift2_id, 2500, 'cash']);
    echo "Sales added for Shift 2.\n";

    // Close Shift 2
    echo "Closing Shift 2...\n";
    $db->execute("UPDATE shifts SET end_time = CURRENT_TIMESTAMP, final_cash = ?, status = 'closed' WHERE id = ?", [22500, $shift2_id]);
    echo "Shift 2 closed.\n";

    echo "Test data created successfully!\n";

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}
