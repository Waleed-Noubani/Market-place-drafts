<?php
/**
 * Use Case 11.6: Mark as Completed (Clients Only)
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Complete Order';

$order_id = sanitize_input($_GET['id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order id', 'error');
}

$user_id = $_SESSION['user_id'];

// Fetch order + authorization
$stmt = $pdo->prepare("
    SELECT order_id, client_id, service_title, status, order_date
    FROM orders
    WHERE order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect_with_message('my-orders.php', 'Order not found', 'error');
}

if ((string)$order['client_id'] !== (string)$user_id) {
    redirect_with_message('my-orders.php', 'Access denied', 'error');
}

if (($order['status'] ?? '') !== 'Delivered') {
    redirect_with_message(
        'order-details.php?id=' . urlencode($order_id),
        'Order must be delivered before completion.',
        'warning'
    );
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['confirm_complete'])) {
        $errors['confirm'] = 'You must confirm completion';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'Completed',
                    completion_date = NOW()
                WHERE order_id = :oid
                  AND client_id = :cid
                  AND status = 'Delivered'
                LIMIT 1
            ");
            $stmt->execute([
                'oid' => $order_id,
                'cid' => $user_id
            ]);

            redirect_with_message(
                'order-details.php?id=' . urlencode($order_id),
                'Order marked as completed successfully.',
                'success'
            );
        } catch (PDOException $e) {
            error_log('Complete order error: ' . $e->getMessage());
            $errors['general'] = 'Failed to complete order. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Complete Order</h1>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
<?php endif; ?>

<div class="order-complete-card" style="max-width: 850px; margin: 0 auto; background:#fff; border:1px solid #DEE2E6; border-radius:8px; padding:25px;">

    <div style="background:#D1ECF1; border:1px solid #17A2B8; border-left:4px solid #17A2B8; padding:15px 20px; border-radius:8px; margin-bottom:20px;">
        <strong style="color:#0C5460;">Confirmation Required</strong>
        <div style="color:#0C5460; margin-top:6px;">
            Are you sure you want to mark this order as completed?  
            This action <strong>cannot be undone</strong>.
        </div>
    </div>

    <div style="margin-bottom:20px;">
        <div style="font-size:18px; font-weight:700; color:#007BFF;">
            Order #<?php echo htmlspecialchars($order['order_id']); ?>
        </div>
        <div style="font-size:16px; font-weight:600; margin-top:4px;">
            <?php echo htmlspecialchars($order['service_title']); ?>
        </div>
        <div class="text-muted" style="margin-top:6px;">
            Placed on <?php echo !empty($order['order_date']) ? format_date($order['order_date']) : '—'; ?>
        </div>
    </div>

    <form method="POST">
        <div style="background:#F8F9FA; border:1px solid #DEE2E6; border-radius:8px; padding:15px;">
            <label style="display:flex; gap:12px; align-items:flex-start; cursor:pointer;">
                <input type="checkbox" name="confirm_complete" style="margin-top:4px; width:18px; height:18px;">
                <span>
                    I confirm that the delivered work is complete and satisfactory.
                </span>
            </label>
            <?php if (isset($errors['confirm'])): ?>
                <div class="form-error" style="margin-top:8px;"><?php echo htmlspecialchars($errors['confirm']); ?></div>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:15px; justify-content:center; margin-top:25px; flex-wrap:wrap;">
            <button type="submit" class="btn-success" style="min-width:220px;">
                Confirm Completion
            </button>
            <a href="order-details.php?id=<?php echo urlencode($order_id); ?>" 
               class="btn-secondary" 
               style="min-width:220px; text-align:center; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                Back
            </a>
        </div>
    </form>

</div>

<?php require_once 'includes/footer.php'; ?>
