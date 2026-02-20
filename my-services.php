<?php
 /** title  ------- link create service
  *  statistics card frelancer
  *  sort
  * table 
  * footer
  */
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();
$page_title = 'My Services';

if (isset($_POST['toggle_service'])) {

    $service_id = $_POST['service_id'];
    toggle_service_status($pdo, $service_id,$_SESSION['user_id']);
    redirect_with_message( 'my-services.php', 'Service updated successfully', 'success' );
}

// Get freelancer statistics
$stats = get_freelancer_stats($pdo, $_SESSION['user_id']);

$sort = $_GET['sort'] ?? 'newest';

switch ($sort) {
    case 'oldest':
        $order_by = 'created_date ASC';
        break;
    case 'price_low':
        $order_by = 'price ASC';
        break;
    case 'price_high':
        $order_by = 'price DESC';
        break;
    case 'title':
        $order_by = 'title ASC';
        break;
    default:
        $order_by = 'created_date DESC';
        $sort = 'newest';
}

// Fetch all services for this freelancer
$stmt = $pdo->prepare("
    SELECT 
        service_id,
        title,
        category,
        subcategory,
        price,
        status,
        featured_status,
        created_date,
        image_1
    FROM services
    WHERE freelancer_id = :freelancer_id
    ORDER BY $order_by
");
$user_id = $_SESSION['user_id'];
$stmt->execute(['freelancer_id' => $user_id]);
$services = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>My Services</h1>
    <a href="create-service.php" class="btn-primary">Create New Service</a>
</div>

<!-- Statistics  -->
<div class="statistics-card">
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_services']; ?></div>
            <div class="stat-label">Total Services</div>
        </div>
        <div class="stat-card stat-card-active">
            <div class="stat-value"><?php echo $stats['active_services']; ?></div>
            <div class="stat-label">Active Services</div>
        </div>
        <div class="stat-card stat-card-featured">
            <div class="stat-value"><?php echo $stats['featured_services']; ?>/3</div>
            <div class="stat-label">Featured Services</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
            <div class="stat-label">Completed Orders</div>
        </div>
    </div>
</div>

<!-- Sort Controls -->
<div class="table-controls">
    <form method="GET" class="sort-form"> 
        <label for="sort">Sort by:</label>
        <select name="sort" id="sort" onchange="this.form.submit()">
            <option value="newest" >Newest First</option>
            <option value="oldest" >Oldest First</option>
            <option value="price_low" >Price: Low to High</option>
            <option value="price_high" >Price: High to Low</option>
            <option value="title" >Title (A-Z)</option>
        </select>
    </form>
</div>

<!-- Table -->
<?php if (count($services) > 0): ?>
<div class="table-wrapper">
    <table class="services-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Service Title</th>
                <th>Category</th>
                <th>Price</th>
                <th>Status</th>
                <th>Featured</th>
                <th>Created Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <!-- Image -->
                <td>
                    <?php if (!empty($service['image_1']) && file_exists($service['image_1'])): ?>
                        <img src="<?php echo htmlspecialchars($service['image_1']); ?>" 
                             alt="Service" 
                             class="service-thumbnail">
                    <?php else: ?>
                        <div class="service-thumbnail-placeholder">No Image</div>
                    <?php endif; ?>
                </td>
                
                <!--  Title -->
                <td>
                    <a href="service-detail.php?id=<?php echo $service['service_id']; ?>" 
                       class="service-title-link"> <?php echo htmlspecialchars($service['title']); ?>  </a>     
                </td>
                
                <!-- Category -->
                <td><?php echo htmlspecialchars($service['category']); ?></td>
                <!-- Price -->
                <td class="price-cell"><?php echo format_price($service['price']); ?></td>
                <!-- Status -->
                <td>
                    <?php if ($service['status'] === 'Active'): ?>
                        <span class="badge-status status-active">Active</span>
                    <?php else: ?>
                        <span class="badge-status status-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
                
                <!-- Featured -->
                <td>
                    <?php if ($service['featured_status'] === 'Yes'): ?>
                        <span class="featured-indicator">★ Featured</span>
                    <?php else: ?>
                        <span class="text-muted">No</span>
                    <?php endif; ?>
                </td>
                
                <!-- Created Date -->
                <td><?php echo format_date($service['created_date']); ?></td>
                
                <!-- Actions -->
                <td class="actions-cell">
                    <a href="edit-service.php?id=<?php echo $service['service_id']; ?>" 
                       class="btn-action btn-edit">Edit</a>
                    
                    <form method="POST" style="display:inline;">
                            <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">

                            <button type="submit" name="toggle_service"
                                class="btn-action <?php echo $service['status'] === 'Active' ? 'btn-deactivate' : 'btn-activate'; ?>"
                                onclick="return confirm('Are you sure?')">
                                <?php echo $service['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                            </button>
                     </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>  <!-- Empty State -->
<div class="empty-state">
    <div class="empty-icon">📦</div>
    <h2>No Services Yet</h2>
    <p>You haven't created any services. Start by creating your first service!</p>
    <a href="create-service.php" class="btn-primary">Create Your First Service</a>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>