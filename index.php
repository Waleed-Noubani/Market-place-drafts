<?php
// Use Case 6: Browse and Search Services
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

$page_title = 'Browse Services'; // رح نستخدها بصفحة الهيدر

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';


$query = "SELECT 
    s.service_id,
    s.title,
    s.category,
    s.subcategory,
    s.price,
    s.delivery_time,
    s.featured_status,
    s.image_1,
    s.created_date,
    u.user_id AS freelancer_id,
    CONCAT(u.first_name, ' ', u.last_name) AS freelancer_name,
    u.profile_photo
FROM services s, users u
WHERE s.freelancer_id = u.user_id
  AND s.status = 'Active'";


$params = [];

// Add search condition
if (!empty($search)) {
    $query .= " AND (s.title LIKE :search OR s.description LIKE :search)"; // يستخدم LIKE و % لدعم البحث الجزئي (partial matches).
    $params['search'] = '%' . $search . '%';
}

// Add category filter
if (!empty($category)) {
    $query .= " AND s.category = :category";
    $params['category'] = $category;
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY s.created_date ASC";
        break;
    case 'price_low':
        $query .= " ORDER BY s.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY s.price DESC";
        break;
    default:
        $query .= " ORDER BY s.created_date DESC";
        $sort = 'newest';
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll();

// طريقة بصفي فيها الخدمات المميزة من الاراي الشاملة (مجرد نسخ وليس حذف من الاراي الاصلية)
$featured_services = array_filter($services, function($s) {
    return $s['featured_status'] === 'Yes';
});

// جبت الكاتيجوري الموجودات عندي وبدهن ينحطين بالقائمة بدون تكرار
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM services WHERE status = 'Active' ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

require_once 'includes/header.php';
?>

<!-- Search and Filter Bar -->
<div class="search-filter-bar">
    <form method="GET" action="index.php" class="filter-form">
        
        <?php if (!empty($search)): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"> <!-- index.php?search=logo&category=Design-->
        <?php endif; ?>
        
        <!-- Category Filter -->
        <div class="filter-group">
            <label for="category">Category:</label>
            <select name="category" id="category" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Sort -->
        <div class="filter-group">
            <label for="sort">Sort by:</label>
            <select name="sort" id="sort" onchange="this.form.submit()">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
            </select>
        </div>
        
    </form>
    
    <!-- Clear Filters -->
    <?php if (!empty($search) || !empty($category)): ?>
        <a href="index.php" class="btn-secondary">Clear Filters</a>
    <?php endif; ?>
</div>

<div class="results-summary">
    <?php if (!empty($search)): ?>
        <h2>Search results for "<?php echo htmlspecialchars($search); ?>"</h2>
    <?php elseif (!empty($category)): ?>
        <h2>Category: <?php echo htmlspecialchars($category); ?></h2>
    <?php else: ?>
        <h2>All Services</h2>
    <?php endif; ?>
    
    <p class="text-muted"><?php echo count($services); ?> services found</p>
</div>

<!-- Featured Services Section -->
<?php if (!empty($featured_services) && empty($search) && empty($category)): ?>
    <section class="featured-section">
        <h2>Featured Services</h2>
        <div class="services-grid">
            <?php foreach ($featured_services as $service): ?>
                <?php include 'includes/service-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <hr class="section-divider">
<?php endif; ?>

<!-- All Services Grid -->
<?php if (empty($services)): ?>   
    <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <h2>No services found</h2>
        <p>Try adjusting your search or filters</p>
        <a href="index.php" class="btn-primary">Show All Services</a>
    </div>
    
<?php else: ?>
    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <?php include 'includes/service-card.php'; ?>
        <?php endforeach; ?>
    </div>  
<?php endif; ?>


<?php require_once 'includes/footer.php'; ?>