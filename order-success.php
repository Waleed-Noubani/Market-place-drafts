<?php
 // Order Success Page  / after successful order place

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Order Confirmation';

if (!isset($_SESSION['recent_orders']) || empty($_SESSION['recent_orders'])) { // from Checkout.php --> idOrder
    redirect_with_message('index.php', 'No recent orders found', 'warning');
}

$order_ids = $_SESSION['recent_orders'];

// Fetch order details
$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$stmt = $pdo->prepare("
    SELECT 
        o.order_id,
        o.service_title,
        o.price,
        o.status,
        o.order_date,
        o.expected_delivery,
        CONCAT(u.first_name, ' ', u.last_name) as freelancer_name,
        u.user_id as freelancer_id
    FROM orders o
    INNER JOIN users u ON o.freelancer_id = u.user_id
    WHERE o.order_id IN ($placeholders)
    ORDER BY o.order_date DESC
");

$stmt->execute($order_ids);
$orders = $stmt->fetchAll();

// Calculate total
$total_amount = 0;
foreach ($orders as $order) {
    $total_amount += $order['price'] + ($order['price'] * 0.05);
}

// Clear recent orders 
unset($_SESSION['recent_orders']);

require_once 'includes/header.php';
?>

<!-- Success Banner -->
<div class="order-success-banner">
    
    <div class="order-success-icon">
        <span class="order-success-check">✓</span>
    </div>
    
    <h1 class="order-success-title">
        Orders Placed Successfully!
    </h1>
    
    <p class="order-success-subtitle">
        You have placed <?php echo count($orders); ?> order<?php echo count($orders) > 1 ? 's' : ''; ?>
    </p>
    
    <p class="order-success-total">
        Total Amount: <?php echo format_price($total_amount); ?>
    </p>
    
</div>

<!-- Orders Section -->
<div class="order-success-container">
    
    <h2 class="order-success-heading">
        Your Orders
    </h2>
    
    <div class="order-success-list">
        
        <?php foreach ($orders as $order): ?>
            
            <div class="order-card">
                
                <div class="order-card-header">
                    
                    <div>
                        <h3 class="order-card-title">
                            Order #<?php echo htmlspecialchars($order['order_id']); ?>
                        </h3>
                        <p class="order-card-date">
                            Placed on <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                        </p>
                    </div>
                    
                    <div>
                        <span class="order-card-status">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                    
                </div>
                
                <div class="order-card-body">
                    
                    <div class="order-card-service">
                        <strong class="order-card-service-title">
                            <?php echo htmlspecialchars($order['service_title']); ?>
                        </strong>
                        <p class="order-card-freelancer">
                            by <a href="#" class="order-card-freelancer-link">
                                <?php echo htmlspecialchars($order['freelancer_name']); ?>
                            </a>
                        </p>
                    </div>
                    
                    <div class="order-card-grid">
                        
                        <div class="order-card-metric">
                            <span class="order-card-metric-label">Total Amount</span>
                            <strong class="order-card-metric-value">
                                <?php echo format_price($order['price'] + ($order['price'] * 0.05)); ?>
                            </strong>
                        </div>
                        
                        <div class="order-card-metric">
                            <span class="order-card-metric-label">Expected Delivery</span>
                            <strong class="order-card-metric-date">
                                <?php echo date('M d, Y', strtotime($order['expected_delivery'])); ?>
                            </strong>
                        </div>
                        
                    </div>
                    
                </div>
                
                <div class="order-card-actions">
                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn-primary order-card-btn">
                        View Order Details
                    </a>
                </div>
                
            </div>
            
        <?php endforeach; ?>
        
    </div>
    
    <div class="order-success-actions">
        
        <a href="my-orders.php" class="btn-primary order-success-action-btn">
            View All Orders
        </a>
        
        <a href="index.php" class="btn-secondary order-success-action-btn">
            Browse More Services
        </a>
        
    </div>
    
</div>

<?php require_once 'includes/footer.php'; ?>