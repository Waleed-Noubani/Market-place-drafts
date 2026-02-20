<?php
/**
 * Use Case 11.7 Request Revision (Clients Only)
 * Order status = "Delivered"
 * Insert revision request with status = "New"
 * Update order status to "Revision Requested"
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Request Revision';

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order ID', 'error');
}

$client_id = $_SESSION['user_id'] ?? '';

$stmt = $pdo->prepare("
    SELECT order_id, client_id, freelancer_id, service_title, status, revisions_included
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
        'Revisions can only be requested when the order is Delivered.',
        'warning'
    );
}

$allowed = (int)($order['revisions_included'] ?? 0);
$unlimited = ($allowed === 999);

// 2) Count used revisions 
$stmt = $pdo->prepare("SELECT COUNT(*) FROM revision_requests WHERE order_id = :oid");
$stmt->execute(['oid' => $order_id]);
$used = (int)$stmt->fetchColumn();

$remaining = $unlimited ? 999 : max(0, $allowed - $used);

if (!$unlimited && $remaining <= 0) {
    redirect_with_message(
        'order-details.php?id=' . urlencode($order_id),
        "You have used all {$allowed} revision requests for this order.",
        'warning'
    );
}

$errors = [];
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = sanitize_input($_POST['revision_description'] ?? '');

    $len = mb_strlen($description);
    if ( $len < 50 || $len > 500) {
        $errors['description'] = 'Revision description is required (50–500 characters).';
    }

    if (!isset($_POST['confirm_revision'])) {
        $errors['confirm'] = 'You must confirm that this request counts toward your revision limit.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM revision_requests WHERE order_id = :oid");
        $stmt->execute(['oid' => $order_id]);
        $used_now = (int)$stmt->fetchColumn();

        $remaining_now = $unlimited ? 999 : max(0, $allowed - $used_now);

        if (!$unlimited && $remaining_now <= 0) {
            $errors['general'] = "You have used all {$allowed} revision requests for this order.";
        }
    }


// Insert revision request (status = New)
// Update order status --> Revision Requested
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO revision_requests (
                    order_id,
                    revision_notes,
                    request_status,
                    request_date
                ) VALUES (
                    :order_id,
                    :notes,
                    'New',
                    NOW()
                )
            ");

            $stmt->execute([
                'order_id' => $order_id,
                'notes'    => $description
            ]);


            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'Revision Requested'
                WHERE order_id = :oid
                  AND client_id = :cid
                  AND status = 'Delivered'
            ");
            $stmt->execute([
                'oid' => $order_id,
                'cid' => $client_id,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception('Order status update failed.');
            }

            $pdo->commit();

            redirect_with_message(
                'order-details.php?id=' . urlencode($order_id),
                'Revision request submitted successfully.',
                'success'
            );

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Request revision error: ' . $e->getMessage());
            $errors['general'] = 'Failed to submit revision request. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Request Revision</h1>
</div>

<div class="form-container request-revision-container">

    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>

    <div class="message-warning">
        <strong>Important Notice</strong><br>
        <?php if ($unlimited): ?>
            This service includes <strong>unlimited</strong> revision requests.
        <?php else: ?>
            This service includes <strong><?php echo htmlspecialchars((string)$allowed); ?></strong> revision requests.
        <?php endif; ?>
        <ul>
            <li>All requests count (accepted + rejected).</li>
            <li>Freelancer may reject if request is outside original scope.</li>
            <li>Be clear and specific.</li>
        </ul>
        <?php if (!$unlimited): ?>
            <div><strong>Revisions Used:</strong> <?php echo $used; ?>/<?php echo $allowed; ?></div>
            <div><strong>Revisions Remaining:</strong> <?php echo $remaining; ?></div>
        <?php else: ?>
            <div><strong>Revisions Used:</strong> <?php echo $used; ?></div>
        <?php endif; ?>
    </div>

    <form method="POST" class="form">

        <div class="form-group">
            <label class="form-label" for="revision_description">Revision Description (Required)</label>
            <span class="form-hint">50–500 characters. Explain exactly what needs to change.</span>

            <textarea
                class="form-textarea <?php echo isset($errors['description']) ? 'input-error' : ''; ?>"
                id="revision_description"
                name="revision_description"
                rows="6"
                placeholder="Be specific: what element to modify, how to modify it, and why."
            ><?php echo htmlspecialchars($description); ?></textarea>

            <?php if (!empty($errors['description'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['description']); ?></span>
            <?php endif; ?>
        </div>

        <div class="checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" name="confirm_revision" value="1">
                <span>I understand this request will count toward my revision limit.</span>
            </label>
            <?php if (!empty($errors['confirm'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['confirm']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Submit Request</button>
            <a class="btn-secondary" href="order-details.php?id=<?php echo urlencode($order_id); ?>">Cancel</a>
        </div>

    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
