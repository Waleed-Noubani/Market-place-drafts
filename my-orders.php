<?php

 // Use Case 11.2: My Orders Page
 
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    redirect_with_message('login.php', 'Please login first', 'warning');
}

$page_title = 'My Orders';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? ''; // 'Client' أو 'Freelancer' (عدّل حسب مشروعك)

// فلتر الحالة
$allowed_statuses = ['All', 'Pending', 'In Progress', 'Delivered', 'Revision Requested', 'Completed', 'Cancelled'];
$status_filter = sanitize_input($_GET['status'] ?? 'All');
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'All';
}

$params = ['user_id' => $user_id];

if ($user_role === 'Freelancer') {
    $where = "o.freelancer_id = :user_id";
    $other_party_label = "Client";
    $join_other = "INNER JOIN users other ON o.client_id = other.user_id";
} else {
    $where = "o.client_id = :user_id";
    $other_party_label = "Freelancer";
    $join_other = "INNER JOIN users other ON o.freelancer_id = other.user_id";
}

if ($status_filter !== 'All') {
    $where .= " AND o.status = :status";
    $params['status'] = $status_filter;
}

$sql = "
    SELECT
        o.order_id,
        o.service_title,
        o.price,
        o.status,
        o.order_date,
        o.expected_delivery,
        CONCAT(other.first_name, ' ', other.last_name) AS other_party_name
    FROM orders o
    $join_other
    WHERE $where
    ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1>My Orders</h1>
</div>

<div class="search-filter-bar">
    <form method="GET" action="my-orders.php" class="filter-form">
        <div class="filter-group">
            <label for="status">Filter by Status</label>
            <select id="status" name="status">
                <?php foreach ($allowed_statuses as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="min-width: 160px;">
            <label>&nbsp;</label> 
            <button type="submit" class="btn-primary">Apply Filter</button>
        </div>
    </form>
</div>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h2>No orders found</h2>
        <a href="index.php" class="btn-primary">Browse Services</a>
    </div>
<?php else: ?>

    <div class="table-wrapper">
        <table class="services-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Service</th>
                    <th><?php echo htmlspecialchars($other_party_label); ?></th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Order Date</th>
                    <th>Expected Delivery</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($orders as $o): ?>
                    <?php
                        $status = $o['status'];
                        $badge_class = 'status-pending';

                        if ($status === 'Pending') $badge_class = 'status-pending';
                        elseif ($status === 'In Progress') $badge_class = 'status-in-progress';
                        elseif ($status === 'Delivered') $badge_class = 'status-completed'; // أقرب ستايل عندك
                        elseif ($status === 'Revision Requested') $badge_class = 'status-warning'; // ما عندك badge جاهز، رح نعالجها ب CSS صغير لاحقاً إذا بدك
                        elseif ($status === 'Completed') $badge_class = 'status-completed';
                        elseif ($status === 'Cancelled') $badge_class = 'status-cancelled';

                        $total = (float)$o['price'] + ((float)$o['price'] * 0.05);
                    ?>
                    <tr>
                        <td>
                            <a class="service-title-link" href="order-details.php?id=<?php echo urlencode($o['order_id']); ?>">
                                #<?php echo htmlspecialchars($o['order_id']); ?>
                            </a>
                        </td>

                        <td><?php echo htmlspecialchars($o['service_title']); ?></td>

                        <td><?php echo htmlspecialchars($o['other_party_name']); ?></td>

                        <td class="price-cell"><?php echo format_price($total); ?></td>

                        <td>
                            <span class="badge-status <?php echo htmlspecialchars($badge_class); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>

                        <td><?php echo date('M d, Y', strtotime($o['order_date'])); ?></td>

                        <td>
                            <?php echo !empty($o['expected_delivery']) ? date('M d, Y', strtotime($o['expected_delivery'])) : '—'; ?>
                        </td>

                        <td>
                            <div class="actions-cell">
                                <a class="btn-action btn-edit" href="order-details.php?id=<?php echo urlencode($o['order_id']); ?>">
                                    View Details
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
