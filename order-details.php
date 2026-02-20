<?php
//  Display order details + timeline + attachments + revision history

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_login();

$page_title = 'Order Details';

$order_id = sanitize_input($_GET['id'] ?? ''); // from my-orders 
if ($order_id === '') {
    redirect_with_message('my-orders.php', 'Invalid order id', 'error');
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? ''; // Client / Freelancer 

// Fetch order + client/freelancer names 
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        CONCAT(c.first_name, ' ', c.last_name) AS client_name,
        CONCAT(f.first_name, ' ', f.last_name) AS freelancer_name,
        c.user_id AS client_user_id,
        f.user_id AS freelancer_user_id
    FROM orders o
    INNER JOIN users c ON o.client_id = c.user_id
    INNER JOIN users f ON o.freelancer_id = f.user_id
    WHERE o.order_id = :oid
    LIMIT 1
");
$stmt->execute(['oid' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect_with_message('my-orders.php', 'Order not found', 'error');
}

// بنتأكد إنه المستخدم الحالي هو إما العميل أو الفريلانسر تبع هالطلب
if ((string)$order['client_user_id'] !== (string)$user_id && (string)$order['freelancer_user_id'] !== (string)$user_id) {
    redirect_with_message('my-orders.php', 'Access denied', 'error');
}

$is_client     = is_client();
$is_freelancer = is_freelancer();
$price       = (float)($order['price'] ?? 0);
$service_fee = $price * 0.05;
$total       = $price + $service_fee;

$cancellation_date   = $order['cancellation_date'] ?? '';
$cancellation_reason = $order['cancellation_reason'] ?? '';

//  CSS classes 
$status = $order['status'] ?? 'Pending';
$badge_class = 'status-pending';
if ($status === 'Pending') $badge_class = 'status-pending';
elseif ($status === 'In Progress') $badge_class = 'status-in-progress';
elseif ($status === 'Delivered') $badge_class = 'status-completed';
elseif ($status === 'Revision Requested') $badge_class = 'status-pending';
elseif ($status === 'Completed') $badge_class = 'status-completed';
elseif ($status === 'Cancelled') $badge_class = 'status-cancelled';

// جيب كل الملفات المرتبطة بهالطلب
$stmt = $pdo->prepare("
    SELECT file_id, order_id, file_path, original_filename, file_size, mime_type, file_type, upload_timestamp
    FROM file_attachments
    WHERE order_id = :oid
    ORDER BY upload_timestamp DESC, file_id DESC
");
$stmt->execute(['oid' => $order_id]);
$attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($attachments)) { $attachments = []; }

// تصنيف الملفات حسب النوع
$requirement_files = [];
$deliverable_files = [];
$revision_files    = [];

foreach ($attachments as $a) {
    $t = $a['file_type'] ?? '';
    if ($t === 'requirement')       $requirement_files[] = $a;
    elseif ($t === 'deliverable')     $deliverable_files[] = $a;
    elseif ($t === 'revision')       $revision_files[] = $a;
}

// Fetch revisions   
$stmt = $pdo->prepare("
    SELECT revision_id, order_id, revision_notes, request_status, request_date, freelancer_response, response_date
    FROM revision_requests
    WHERE order_id = :oid
    ORDER BY request_date DESC
");
$stmt->execute(['oid' => $order_id]);
$revisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($revisions)) { $revisions = []; }

// Revision stats حساب إحصائيات التعديلات
$accepted = 0; $rejected = 0; $pending = 0;
foreach ($revisions as $r) {
    $st = $r['request_status'] ?? 'New';

    if ($st === 'Accepted')    
        $accepted++;
    elseif ($st === 'Rejected')   
        $rejected++;
    else    
        $pending++;
}

$included  = (int)($order['revisions_included'] ?? 0); // عدد التعديلات المسموحة
$used      = $accepted + $rejected;                   //عدد التعديلات المستخدمة
$remaining = max(0, $included - $used);  // المتبقية

//أول مرحلة: Order Placed → ايماً مكتملة (done = true)
$timeline = [];
$timeline[] = [
    'title' => 'Order Placed',
    'desc'  => 'Your order has been created.',
    'time'  => !empty($order['order_date']) ? format_datetime($order['order_date']) : '',
    'done'  => true,
];

$timeline[] = [
    'title' => 'In Progress',
    'desc'  => 'Freelancer is working on your order.',
    'time'  => '',
    'done'  => in_array($status, ['In Progress', 'Delivered', 'Revision Requested', 'Completed'], true),
];

$timeline[] = [
    'title' => 'Delivered',
    'desc'  => 'Work has been delivered.',
    'time'  => '',
    'done'  => in_array($status, ['Delivered', 'Revision Requested', 'Completed'], true),
];

$timeline[] = [
    'title' => 'Revision Requested',
    'desc'  => 'Client requested changes (if applicable).',
    'time'  => '',
    'done'  => ($status === 'Revision Requested') || ($pending > 0) || ($used > 0),
];

$timeline[] = [
    'title' => ($status === 'Cancelled' ? 'Cancelled' : 'Completed'),
    'desc'  => ($status === 'Cancelled' ? 'Order was cancelled.' : 'Order was completed.'),
    'time'  => '',
    'done'  => in_array($status, ['Completed', 'Cancelled'], true),
];

$has_new_revision = false;

if ($is_freelancer && $status === 'Revision Requested') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM revision_requests
        WHERE order_id = :oid
          AND request_status = 'New'
    ");
    $stmt->execute(['oid' => $order_id]);
    $has_new_revision = ((int)$stmt->fetchColumn() > 0);
}

require_once 'includes/header.php';
?>

<?php echo get_flash_message(); ?>

<div class="page-header">
    <h1>Order Details</h1>
</div>

<div class="order-details-layout">

    <!-- LEFT -->
    <div class="order-details-main">

        <!-- Summary Card -->
        <div class="order-summary-card">
            <div class="order-summary-top">
                <div>
                    <div class="order-summary-id">Order #<?php echo htmlspecialchars($order['order_id']); ?></div>
                    <div class="order-summary-title"><?php echo htmlspecialchars($order['service_title'] ?? ''); ?></div>
                    <div class="order-summary-meta">
                        <span class="badge-status <?php echo htmlspecialchars($badge_class); ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                        <span class="text-muted">Placed on <?php echo !empty($order['order_date']) ? format_date($order['order_date']) : '—'; ?></span>
                    </div>
                </div>

                <div class="order-summary-price">
                    <div class="order-price-label">Total</div>
                    <div class="order-price-amount"><?php echo format_price($total); ?></div>
                </div>
            </div>
            <!-- /////////////////////////////////////////////////////////////////// -->

            <div class="order-summary-grid">
                <div class="order-summary-item">
                    <div class="order-summary-label">Client</div>
                    <div class="order-summary-value"><?php echo htmlspecialchars($order['client_name']); ?></div>
                </div>
                <div class="order-summary-item">
                    <div class="order-summary-label">Freelancer</div>
                    <div class="order-summary-value"><?php echo htmlspecialchars($order['freelancer_name']); ?></div>
                </div>
                <div class="order-summary-item">
                    <div class="order-summary-label">Expected Delivery</div>
                    <div class="order-summary-value"> <?php echo format_date($order['expected_delivery'])?> </div>
                </div>
                <div class="order-summary-item">
                    <div class="order-summary-label">Delivery Time</div>
                    <div class="order-summary-value"><?php echo htmlspecialchars((string)($order['delivery_time'] ?? '—')); ?> days</div>
                </div>
                <div class="order-summary-item">
                    <div class="order-summary-label">Revisions</div>
                    <div class="order-summary-value"><?php echo htmlspecialchars((string)$included); ?> included
                        <span class="order-revision-remaining"> (Remaining: <?php echo htmlspecialchars((string)$remaining); ?>) </span>
                    </div>
                </div>
                <div class="order-summary-item">
                    <div class="order-summary-label">Service Fee</div>
                    <div class="order-summary-value"><?php echo format_price($service_fee); ?></div>
                </div>
            </div>
        </div>
        <!-- /////////////////////////////////////////////////////////////////////////////// -->

        <!-- Timeline -->
        <div class="order-timeline">
            <?php foreach ($timeline as $i => $t): ?>
                <div class="order-timeline-item <?php echo $i === count($timeline)-1 ? 'order-timeline-item-last' : ''; ?>">
                    <!-- التحكم في اللون الاخضر او الرمادي حسب الانجاز -->
                    <div class="order-timeline-icon <?php echo $t['done'] ? 'order-timeline-icon-done' : 'order-timeline-icon-pending'; ?>">
                        <?php echo $t['done'] ? '✓' : '•'; ?>  
                    </div>
                    <div class="order-timeline-connector"></div>

                    <div class="order-timeline-content">
                        <div class="order-timeline-title"><?php echo htmlspecialchars($t['title']); ?></div>
                        <?php if (!empty($t['time'])): ?>
                            <div class="order-timeline-time"><?php echo htmlspecialchars($t['time']); ?></div>
                        <?php endif; ?>
                        <div class="order-timeline-desc"><?php echo htmlspecialchars($t['desc']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Requirements -->
        <div class="order-section">
            <h2 class="heading-secondary">Service Requirements</h2>

            <div class="order-block">
                <div class="order-block-label">Requirements</div>
                <div class="order-block-text">
                    <?php echo nl2br(htmlspecialchars($order['requirements'] ?? '—')); ?>
                </div>
            </div>

            <?php if (!empty($order['special_instructions'])): ?>
                <div class="order-block">
                    <div class="order-block-label">Special Instructions</div>
                    <div class="order-block-text">
                        <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['preferred_deadline'])): ?>
                <div class="order-block">
                    <div class="order-block-label">Preferred Deadline</div>
                    <div class="order-block-text"><?php echo format_date($order['preferred_deadline']); ?></div>
                </div>
            <?php endif; ?>

            <div class="order-block">
                <div class="order-block-label">Requirement Files</div>

                <?php if (!empty($requirement_files)): ?>
                    <div class="order-files">
                        <?php foreach ($requirement_files as $f): ?>
                            <div class="order-file-item">
                                <div class="order-file-icon <?php echo htmlspecialchars(get_file_icon_class($f['mime_type'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(get_file_type_text($f['mime_type'] ?? '')); ?>
                                </div>
                                <div class="order-file-info">
                                    <a class="order-file-name" href="download.php?id=<?php echo urlencode($f['file_id']); ?>">
                                        <?php echo htmlspecialchars($f['original_filename'] ?? 'file'); ?>
                                    </a>
                                    <div class="order-file-meta"><?php echo htmlspecialchars(format_file_size((int)($f['file_size'] ?? 0))); ?></div>
                                </div>
                                <a class="order-file-action" href="download.php?id=<?php echo urlencode($f['file_id']); ?>">⬇</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="order-empty-note">No files uploaded.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delivery -->
        <div class="order-section">
            <h2 class="heading-secondary">Delivery</h2>

            <div class="order-block">
                <div class="order-block-label">Delivered Files</div>

                <?php if (!empty($deliverable_files)): ?>
                    <div class="order-files">
                        <?php foreach ($deliverable_files as $f): ?>
                            <div class="order-file-item">
                                <div class="order-file-icon <?php echo htmlspecialchars(get_file_icon_class($f['mime_type'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(get_file_type_text($f['mime_type'] ?? '')); ?>
                                </div>
                                <div class="order-file-info">
                                    <a class="order-file-name" href="download.php?id=<?php echo urlencode($f['file_id']); ?>">
                                        <?php echo htmlspecialchars($f['original_filename'] ?? 'file'); ?>
                                    </a>
                                    <div class="order-file-meta"><?php echo htmlspecialchars(format_file_size((int)($f['file_size'] ?? 0))); ?></div>
                                </div>
                                <a class="order-file-action" href="download.php?id=<?php echo urlencode($f['file_id']); ?>">⬇</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="order-empty-note">No delivery files yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Revision History -->
        <div class="revision-history-card">
            <h2 class="heading-secondary">Revision History</h2>

            <div class="revision-stats">
                <div class="revision-stat">
                    <div class="revision-stat-number"><?php echo htmlspecialchars((string)count($revisions)); ?></div>
                    <div class="revision-stat-label">Total</div>
                </div>
                <div class="revision-stat revision-stat-accepted">
                    <div class="revision-stat-number"><?php echo htmlspecialchars((string)$accepted); ?></div>
                    <div class="revision-stat-label">Accepted</div>
                </div>
                <div class="revision-stat revision-stat-rejected">
                    <div class="revision-stat-number"><?php echo htmlspecialchars((string)$rejected); ?></div>
                    <div class="revision-stat-label">Rejected</div>
                </div>
                <div class="revision-stat revision-stat-pending">
                    <div class="revision-stat-number"><?php echo htmlspecialchars((string)$pending); ?></div>
                    <div class="revision-stat-label">Pending</div>
                </div>
                <div class="revision-stat">
                    <div class="revision-stat-number"><?php echo htmlspecialchars((string)$remaining); ?>/<?php echo htmlspecialchars((string)$included); ?></div>
                    <div class="revision-stat-label">Remaining</div>
                </div>
            </div>
        <!-- ////////////////////////////////////////////////////////////////////////// -->
          
            <div class="table-wrapper">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Response</th>
                            <th>Response Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($revisions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No revision requests.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($revisions as $idx => $r): ?>
                                <?php
                                    $rs = $r['request_status'] ?? 'New';
                                    $rs_badge = 'status-pending';
                                    if ($rs === 'Accepted') $rs_badge = 'status-active';
                                    elseif ($rs === 'Rejected') $rs_badge = 'status-cancelled';
                                    else $rs_badge = 'status-pending';
                                ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars((string)($idx + 1)); ?></td>
                                    <td><?php echo !empty($r['request_date']) ? format_date($r['request_date']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($r['revision_notes'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge-status <?php echo htmlspecialchars($rs_badge); ?>">
                                            <?php echo htmlspecialchars($rs); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($r['freelancer_response']) ? htmlspecialchars($r['freelancer_response']) : '—'; ?></td>
                                    <td><?php echo !empty($r['response_date']) ? format_date($r['response_date']) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

  <!-- RIGHT (Quick actions) -->
<div class="order-details-sidebar">
  <div class="order-sidebar-card">
    <h3 class="order-sidebar-title">Actions</h3>
    <div class="order-sidebar-note text-muted">Actions depend on role + status.</div>
    
    <?php if ($is_client): ?>
      <?php if ($status === 'Pending'): ?>
        <a class="btn-danger btn-full-width"
           href="cancel-order.php?order_id=<?php echo urlencode($order_id); ?>">Cancel Order</a>
      <?php endif; ?>
      
    <?php if ($status === 'Delivered'): ?>
        <a class="btn-success btn-full-width"
           href="mark-completed.php?order_id=<?php echo urlencode($order_id); ?>">Mark as Completed</a>
        <?php if ($remaining > 0): ?>
          <a class="btn-primary btn-full-width"
             href="request-revision.php?order_id=<?php echo urlencode($order_id); ?>">Request Revision</a>
        <?php else: ?>
           <span class="btn-primary btn-full-width btn-disabled">Request Revision</span>
            <div class="message-warning">
            You have used all revision requests for this order.
            </div>
        <?php endif; ?>
    <?php endif; ?>
      
      <?php if ($status === 'Revision Requested'): ?>
        <a class="btn-secondary btn-full-width" href="#revision-history">View Revision Status</a>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($is_freelancer): ?>
      <?php if ($status === 'Pending'): ?>
        <a class="btn-primary btn-full-width"
           href="start-working.php?order_id=<?php echo urlencode($order_id); ?>">Start Working</a>
      <?php endif; ?>
      
      <?php if ($status === 'In Progress'): ?>
        <a class="btn-success btn-full-width"
           href="upload-delivery.php?order_id=<?php echo urlencode($order_id); ?>">Upload Delivery</a>
      <?php endif; ?>
      
      <?php if ($status === 'Revision Requested'): ?>
        <?php if ($has_new_revision): ?>
            <a class="btn-success btn-full-width"
            href="respond-revision.php?order_id=<?php echo urlencode($order_id); ?>">
            Respond to Revision
            </a>
        <?php else: ?>
            <span class="btn-secondary btn-full-width" style="pointer-events:none; opacity:.6;">
            No New Revision Request
            </span>
        <?php endif; ?>
    <?php endif; ?>

    <?php endif; ?>
    
    <?php if ($status === 'Cancelled'): ?>
      <div class="alert alert-danger">
        <strong>❌ Order Cancelled</strong>
        <?php if (!empty($cancellation_date)): ?>
          <br><small><strong>Date:</strong> <?php echo htmlspecialchars($cancellation_date); ?></small>
        <?php endif; ?>
        <?php if (!empty($cancellation_reason)): ?>
          <br><small><strong>Reason:</strong> <?php echo htmlspecialchars($cancellation_reason); ?></small>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    
    <a class="btn-secondary btn-full-width" href="my-orders.php">Back to My Orders</a>
  </div>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
