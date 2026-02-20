<?php
/**
 * Add to Cart
 * Use Case 8: Add Service to Cart
 */
require_once 'classes/Service.php'; 
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';
require_once 'classes/Service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // هذا جاي من service-detail
    header('Location: index.php');
    exit;
}

$service_id = $_POST['service_id'] ?? ''; //  هذا جاي من service-detail

if (empty($service_id)) {
    redirect_with_message('index.php', 'Invalid service', 'error');
}


// Fetch service with freelancer info
$stmt = $pdo->prepare("
    SELECT 
        s.service_id,
        s.title,
        s.category,
        s.subcategory,
        s.price,
        s.delivery_time,
        s.revisions_included,
        s.freelancer_id,
        s.status,
        s.image_1,
        CONCAT(u.first_name, ' ', u.last_name) as freelancer_name
    FROM services s
    INNER JOIN users u ON s.freelancer_id = u.user_id
    WHERE s.service_id = :service_id
");

$stmt->execute(['service_id' => $service_id]);
$service = $stmt->fetch();


if (!$service) {
    redirect_with_message('index.php', 'Service not found', 'error');
}


// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

//  فحص إذا الخدمة موجودة من قبل لمنع التكرار  // مع اني متحقق بكلاس تفاصيل الخدمة قبل ضغط الز
foreach ($_SESSION['cart'] as $item) {   
    if ($item->getServiceId() === $service_id) {
        redirect_with_message( "service-detail.php?id=$service_id", 'Service already in cart', 'warning');
    }
}

// Create Service object and add to cart
$data = [
    'service_id' => $service['service_id'],
    'title' => $service['title'],
    'category' => $service['category'],
    'subcategory' => $service['subcategory'],
    'price' => $service['price'],
    'delivery_time' => $service['delivery_time'],
    'revisions_included' => $service['revisions_included'],
    'freelancer_id' => $service['freelancer_id'],
    'freelancer_name' => $service['freelancer_name'],
    'image_1' => $service['image_1'],
    'added_timestamp' => time()
];

$serviceObj = new Service($data); 

// Add to cart
$_SESSION['cart'][] = $serviceObj;

//  action: add or order_now
$action = $_POST['action'] ?? 'add';

if ($action === 'order_now') {
    redirect_with_message('checkout.php', 'Service added to cart. Complete your order below.', 'success');
} else {
    redirect_with_message("service-detail.php?id=$service_id",'Service added to cart successfully!', 'success');
}
?>