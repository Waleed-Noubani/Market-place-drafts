<?php // convert panding to canceled order
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Cancel Order';

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order id', 'error');
}

$client_id = $_SESSION['user_id'];

// Fetch order + authorize
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

if ($status !== 'Pending') {
    redirect_with_message(
        "order-details.php?id=" . urlencode($order_id),
        'Only pending orders can be cancelled.',
        'warning'
    );
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitize_input($_POST['cancellation_reason'] ?? '');

    if (!isset($_POST['confirm_cancel'])) {
        $errors['confirm'] = 'You must confirm cancellation';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'Cancelled',
                    cancellation_date = NOW(),
                    cancellation_reason = :reason
                WHERE order_id = :oid
                  AND client_id = :cid
                  AND status = 'Pending'
            ");

            $stmt->execute([
                'oid'    => $order_id,
                'cid'    => $client_id,
                'reason' => ($reason === '' ? null : $reason)
            ]);

            redirect_with_message(
                'my-orders.php',
                'Order cancelled successfully.',
                'success'
            );
        } catch (PDOException $e) {
            error_log("Cancel order error: " . $e->getMessage());
            $errors['general'] = 'Failed to cancel order. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Cancel Order</h1>
</div>

<div class="cancel-order-card">

    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>

    <div class="cancel-warning-box">
        <strong class="cancel-warning-title">⚠️ Warning</strong>
        <div class="cancel-warning-text">
            Are you sure you want to cancel this order? This action <strong>cannot be undone</strong>.
        </div>
    </div>

    <!-- Order Details -->
    <div class="cancel-order-details">
        <div class="cancel-order-id">
            Order #<?php echo htmlspecialchars($order['order_id']); ?>
        </div>

        <div class="cancel-order-service">
            <?php echo htmlspecialchars($order['service_title']); ?>
        </div>

        <div class="text-muted">
            Price: <?php echo format_price((float)$order['price'] * 1.05); ?> •
            Placed on <?php echo format_date($order['order_date']); ?>
        </div>
    </div>

    <form method="POST" class="form"> 
        <div class="form-group">
            <label class="form-label" for="cancellation_reason">
                Cancellation Reason (Optional)
            </label>
            <textarea
                class="form-textarea"
                id="cancellation_reason"
                name="cancellation_reason"
                rows="4"
                placeholder="Tell us why you're cancelling (optional)"
            ><?php echo isset($_POST['cancellation_reason']) ? htmlspecialchars($_POST['cancellation_reason']) : ''; ?></textarea>
        </div>

        <!-- Confirmation -->
        <div class="cancel-confirm-box">
            <label class="checkbox-label">
                <input type="checkbox" name="confirm_cancel" <?php echo isset($_POST['confirm_cancel']) ? 'checked' : ''; ?>>
                <span>
                    I understand that cancelling this order cannot be undone and a refund will be processed.
                </span>
            </label>

            <?php if (isset($errors['confirm'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['confirm']); ?></span>
            <?php endif; ?>
        </div>

        <div class="cancel-actions">
            <button type="submit" class="btn-danger cancel-btn-wide">Confirm Cancellation </button>
            <a href="order-details.php?id=<?php echo urlencode($order_id); ?>"
               class="btn-secondary cancel-btn-wide">
                Go Back
            </a>
        </div>
    </form>

</div>

<?php require_once 'includes/footer.php'; ?>
