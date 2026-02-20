<?php
/**
 * Use Case 11.8 Respond to Revision Request (Freelancer Only)
 * - order.status = 'Revision Requested'
 * - revision_requests.request_status = 'New'
 * خيارين:
 * 1) Accept & Upload Revised Work:
 *    - رسالة (50-500)
 *    - ملفات (1-5) max 50MB each
 *    - حفظ الملفات: /uploads/orders/[order_id]/revisions/
 *    - insert file_attachments file_type='deliverable'
 *    - update revision request => Accepted + response_date + freelancer_response
 *    - update order => Delivered + actual_delivery_date NOW()
 *
 * 2) Reject Request:
 *    - سبب رفض (50-500)
 *    - update revision request => Rejected + response_date + freelancer_response
 *    - update order => Delivered
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();

$page_title = 'Respond to Revision Request';

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order id', 'error');
}

$freelancer_id = $_SESSION['user_id'];

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

if (($order['status'] ?? '') !== 'Revision Requested') {
    redirect_with_message("order-details.php?id={$order_id}", 'This order is not in Revision Requested status.', 'warning');
}

// Get the latest NEW revision request
$stmt = $pdo->prepare("
    SELECT revision_id, revision_notes, request_status, request_date
    FROM revision_requests
    WHERE order_id = :oid AND request_status = 'New'
    ORDER BY request_date DESC
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$revision = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$revision) {
    redirect_with_message("order-details.php?id={$order_id}", 'No new revision request found for this order.', 'warning');
}

$errors = [];
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');

    if ($action === 'accept_upload') {       ///////////////////////////////////////////////// ACCEPT & UPLOAD
        $delivery_message = sanitize_input($_POST['delivery_message'] ?? '');

        $len = strlen($delivery_message);
        if ($len < 50 || $len > 500) {
            $errors['delivery_message'] = 'Delivery message must be between 50 and 500 characters.';
        }

        $file_validation = validate_requirement_files2( $_FILES['revision_files'] ?? [], 5, 50,null );

        if (!$file_validation['success']) {
            $errors['revision_files'] = $file_validation['error'];
        } elseif (empty($file_validation['files'])) {
            $errors['revision_files'] = 'At least one revised file is required.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $dir = "uploads/orders/{$order_id}/revisions/";
                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0755, true)) {
                        throw new Exception('Failed to create revisions directory.');
                    }
                }

                // Save each file + insert file_attachments
                foreach ($file_validation['files'] as $index => $file) {
                    $ext = pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION);
                    $ext = $ext ? strtolower($ext) : 'dat';

                    $safe_name = 'rev_' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT) . '_' . time() . '.' . $ext;
                    $file_path = $dir . $safe_name;

                    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                        throw new Exception('Failed to move uploaded file.');
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO file_attachments (
                            order_id,
                            file_path,
                            original_filename,
                            file_size,
                            mime_type,
                            file_type,
                            upload_timestamp
                        ) VALUES (
                            :oid,
                            :path,
                            :name,
                            :size,
                            :mime,
                            'deliverable',
                            NOW()
                        )
                    ");
                    $stmt->execute([
                        'oid'  => $order_id,
                        'path' => $file_path,
                        'name' => $file['original_name'],
                        'size' => $file['size'],
                        'mime' => $file['mime_type'],
                    ]);
                }

                // Update revision request 
                $stmt = $pdo->prepare("
                    UPDATE revision_requests
                    SET request_status = 'Accepted',
                        response_date = NOW(),
                        freelancer_response = :resp
                    WHERE revision_id = :rid
                      AND order_id = :oid
                      AND request_status = 'New'
                ");
                $stmt->execute([
                    'resp' => $delivery_message,
                    'rid'  => $revision['revision_id'],
                    'oid'  => $order_id
                ]);

                // Update order 
                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET status = 'Delivered',
                        actual_delivery_date = NOW()
                    WHERE order_id = :oid
                      AND freelancer_id = :fid
                      AND status = 'Revision Requested'
                ");
                $stmt->execute([
                    'oid' => $order_id,
                    'fid' => $freelancer_id
                ]);

                $pdo->commit();

                redirect_with_message(
                    "order-details.php?id={$order_id}",
                    'Revision accepted and revised work uploaded successfully.',
                    'success'
                );

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Respond revision accept error: " . $e->getMessage());
                $errors['general'] = 'Failed to upload revised work. Please try again.';
            }
        }
    }

    
    if ($action === 'reject') { /////////////////////////////////////////////// REJECT REQUEST
        $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
        $len = strlen($rejection_reason);

        if ($len < 50 || $len > 500) {
            $errors['rejection_reason'] = 'Rejection reason must be between 50 and 500 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update revision request 
                $stmt = $pdo->prepare("
                    UPDATE revision_requests
                    SET request_status = 'Rejected',
                        response_date = NOW(),
                        freelancer_response = :reason
                    WHERE revision_id = :rid
                      AND order_id = :oid
                      AND request_status = 'New'
                ");
                $stmt->execute([
                    'reason' => $rejection_reason,
                    'rid'    => $revision['revision_id'],
                    'oid'    => $order_id
                ]);

                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET status = 'Delivered'
                    WHERE order_id = :oid
                      AND freelancer_id = :fid
                      AND status = 'Revision Requested'
                ");
                $stmt->execute([
                    'oid' => $order_id,
                    'fid' => $freelancer_id
                ]);

                $pdo->commit();

                redirect_with_message(
                    "order-details.php?id={$order_id}",
                    'Revision request rejected. The client has been notified.',
                    'success'
                );

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Respond revision reject error: " . $e->getMessage());
                $errors['general'] = 'Failed to reject revision request. Please try again.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Respond to Revision Request</h1>
</div>

<div class="form-container">
    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>

    <div class="message-info">
        <strong>Order:</strong> <?php echo htmlspecialchars($order['service_title'] ?? ''); ?><br>
        <strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?><br>
        <strong>Revision ID:</strong> <?php echo htmlspecialchars($revision['revision_id']); ?><br>
        <strong>Client Notes:</strong><br>
        <?php echo nl2br(htmlspecialchars($revision['revision_notes'] ?? '')); ?>
    </div>

    <!-- ------------------- Accept & upload ---------------- -->
    <div class="order-block">
        <div class="order-block-label">Accept & Upload Revised Work</div>

        <form method="POST" enctype="multipart/form-data" class="form">
            <input type="hidden" name="action" value="accept_upload">

            <div class="form-group">
                <label class="form-label" for="delivery_message">Delivery Message (Required)</label>
                <span class="form-hint">50–500 characters</span>

                <textarea class="form-textarea" id="delivery_message" name="delivery_message" rows="5"><?php
                    echo isset($_POST['delivery_message']) ? htmlspecialchars($_POST['delivery_message']) : '';
                ?></textarea>

                <?php if (!empty($errors['delivery_message'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['delivery_message']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Revised Files (Required)</label>
                <span class="form-hint">1–5 files, max 50MB each (any format)</span>

                <input type="file" name="revision_files[]" class="form-input" multiple>

                <?php if (!empty($errors['revision_files'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['revision_files']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn-success" type="submit">Accept & Upload</button>
                <a class="btn-secondary" href="order-details.php?id=<?php echo urlencode($order_id); ?>">Back</a>
            </div>
        </form>
    </div>

<!-- ---------------------    Reject  ------------------>
    <div class="order-block">
        <div class="order-block-label">Reject Request</div>

        <form method="POST" class="form">
            <input type="hidden" name="action" value="reject">

            <div class="form-group">
                <label class="form-label" for="rejection_reason">Rejection Reason (Required)</label>
                <span class="form-hint">50–500 characters</span>

                <textarea class="form-textarea" id="rejection_reason" name="rejection_reason" rows="5"><?php
                    echo isset($_POST['rejection_reason']) ? htmlspecialchars($_POST['rejection_reason']) : '';
                ?></textarea>

                <?php if (!empty($errors['rejection_reason'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['rejection_reason']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button class="btn-danger" type="submit">Reject Request</button>
                <a class="btn-secondary" href="order-details.php?id=<?php echo urlencode($order_id); ?>">Back</a>
            </div>
        </form>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
