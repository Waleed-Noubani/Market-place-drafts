<?php
// Use Case 5: Edit Service Listing

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer();
$page_title = 'Edit Service';

$service_id = $_GET['id'] ?? ''; // from my-service or service-details.php

if (empty($service_id)) {
    redirect_with_message('my-services.php', 'Invalid service ID', 'error');
}

// Fetch service 
$stmt = $pdo->prepare("
    SELECT * FROM services 
    WHERE service_id = :service_id AND freelancer_id = :freelancer_id
");

$stmt->execute([
    'service_id' => $service_id,
    'freelancer_id' => $_SESSION['user_id']
]);

$service = $stmt->fetch();

if (!$service) {
    redirect_with_message('my-services.php', 'Service not found or access denied', 'error');
}

$categories_data = [
    'Web Development' => ['Frontend Development', 'Backend Development', 'Full Stack Development', 'WordPress Development', 'E-commerce Development'],
    'Graphic Design' => ['Logo Design', 'Brand Identity', 'Web Design', 'Print Design', 'Illustration', 'UI/UX Design'],
    'Writing & Translation' => ['Article Writing', 'Copywriting', 'Proofreading', 'Translation', 'Technical Writing'],
    'Digital Marketing' => ['SEO', 'Social Media Marketing', 'Email Marketing', 'Content Marketing', 'PPC Advertising']
];

$errors = [];

// Handle formmmm 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $title = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $subcategory = sanitize_input($_POST['subcategory'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $delivery_time = (int)($_POST['delivery_time'] ?? 0);
    $revisions = (int)($_POST['revisions'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $featured = isset($_POST['featured']) && $_POST['featured'] === 'Yes' ? 'Yes' : 'No';
    
    // Validation
    if (empty($title)) {
        $errors['title'] = 'Service title is required';
    } elseif (strlen($title) < 10 || strlen($title) > 100) {
        $errors['title'] = 'Title must be between 10 and 100 characters';
    } else {                            // Check uniqueness
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM services 
            WHERE freelancer_id = :id AND title = :title AND service_id != :service_id ");
        $stmt->execute([
            'id' => $_SESSION['user_id'],
            'title' => $title,
            'service_id' => $service_id
        ]);
        if ($stmt->fetchColumn() > 0) {
            $errors['title'] = 'You already have another service with this title';
        }
    }
    
    if (empty($category) || !array_key_exists($category, $categories_data)) {
        $errors['category'] = 'Invalid category';
    }
    
    if (empty($subcategory)) {
        $errors['subcategory'] = 'Subcategory is required';
    } elseif (!empty($category) && !in_array($subcategory, $categories_data[$category] ?? [])) {
        $errors['subcategory'] = 'Invalid subcategory';
    }
    
    if (empty($description) || strlen($description) < 100 || strlen($description) > 2000) {
        $errors['description'] = 'Description must be between 100 and 2000 characters';
    }
    
    if ($delivery_time < 1 || $delivery_time > 90) {
        $errors['delivery_time'] = 'Delivery time must be between 1 and 90 days';
    }
    
    if ($revisions < 0 || $revisions > 999) {
        $errors['revisions'] = 'Revisions must be between 0 and 999';
    }
    
    if ($price < 5 || $price > 10000) {
        $errors['price'] = 'Price must be between $5 and $10,000';
    }
    
    // Featured validation  // Must be Active // allwed 3 featured only each personal
    if ($featured === 'Yes') {
        
        if ($status === 'Inactive') {
            $errors['featured'] = 'Only active services can be featured';
            $featured = 'No';
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM services 
            WHERE freelancer_id = :id 
            AND featured_status = 'Yes' 
            AND service_id != :service_id
        ");
        $stmt->execute([
            'id' => $_SESSION['user_id'],
            'service_id' => $service_id
        ]);
        
        if ($stmt->fetchColumn() >= 3) {
            $errors['featured'] = 'You can only have 3 featured services at a time';
            $featured = 'No';
        }
    }
    
    if ($status === 'Inactive') {
        $featured = 'No';
    }
    
    // Handle images
    $image_paths = [$service['image_1'],$service['image_2'],$service['image_3']]; // خزنت الصور القديمة
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024;  // 5 MB
    
    for ($i = 1; $i <= 3; $i++) {
    if (isset($_FILES["image_$i"]) && $_FILES["image_$i"]['error'] === UPLOAD_ERR_OK) {
        
        $file = $_FILES["image_$i"];
        $validation = validate_file_upload($file, $allowed_types, $max_size);
        
        if (!$validation['success']) {
            $errors["image_$i"] = $validation['error'];
            continue;
        }
        
        list($width, $height) = getimagesize($file['tmp_name']);
        if ($width < 250 || $height < 200) {  
            $errors["image_$i"] = 'Image must be at least 250*200 pixels';
            continue;
        }
        
        //  احذف الصورة القديمة
        if (!empty($image_paths[$i - 1]) && file_exists($image_paths[$i - 1])) {
            unlink($image_paths[$i - 1]);  // delete $service['image_1']
        }
        
        //  حفظ الصورة الجديدة باسم unique
        $service_dir = 'uploads/services/' . $service_id . '/';
        if (!is_dir($service_dir)) {                     //  // إذا المجلد uploads/temp/20000001/ مش موجوداعمله تلقائيًا
            mkdir($service_dir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION); // .png
        $new_filename = 'image_' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_' . time() . '.' . $extension;
        $new_path = $service_dir . $new_filename;  // uploads/temp/20000001/image_02.png
        
        if (move_uploaded_file($file['tmp_name'], $new_path)) { // file[tmp_name] fetch oldest path
            $image_paths[$i - 1] = $new_path; 
        }
    }
}
    
    // Check at least one image
    if (empty($image_paths[0])) {
        $errors['images'] = 'At least one image is required';
    }
    
    //////////////////////////////    update database  /////////////////////////////////
    if (empty($errors)) {
        
        try {
            $stmt = $pdo->prepare("
                UPDATE services 
                SET 
                    title = :title,
                    category = :category,
                    subcategory = :subcategory,
                    description = :description,
                    price = :price,
                    delivery_time = :delivery_time,
                    revisions_included = :revisions,
                    status = :status,
                    featured_status = :featured,
                    image_1 = :image_1,
                    image_2 = :image_2,
                    image_3 = :image_3
                WHERE service_id = :service_id
                AND freelancer_id = :freelancer_id
            ");
            
            $stmt->execute([
                'title' => $title,
                'category' => $category,
                'subcategory' => $subcategory,
                'description' => $description,
                'price' => $price,
                'delivery_time' => $delivery_time,
                'revisions' => $revisions,
                'status' => $status,
                'featured' => $featured,
                'image_1' => $image_paths[0],
                'image_2' => $image_paths[1],
                'image_3' => $image_paths[2],
                'service_id' => $service_id,
                'freelancer_id' => $_SESSION['user_id']
            ]);
            
            redirect_with_message('my-services.php', 'Service updated successfully!', 'success');
            
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
            error_log("Service update error: " . $e->getMessage());
        }
    }
    
    // Update service array 
    $service['title'] = $title;
    $service['category'] = $category;
    $service['subcategory'] = $subcategory;
    $service['description'] = $description;
    $service['delivery_time'] = $delivery_time;
    $service['revisions_included'] = $revisions;
    $service['price'] = $price;
    $service['status'] = $status;
    $service['featured_status'] = $featured;
}


require_once 'includes/header.php';
?>

<div class="form-container" style="max-width: 800px; margin: 0 auto;">
    
    <h1 class="heading-primary">Edit Service</h1>
    
    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="edit-service.php?id=<?php echo $service_id; ?>" enctype="multipart/form-data" class="form">
        
        <div class="form-section">
            <h2 class="heading-secondary">Basic Information</h2>
            <!-- Title -->
            <div class="form-group">
                <label for="title" class="form-label">Service Title *</label>
                <input type="text" id="title" name="title" class="form-input <?php echo isset($errors['title']) ? 'input-error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($service['title']); ?>" required>
                <?php if (isset($errors['title'])): ?>
                    <span class="form-error"><?php echo $errors['title']; ?></span>
                <?php endif; ?>
            </div>
            <!-- category -->
            <div class="form-group">
                <label for="category" class="form-label">Category *</label>
                <select id="category" name="category" class="form-input" required>
                    <?php foreach (array_keys($categories_data) as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $service['category'] === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- subcategory -->
            <div class="form-group">
                <label for="subcategory" class="form-label">Subcategory *</label>
                <select id="subcategory" name="subcategory" class="form-input" required>
                    <?php foreach ($categories_data as $cat => $subs): ?>
                        <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                            <?php foreach ($subs as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>"
                                        <?php echo $service['subcategory'] === $sub ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sub); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- description -->
            <div class="form-group">
                <label for="description" class="form-label">Description *</label>
                <textarea id="description" name="description" class="form-input" rows="6" required><?php echo htmlspecialchars($service['description']); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?php echo $errors['description']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-section">
            <h2 class="heading-secondary">Pricing & Delivery</h2>
            <!-- delivery_time -->
            <div class="form-group">
                <label for="delivery_time" class="form-label">Delivery Time (days) *</label>
                <input type="number" id="delivery_time" name="delivery_time" class="form-input" 
                       value="<?php echo $service['delivery_time']; ?>" min="1" max="90" required>
            </div>
            <!-- revision -->
            <div class="form-group">
                <label for="revisions" class="form-label">Revisions Included *</label>
                <input type="number" id="revisions" name="revisions" class="form-input" 
                       value="<?php echo $service['revisions_included']; ?>" min="0" max="999" required>
            </div>
            <!-- price -->
            <div class="form-group">
                <label for="price" class="form-label">Price (USD) *</label>
                <input type="number" id="price" name="price" class="form-input" 
                       value="<?php echo $service['price']; ?>" min="5" max="10000" step="1" required>
            </div>
        </div>
        
        <!-- Status & Featured -->
        <div class="form-section">
            <h2 class="heading-secondary">Service Status</h2>
            
            <div class="form-group">
                <label class="form-label">Status *</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="status" value="Active" 
                               <?php echo $service['status'] === 'Active' ? 'checked' : ''; ?>>
                        <span>Active </span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="status" value="Inactive"
                               <?php echo $service['status'] === 'Inactive' ? 'checked' : ''; ?>>
                        <span>Inactive </span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="featured" value="Yes"
                           <?php echo $service['featured_status'] === 'Yes' ? 'checked' : ''; ?>>
                    <span>Mark as Featured </span>
                </label>
                <?php if (isset($errors['featured'])): ?>
                    <span class="form-error"><?php echo $errors['featured']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Images -->
        <div class="form-section">
            <h2 class="heading-secondary">Service Images</h2>
            
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="form-group">
                    <label class="form-label">Image <?php echo $i; ?></label>
                    
                    <?php if (!empty($service["image_$i"]) && file_exists($service["image_$i"])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo htmlspecialchars($service["image_$i"]); ?>" 
                                 style="width: 200px; height: 150px; object-fit: cover; border-radius: 4px; border: 1px solid #DEE2E6;"
                            >
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" name="image_<?php echo $i; ?>" class="form-input" 
                           accept="image/jpeg,image/jpg,image/png">
                    <small class="form-hint"> jpeg/JPG/PNG, max 5MB</small>
                    
                    <?php if (isset($errors["image_$i"])): ?>
                        <span class="form-error"><?php echo $errors["image_$i"]; ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary btn-full-width">Update Service</button>
            <a href="my-services.php" class="btn-secondary btn-full-width">Cancel</a>
        </div>
        
    </form>
    
</div>

<?php require_once 'includes/footer.php'; ?>