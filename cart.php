<?php
require_once 'classes/Service.php' ;
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';
require_once 'includes/service-card.php';


require_client();
$page_title = 'Shopping Cart';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    $service_id_toRemove = $_POST['service_id'] ?? '';
    
    foreach ($_SESSION['cart'] as $key => $service) {
        if ($service->getServiceId() === $service_id_toRemove) {
            unset($_SESSION['cart'][$key]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            redirect_with_message('cart.php', 'Service removed from cart', 'success');
        }
     }
    
}

//  نسخة للعرض
$services = $_SESSION['cart'];

$subtotal = 0;
foreach ($services as $service) {
    $subtotal += $service->getPrice();
}
$service_fee = $subtotal * 0.05; 
$total = $subtotal + $service_fee;

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Shopping Cart</h1>
</div>

<!-- Table -->
<?php if (!empty($services)): ?>
<div class="cart-layout">
    
    <!-- Left Column (65%) -->
    <div class="cart-main">
        <div class="table-wrapper">
        <table class="services-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Service Title</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Freelancer Name</th>
                    <th>Delivery Time</th>
                    <th>Revisions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                <tr>
                    <!-- Image -->
                    <td>
                        <a href="service-detail.php?id=<?php echo $service->getServiceId(); ?>">
                        <?php if (!empty($service->getImage()) && file_exists($service->getImage())): ?>
                            <img src="<?php echo htmlspecialchars($service->getImage()); ?>" 
                                alt="Service" 
                                class="service-thumbnail">
                        <?php else: ?>
                            <div class="service-thumbnail-placeholder">No Image</div>
                        <?php endif; ?>
                        </a>
                    </td>
                    
                    <!--  Title -->
                    <td>
                        <a href="service-detail.php?id=<?php echo $service->getServiceId(); ?>" 
                        class="service-title-link"> <?php echo htmlspecialchars($service->getTitle()); ?>  </a>     
                    </td>
                    
                    <!-- Category -->
                    <td><?php echo htmlspecialchars($service->getCategory()); ?></td>
                    <!-- Price -->
                    <td class="price-cell"><?php echo format_price($service->getPrice()); ?></td>
                    <!-- Freelancer Name -->
                    <td><?php echo htmlspecialchars($service->getFreelancerName()); ?></td>
                    <!-- Delivery Time -->
                    <td><?php echo $service->getDeliveryTime(); ?></td>
                    <!-- Revisions Date -->
                    <td><?php echo $service->getRevisionsIncluded(); ?></td>
                    
                    <!-- Actions -->
                    <td class="actions-cell">
                        <form method="POST">
                                <input type="hidden" name="service_id" value="<?php echo $service->getServiceId(); ?>">
                                <button type="submit" name="remove"  class="btn-danger"
                                          onclick="return confirm('Are you sure?')" >  Remove  </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Sidebar: Order Summary (30%) -->
    <div class="cart-sidebar">
        <div class="cart-summary-card">
            <h2>Order Summary</h2>
            
            <div class="summary-items">
                <div class="summary-row">
                    <span>Services Subtotal:</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Service Fee (5%):</span>
                    <span>$<?php echo number_format($service_fee, 2); ?></span>
                </div>
            </div>
            
            <div class="summary-total">
                <span>Total:</span>
                <span>$<?php echo number_format($total, 2); ?></span>
            </div>
            
            <form method="POST" action="checkout.php" class="cart-checkout-form">
                <button type="submit" name="action" value="order_now" class="btn-success btn-full-width">Proceed to Checkout </button>
            </form>
        </div>
    </div>

</div>

<?php else: ?>  <!-- Empty State -->
    <div class="empty-state">
        <div class="empty-icon">🛒</div>
        <h2>Your cart is empty</h2>
        <p>You haven't added any services yet. Start browsing!</p>
        <a href="index.php" class="btn-primary">Browse Services</a>
    </div>
    <?php
    if (isset($_COOKIE['recently_viewed'])) {
            $viewed_ids = explode(',', $_COOKIE['recently_viewed']); // $viewed_ids = ['12', '7', '3', '20'];
            $viewed_ids = array_slice($viewed_ids, 0, 4);    //  بوخذ اول 4
            
            if (!empty($viewed_ids)) {
                $placeholders = implode(',', array_fill(0, count($viewed_ids), '?')); // (?,?,?,?)
               $stmt = $pdo->prepare("
                    SELECT 
                        s.service_id,
                        s.title,
                        s.category,
                        s.price,
                        s.delivery_time,
                        s.featured_status,
                        s.image_1,
                        CONCAT(u.first_name, ' ', u.last_name) AS freelancer_name,
                        u.profile_photo
                    FROM services s
                    INNER JOIN users u ON s.freelancer_id = u.user_id
                    WHERE s.service_id IN ($placeholders)          
                    AND s.status = 'Active'
                    LIMIT 4
                ");           // WHERE service_id IN (?,?,?,?)

                $stmt->execute($viewed_ids);
                $inCookie_services = $stmt->fetchAll();
                
                if (!empty($inCookie_services)): ?>
                <div class="services-grid">
                        <?php foreach ($inCookie_services as $service): ?>
                            <?php include 'includes/service-card.php'; ?>
                        <?php endforeach; ?>
                </div>  
                <?php endif; 
       }
     }
 endif; ?>


<?php include 'includes/footer.php'; ?>