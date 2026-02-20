<?php
// Use Case 7: View Service Details

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

$service_id = $_GET['id'] ?? ''; // from url index.php

if (empty($service_id)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        s.service_id,
        s.freelancer_id,
        s.title,
        s.category,
        s.subcategory,
        s.description,
        s.price,
        s.delivery_time,
        s.revisions_included,
        s.image_1,
        s.image_2,
        s.image_3,
        s.status,
        s.created_date,
        CONCAT(u.first_name, ' ', u.last_name) as freelancer_name,
        u.profile_photo,
        u.registration_date
    FROM services s
    INNER JOIN users u ON s.freelancer_id = u.user_id
    WHERE s.service_id = :service_id
");

$stmt->execute(params: ['service_id' => $service_id]);
$service = $stmt->fetch();

if (!$service) {
    include 'includes/header.php';
    ?>
    <div class="empty-state">
        <div class="empty-icon">❌</div>
        <h2>Service Not Found</h2>
        <p>The service you're looking for doesn't exist or has been removed.</p>
        <a href="index.php" class="btn-primary">Browse All Services</a>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

$is_owner = (is_logged_in() && $service['freelancer_id'] === $_SESSION['user_id']);

if ($service['status'] !== 'Active' && !$is_owner) {
    include 'includes/header.php';
    ?>
    <div class="empty-state">
        <div class="empty-icon">⚠️</div>
        <h2>Service No Longer Available</h2>
        <p>This service is currently inactive.</p>
        <a href="index.php" class="btn-primary">Browse All Services</a>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

$cookie_value = $_COOKIE['recently_viewed'] ?? ''; // قراءة الكوكي إن وُجد.

//تحويل النص من "3,5,7"إلى:--> [3,5,7]
$viewed_services = !empty($cookie_value) ? explode(',', $cookie_value) : []; // array 

// إزالة الخدمة الحالية إن كانت موجودة مسبقا
$viewed_services = array_filter($viewed_services, function($id) use ($service_id) { // filter array
    return $id !== $service_id;
});

// اضافة الخدمة الحالية
$viewed_services[] = $service_id; // add new service to array

// اذا السايز زاد عن 4 انا بحفظ اخر اربعه تمت اضافتهن
if (count($viewed_services) > 4) {
    $viewed_services = array_slice($viewed_services, -4); 
}

// Save cookie (30 days)     / = متاح في كامل الموقع.           // store array to cookie
$cookie_value = implode(',', $viewed_services); // الكوكي يخزن فقط String  :  convert array to string
setcookie('recently_viewed' , $cookie_value, time() + (30 * 24 * 60 * 60), '/');



$page_title = $service['title'];
include 'includes/header.php';
?>

<div class="service-detail-layout">
    
    <!-- Left Column (65%) -->
    <div class="service-detail-left">
        
       
        <div class="image-gallery">
            
            <!-- Main Image Display -->
            <div class="main-image-container">
                <?php if (!empty($service['image_1']) && file_exists($service['image_1'])): ?>
                    <img id="img1" src="<?php echo htmlspecialchars($service['image_1']); ?>" 
                         alt="<?php echo htmlspecialchars($service['title']); ?>" 
                         class="main-image main-image-active">
                <?php endif; ?>
                
                <?php if (!empty($service['image_2']) && file_exists($service['image_2'])): ?>
                    <img id="img2" src="<?php echo htmlspecialchars($service['image_2']); ?>" 
                         alt="<?php echo htmlspecialchars($service['title']); ?>" 
                         class="main-image">
                <?php endif; ?>
                
                <?php if (!empty($service['image_3']) && file_exists($service['image_3'])): ?>
                    <img id="img3" src="<?php echo htmlspecialchars($service['image_3']); ?>" 
                         alt="<?php echo htmlspecialchars($service['title']); ?>" 
                         class="main-image">
                <?php endif; ?>
            </div>
            
            <!-- Thumbnails   الصور المصغرة--> 
            <div class="thumbnails-container">
                <?php if (!empty($service['image_1']) && file_exists($service['image_1'])): ?>
                    <a href="#img1" class="thumbnail thumbnail-active">
                        <img src="<?php echo htmlspecialchars($service['image_1']); ?>" alt="Thumbnail 1">
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($service['image_2']) && file_exists($service['image_2'])): ?>
                    <a href="#img2" class="thumbnail">
                        <img src="<?php echo htmlspecialchars($service['image_2']); ?>" alt="Thumbnail 2">
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($service['image_3']) && file_exists($service['image_3'])): ?>
                    <a href="#img3" class="thumbnail">
                        <img src="<?php echo htmlspecialchars($service['image_3']); ?>" alt="Thumbnail 3">
                    </a>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- Title -->
        <h1 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h1>
        
        <!-- Category  -->
        <div class="category-breadcrumb"> 
            <a href="index.php?category=<?php echo urlencode($service['category']); ?>">
                <?php echo htmlspecialchars($service['category']); ?>
            </a>
            <span class="breadcrumb-separator">›</span>
            <span><?php echo htmlspecialchars($service['subcategory']); ?></span>
        </div>
        
        <!-- Freelancer Info  -->
        <div class="freelancer-card">
            <div class="freelancer-photo">
                <?php if (!empty($service['profile_photo']) && file_exists($service['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($service['profile_photo']); ?>" alt="Freelancer">
                <?php endif; ?>
            </div>
            <div class="freelancer-info">
                <h3 class="freelancer-name"><?php echo htmlspecialchars($service['freelancer_name']); ?></h3>
                <p class="freelancer-member">Member since <?php echo date('M Y', strtotime($service['registration_date'])); ?></p>
                <!-- <a href="profile.php?id=<?php //echo $service['freelancer_id']; ?>" class="freelancer-link">View Profile →</a> -->
            </div>
        </div>
        
        <!-- Description -->
        <div class="service-description">
            <h2>About This Service</h2>
            <p><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
        </div>
        
    </div>
    
    <!-- Right Column (35%) - Sticky Booking Card -->
    <div class="service-detail-right">
        <div class="booking-card">
            
            <!-- Price -->
            <div class="booking-price">
                <span class="price-label">Starting at</span>
                <span class="price-amount"><?php echo format_price($service['price']); ?></span>
            </div>
            
            <!-- Service Info -->
            <div class="booking-info">
                <div class="info-item">
                    <span class="info-icon">⏱</span>
                    <span class="info-text"><?php echo $service['delivery_time']; ?> days delivery</span>
                </div>
                <div class="info-item">
                    <span class="info-text"><?php echo $service['revisions_included']; ?> revisions </span>
                </div>
            </div>
            
            <!-- Buttons -->
            <div class="booking-actions">
                
                <?php if (!is_logged_in()): ?> <!-- Guest User -->
                    <a href="login.php" class="btn-primary btn-full-width">Login to Order</a>
                    
                <?php elseif ($is_owner): ?> <!-- Service Owner (Freelancer) -->
                    <a href="edit-service.php?id=<?php echo $service['service_id']; ?>" class="btn-primary btn-full-width">Edit Service</a>
                    
                <?php else: ?>  <!-- Client or Other Freelancer -->       
                   <?php if (is_in_cart($service_id)): ?>
                        <button class="btn-secondary btn-full-width" disabled>Already in Cart</button>
                        <a href="cart.php" class="btn-primary btn-full-width">View Cart</a>
                    <?php else: ?>
                        <form method="POST" action="add-to-cart.php" style="margin: 0;">
                            <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                            <button type="submit" name="action" value="add" class="btn-primary btn-full-width">Add to Cart</button>
                            <button type="submit" name="action" value="order_now" class="btn-success btn-full-width">Order Now</button>
                        </form>
                    <?php endif; ?>
                    
                <?php endif; ?>
                
            </div>
            
        </div>
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>