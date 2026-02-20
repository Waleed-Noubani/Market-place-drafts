<?php
session_start();            // convert pending to in progress (Freelancer)

require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order id', 'error');
}

$freelancer_id = $_SESSION['user_id'];

// Fetch + authorize
$stmt = $pdo->prepare("
    SELECT order_id, freelancer_id, service_title, status
    FROM orders
    WHERE order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || (string)$order['freelancer_id'] !== (string)$freelancer_id) {
    redirect_with_message('my-orders.php', 'Order not found or access denied', 'error');
}

if (($order['status'] ?? '') !== 'Pending') {
    redirect_with_message(
        "order-details.php?id={$order_id}",
        'Order must be pending to start working.',
        'warning'
    );
}

//  update 
try {
    $stmt = $pdo->prepare("
        UPDATE orders
        SET status = 'In Progress'
        WHERE order_id = :oid
          AND freelancer_id = :fid
          AND status = 'Pending'
    ");
    $stmt->execute(['oid' => $order_id, 'fid' => $freelancer_id]);
    
    redirect_with_message(
        "order-details.php?id={$order_id}",
        'Order status updated to In Progress.',
        'success'
    );
} catch (PDOException $e) {
    error_log('Start working error: ' . $e->getMessage());
    redirect_with_message(
        "order-details.php?id={$order_id}",
        'Failed to update order status.',
        'error'
    );
}