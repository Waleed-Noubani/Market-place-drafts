<?php
/**
 * Use Case 11.5 Upload Delivery (Freelancers Only)
 * Allowed statuses: "In Progress" or "Revision Requested"
 *  Delivery message: required 50–500 chars
 *  Delivery files: required 1–5 files, max 50MB each
 *  Save files to: uploads/orders/[order_id]/deliverables/
 *  Insert each file into file_attachments with file_type='deliverable'
 *  Update order status -> Delivered and set actual_delivery_date = NOW()
 */

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();

$page_title = 'Upload Delivery';

$order_id = sanitize_input($_GET['order_id'] ?? '');
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order ID', 'error');
}

$freelancer_id = $_SESSION['user_id'] ?? '';

$stmt = $pdo->prepare("
    SELECT order_id, freelancer_id, service_title, status
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

$status = $order['status'] ?? '';

if (!in_array($status, ['In Progress', 'Revision Requested'], true)) {
    redirect_with_message(
        'order-details.php?id=' . urlencode($order_id),
        'Upload delivery is only available for In Progress or Revision Requested orders.',
        'warning'
    );
}

$errors = [];
$delivery_message = '';
$optional_notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $delivery_message = sanitize_input($_POST['delivery_message'] ?? '');
    $optional_notes   = sanitize_input($_POST['optional_notes'] ?? '');

    $len = mb_strlen($delivery_message);
    if ( $len < 50 || $len > 500) {
        $errors['message'] = 'Delivery message is required (50–500 characters).';
    }

    // Validate files (required 1–5, max 50MB)
    $file_validation = validate_requirement_files2($_FILES['delivery_files'] ?? [], 5, 50);

    if (!$file_validation['success']) {
        $errors['files'] = $file_validation['error'];
    } elseif (count($file_validation['files']) < 1) {
        $errors['files'] = 'At least 1 delivery file is required.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $dir = "uploads/orders/{$order_id}/deliverables/";
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception('Failed to create deliverables directory.');
                }
            }

            // Save each file + insert attachment record
            foreach ($file_validation['files'] as $index => $file) {

                $original_name = $file['original_name'] ?? ('file_' . ($index + 1));
                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                $ext = $ext ? strtolower($ext) : 'dat';

                $safe_name = 'deliverable_' .str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT) . '_' .time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

                $file_path = $dir . $safe_name;

                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Failed to move uploaded file: ' . $original_name);
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
                    'name' => $original_name,
                    'size' => $file['size'],
                    'mime' => $file['mime_type'],
                ]);
            }

            // Update order status --> Delivered
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = 'Delivered',
                    actual_delivery_date = NOW()
                WHERE order_id = :oid
                  AND freelancer_id = :fid
                  AND status IN ('In Progress', 'Revision Requested')
            ");
            $stmt->execute([
                'oid' => $order_id,
                'fid' => $freelancer_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception('Order status update failed.');
            }

            $pdo->commit();

            redirect_with_message(
                'order-details.php?id=' . urlencode($order_id),
                'Delivery uploaded successfully.',
                'success'
            );

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Upload delivery error: ' . $e->getMessage());
            $errors['general'] = 'Failed to upload delivery. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Upload Delivery</h1>
</div>

<div class="form-container upload-delivery-container">

    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>

    <div class="message-info">
        <strong>Order:</strong> <?php echo htmlspecialchars($order['service_title'] ?? ''); ?><br>
        <strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?><br>
        <strong>Status:</strong> <?php echo htmlspecialchars($status); ?>
    </div>

    <div class="message-warning">
        <strong>Important:</strong> The client will be notified via order status update after you upload.
    </div>

    <form method="POST" class="form" enctype="multipart/form-data">

        <div class="form-group">
            <label class="form-label" for="delivery_message">Delivery Message (Required)</label>
            <span class="form-hint">50–500 characters.</span>

            <textarea
                class="form-textarea <?php echo isset($errors['message']) ? 'input-error' : ''; ?>"
                id="delivery_message"
                name="delivery_message"
                rows="5"
                placeholder="Describe what you delivered and how to use it..."
            ><?php echo htmlspecialchars($delivery_message); ?></textarea>

            <?php if (!empty($errors['message'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['message']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="delivery_files">Delivery Files (Required)</label>
            <span class="form-hint">Upload 1–5 files. Max 50MB each.</span>

            <input
                type="file"
                id="delivery_files"
                name="delivery_files[]"
                class="form-input <?php echo isset($errors['files']) ? 'input-error' : ''; ?>"
                multiple
            >

            <?php if (!empty($errors['files'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['files']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="optional_notes">Optional Notes</label>
            <span class="form-hint">Optional additional info (e.g., passwords, instructions).</span>

            <textarea
                class="form-textarea"
                id="optional_notes"
                name="optional_notes"
                rows="3"
                placeholder="Optional..."
            ><?php echo htmlspecialchars($optional_notes); ?></textarea>
        </div>

        <div class="form-actions">
            <button class="btn-success" type="submit">Upload Delivery</button>
            <a class="btn-secondary" href="order-details.php?id=<?php echo urlencode($order_id); ?>">Back</a>
        </div>

    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
