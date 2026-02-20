<?php
/**
 * Use Case 11.8: Handle Revision Requests (Freelancer Only)
 * Actions: accept / reject
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();

$order_id = sanitize_input($_POST['order_id'] ?? '');
$action   = sanitize_input($_POST['action'] ?? '');
$response = sanitize_input($_POST['freelancer_response'] ?? '');

if ($order_id === '' || !in_array($action, ['accept', 'reject'], true)) {
    redirect_with_message('my-orders.php', 'Invalid request', 'error');
}

$freelancer_id = $_SESSION['user_id'];

// Ensure order belongs to this freelancer + current status must allow revision handling
$stmt = $pdo->prepare("
    SELECT order_id, freelancer_id, status
    FROM orders
    WHERE order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect_with_message('my-orders.php', 'Order not found', 'error');
}
if ((string)$order['freelancer_id'] !== (string)$freelancer_id) {
    redirect_with_message('my-orders.php', 'Access denied', 'error');
}

// Validate response text
if (empty($response)) {
    redirect_with_message('order-details.php?id=' . urlencode($order_id), 'Response is required', 'error');
}
if (strlen($response) < 5 || strlen($response) > 1000) {
    redirect_with_message('order-details.php?id=' . urlencode($order_id), 'Response must be 5-1000 characters', 'error');
}

try {
    $pdo->beginTransaction();

    // Get latest NEW revision request for this order
    $stmt = $pdo->prepare("
        SELECT revision_id
        FROM revision_requests
        WHERE order_id = :oid AND request_status = 'New'
        ORDER BY request_date DESC, revision_id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute(['oid' => $order_id]);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rev) {
        $pdo->rollBack();
        redirect_with_message('order-details.php?id=' . urlencode($order_id), 'No pending revision requests found', 'warning');
    }

    $new_status = ($action === 'accept') ? 'Accepted' : 'Rejected';

    // Update revision request
    $stmt = $pdo->prepare("
        UPDATE revision_requests
        SET request_status = :st,
            freelancer_response = :resp,
            response_date = NOW()
        WHERE revision_id = :rid
          AND order_id = :oid
          AND request_status = 'New'
        LIMIT 1
    ");
    $stmt->execute([
        'st'   => $new_status,
        'resp' => $response,
        'rid'  => $rev['revision_id'],
        'oid'  => $order_id
    ]);

    // Update order status based on decision
    if ($action === 'accept') {
        // back to working
        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'In Progress'
            WHERE order_id = :oid AND freelancer_id = :fid
            LIMIT 1
        ");
        $stmt->execute(['oid' => $order_id, 'fid' => $freelancer_id]);
    } else {
        // keep it delivered
        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = 'Delivered'
            WHERE order_id = :oid AND freelancer_id = :fid
            LIMIT 1
        ");
        $stmt->execute(['oid' => $order_id, 'fid' => $freelancer_id]);
    }

    $pdo->commit();

    redirect_with_message(
        'order-details.php?id=' . urlencode($order_id),
        'Revision request ' . ($action === 'accept' ? 'accepted' : 'rejected') . ' successfully.',
        'success'
    );

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Handle revision error: ' . $e->getMessage());
    redirect_with_message('order-details.php?id=' . urlencode($order_id), 'Failed to process request. Try again.', 'error');
}
