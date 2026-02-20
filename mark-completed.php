<?php
/**
 * Use Case 11.6 Mark as Completed (Clients Only)
 * order status is "Delivered"
 *  update status to "Completed" 
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Mark Order as Completed';

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order ID', 'error');
}

$client_id = $_SESSION['user_id'] ?? '';

$stmt = $pdo->prepare("
    SELECT order_id, client_id, service_title, status, price, order_date
    FROM orders
    WHERE order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect_with_message('my-orders.php', 'Order not found', 'error');
}

if ((string)$order['client_id'] !== (string)$client_id) {
    redirect_with_message('my-orders.php', 'Access denied', 'error');
}

$status = $order['status'] ?? '';

if ($status !== 'Delivered') {
    redirect_with_message(
        'order-details.php?id=' . urlencode($order_id),
        'Only delivered orders can be marked as completed.',
        'warning'
    );
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['confirm_complete'])) {
        $errors['confirm'] = 'You must confirm before marking as completed.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'Completed'
                WHERE order_id = :oid
                  AND client_id = :cid
                  AND status = 'Delivered'
            ");
            $stmt->execute([
                'oid' => $order_id,
                'cid' => $client_id
            ]);

            if ($stmt->rowCount() !== 1) {
                redirect_with_message(
                    'order-details.php?id=' . urlencode($order_id),
                    'Could not mark this order as completed. Please refresh and try again.',
                    'error'
                );
            }

            redirect_with_message(
                'order-details.php?id=' . urlencode($order_id),
                'Order marked as completed successfully.',
                'success'
            );

        } catch (PDOException $e) {
            error_log("Mark completed error: " . $e->getMessage());
            $errors['general'] = 'Failed to complete order. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Mark as Completed</h1>
</div>

<div class="form-container mark-completed-container">

    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>

    <div class="message-warning">
        <strong>Are you sure?</strong><br>
        Are you sure you want to mark this order as completed? This action cannot be undone.
    </div>

    <div class="message-info">
        <strong>Order #:</strong> <?php echo htmlspecialchars($order['order_id']); ?><br>
        <strong>Service:</strong> <?php echo htmlspecialchars($order['service_title'] ?? ''); ?><br>
        <strong>Status:</strong> <?php echo htmlspecialchars($status); ?><br>
        <strong>Price:</strong> <?php echo format_price((float)$order['price'] * 1.05); ?><br>
        <strong>Placed on:</strong> <?php echo format_date($order['order_date']); ?>
    </div>

    <form method="POST" class="form">
        <div class="checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" name="confirm_complete" value="1">
                <span>I confirm that I want to mark this order as completed.</span>
            </label>
            <?php if (!empty($errors['confirm'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['confirm']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-success">Confirm Completion</button>
            <a class="btn-secondary" href="order-details.php?id=<?php echo urlencode($order_id); ?>">Back</a>
        </div>
    </form>

</div>

<?php require_once 'includes/footer.php'; ?>
