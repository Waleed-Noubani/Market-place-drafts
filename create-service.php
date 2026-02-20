<?php

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_freelancer(); 

$page_title = 'Create Service';

// Get current step
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(3, $step));

$errors = [];

$categories_data = [
    'Web Development' => ['Frontend Development', 'Backend Development', 'Full Stack Development', 'WordPress Development', 'E-commerce Development'],
    'Graphic Design' => ['Logo Design', 'Brand Identity', 'Web Design', 'Print Design', 'Illustration', 'UI/UX Design'],
    'Writing & Translation' => ['Article Writing', 'Copywriting', 'Proofreading', 'Translation', 'Technical Writing'],
    'Digital Marketing' => ['SEO', 'Social Media Marketing', 'Email Marketing', 'Content Marketing', 'PPC Advertising']
];

/////////////////////////// STEP 1: Basic Information /////////////////////////

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $title = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $subcategory = sanitize_input($_POST['subcategory'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $delivery_time = (int)($_POST['delivery_time'] ?? 0);
    $revisions = (int)($_POST['revisions'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    
    // Validation
    if (empty($title)) {
        $errors['title'] = 'Service title is required';
    } elseif (strlen($title) < 10 || strlen($title) > 100) {
        $errors['title'] = 'Title must be between 10 and 100 characters';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :id AND title = :title");
        $stmt->execute(['id' => $_SESSION['user_id'] , 'title' => $title]);
        if ($stmt->fetchColumn() > 0) {
            $errors['title'] = 'You already have a service with this title';
        }
    }
    
    if (empty($category)) {
        $errors['category'] = 'Category is required';
    } elseif (!array_key_exists($category, $categories_data)) {
        $errors['category'] = 'Invalid category';
    }
    
    if (empty($subcategory)) {
        $errors['subcategory'] = 'Subcategory is required';
    } elseif (!empty($category) && !in_array($subcategory, $categories_data[$category] ?? [])) {
        $errors['subcategory'] = 'Invalid subcategory';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Description is required';
    } elseif (strlen($description) < 50 || strlen($description) > 2000) {
        $errors['description'] = 'Description must be between 50 and 2000 characters';
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
                                                                     // ممنوع يكون عندي اكتف اكثر من 50
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :id AND status = 'Active'");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    if ($stmt->fetchColumn() >= 50) {
        $errors['general'] = 'Maximum 50 active services allowed';
    }
    
    if (empty($errors)) {
        $_SESSION['service_creation'] = [   
            'step' => 1,
            'title' => $title,
            'category' => $category,
            'subcategory' => $subcategory,
            'description' => $description,
            'delivery_time' => $delivery_time,
            'revisions' => $revisions,
            'price' => $price
        ];
        header('Location: create-service.php?step=2');
        exit;
    }
}

//////////////////////////////// STEP 2: Upload Images ///////////////////////////////

if ($step === 2) {
    
    if (!isset($_SESSION['service_creation']['step']) || $_SESSION['service_creation']['step'] < 1) {
        header('Location: create-service.php?step=1');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024;  // = 5MB
        $uploaded_images = [];
        
        for ($i = 1; $i <= 3; $i++) {
            if (isset($_FILES["image_$i"]) && $_FILES["image_$i"]['error'] === UPLOAD_ERR_OK) {
                
                $file = $_FILES["image_$i"];
                $validation = validate_file_upload($file, $allowed_types, $max_size);
                
                if (!$validation['success']) {
                    $errors["image_$i"] = $validation['error'];
                    continue;
                }
                
                list($width, $height) = getimagesize($file['tmp_name']);
                if ($width < 100 || $height < 200) {
                    $errors["image_$i"] = 'Image must be at least 800x600 pixels';
                    continue;
                }
                
                $temp_dir = 'uploads/temp/' . session_id() . '/';   // uploads/temp/a3f9k2l8m1/
                if (!is_dir($temp_dir)) {                 // إذا المجلد uploads/temp/abc123/ مش موجوداعمله تلقائيًا

                    mkdir($temp_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION); // .png
                $temp_filename = 'image_' . str_pad($i, 2, '0', STR_PAD_LEFT) . '.' . $extension; //image_01.jpg
                $temp_path = $temp_dir . $temp_filename;     // uploads/temp/a3f9k2l8m1/image_01.jpg
                
                if (move_uploaded_file($file['tmp_name'], $temp_path)) {  // ننقل الصورة من المكان المؤقت للسيرفر.
                    $uploaded_images["image_$i"] = $temp_path;                // $uploaded_images["image_$i"] = $temp_path;

                }
            }
        }  // end for loop
        
        if (empty($uploaded_images)) { // لو ما اختار ولا صورة
            $errors['general'] = 'At least one image is required';
        }
        
        $main_image = $_POST['main_image'] ?? '1'; // 1  || 2 || 3
        if (!isset($uploaded_images["image_$main_image"])) {      // لو هاي الي اخترتها صورة رئيسية هي بالاصل مش موجودة ؟ 
            $main_image = array_key_first($uploaded_images); // اختار اول صورة تم تحميلها 
            $main_image = str_replace('image_', '', $main_image); 
        }
        
        if (empty($errors)) {
            $_SESSION['service_creation']['step'] = 2;
            $_SESSION['service_creation']['images'] = $uploaded_images;
            $_SESSION['service_creation']['main_image'] = $main_image;
            header('Location: create-service.php?step=3');
            exit;
        }
    }
}


////////////////////////// STEP 3: Review && Confirm  /////////////////////////

if ($step === 3) {
    
    if (!isset($_SESSION['service_creation']['step']) || $_SESSION['service_creation']['step'] < 2) {
        header('Location: create-service.php?step=1');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
        
        $service_data = $_SESSION['service_creation'];
        
        try {
            $service_id = generate_unique_id($pdo, 'services', 'service_id');
            
            $permanent_dir = 'uploads/services/' . $service_id . '/';    // مسار مجلد دائم خاص بهالخدمة.
            if (!is_dir($permanent_dir)) {             // إذا المجلد uploads/services/$service_id/ مش موجوداعمله تلقائيًا
                mkdir($permanent_dir, 0755, true);   
            }
            
            $image_paths = [null, null, null];
            foreach ($service_data['images'] as $key => $temp_path) { // نلف على الصور اللي تم رفعها بالخطوة الثانية.
                if ($temp_path && file_exists($temp_path)) {
                    $index = (int)str_replace('image_', '', $key); // نحول image_1 --> 1

                    //نعيد تسمية الصورة بشكل مرتب: image_01.jpg
                    $new_filename = 'image_' . str_pad($index, 2, '0', STR_PAD_LEFT) . '.' . pathinfo($temp_path, PATHINFO_EXTENSION);
                    $new_path = $permanent_dir . $new_filename;   // uploads/services/$service_id/image_01.jpg
                    
                    if (rename($temp_path, $new_path)) { // REname
                        $image_paths[$index - 1] = $new_path;
                    }
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO services (
                    service_id, freelancer_id, title, category, subcategory,
                    description, price, delivery_time, revisions_included,
                    image_1, image_2, image_3, status, featured_status, created_date
                ) VALUES (
                    :service_id, :freelancer_id, :title, :category, :subcategory,
                    :description, :price, :delivery_time, :revisions,
                    :image_1, :image_2, :image_3, 'Active', 'No', NOW()
                )
            ");
            
            $stmt->execute([
                'service_id' => $service_id,
                'freelancer_id' => $_SESSION['user_id'],
                'title' => $service_data['title'],
                'category' => $service_data['category'],
                'subcategory' => $service_data['subcategory'],
                'description' => $service_data['description'],
                'price' => $service_data['price'],
                'delivery_time' => $service_data['delivery_time'],
                'revisions' => $service_data['revisions'],
                'image_1' => $image_paths[0],
                'image_2' => $image_paths[1],
                'image_3' => $image_paths[2]
            ]);
            
            $temp_dir = 'uploads/temp/' . session_id() . '/';  // نحذف المجلد المؤقت بعد نقل الصور.
            if (is_dir($temp_dir)) {
                rmdir($temp_dir);
            }
            
            unset($_SESSION['service_creation']); // clear SESSION
            redirect_with_message('my-services.php', "Service created successfully! Service ID: $service_id", 'success');
            
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
            error_log("Service creation error: " . $e->getMessage());
        }
    }
}

require_once 'includes/header.php';
?>

<div class="breadcrumb-progress">
    <div class="step-container"> 
        <div class="step <?php echo $step >= 1 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 1 ? 'step-active' : ''; ?>">
            <div class="step-icon"><?php echo $step > 1 ? '✓' : '1'; ?></div>
            <div class="step-label">Basic Information</div>
        </div>
        <div class="step-connector <?php echo $step >= 2 ? 'connector-completed' : ''; ?>"></div>
        <div class="step <?php echo $step >= 2 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 2 ? 'step-active' : ''; ?>">
            <div class="step-icon"><?php echo $step > 2 ? '✓' : '2'; ?></div>
            <div class="step-label">Upload Images</div>
        </div>
        <div class="step-connector <?php echo $step >= 3 ? 'connector-completed' : ''; ?>"></div>
        <div class="step <?php echo $step >= 3 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 3 ? 'step-active' : ''; ?>">
            <div class="step-icon">3</div>
            <div class="step-label">Review && Confirm</div>
        </div>
    </div>
</div>

<div class="form-container" style="max-width: 800px; margin: 0 auto;">
    
    <?php if (!empty($errors['general'])): ?>
        <div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
    <?php endif; ?>
    
    <?php if ($step === 1): ?>           <!-- STEP 1 FORM: Basic Information -->

        <h1 class="heading-primary">Create New Service</h1>
        <h2 class="heading-secondary">Step 1: Basic Information</h2>
        
        <form method="POST" action="create-service.php?step=1" class="form">
            
            <div class="form-group">
                <label for="title" class="form-label">Service Title *</label>
                <input type="text" id="title" name="title" class="form-input <?php echo isset($errors['title']) ? 'input-error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($title ?? $_SESSION['service_creation']['title'] ?? ''); ?>" 
                       required>
                <small class="form-hint">10-100 characters</small>
                <?php if (isset($errors['title'])): ?>
                    <span class="form-error"><?php echo $errors['title']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="category" class="form-label">Category *</label>
                <select id="category" name="category" class="form-input <?php echo isset($errors['category']) ? 'input-error' : ''; ?>" required>
                    <option value="">Select Category</option>
                    <?php foreach (array_keys($categories_data) as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo ($category ?? $_SESSION['service_creation']['category'] ?? '') === $cat ? 'selected' : ''; ?>> 
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <span class="form-error"><?php echo $errors['category']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="subcategory" class="form-label">Subcategory *</label>
                <select id="subcategory" name="subcategory" class="form-input <?php echo isset($errors['subcategory']) ? 'input-error' : ''; ?>" required>
                    <option value="">Select Subcategory</option>
                    <?php foreach ($categories_data as $cat => $subs): ?>
                        <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                            <?php foreach ($subs as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>"
                                        <?php echo ($subcategory ?? $_SESSION['service_creation']['subcategory'] ?? '') === $sub ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sub); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['subcategory'])): ?>
                    <span class="form-error"><?php echo $errors['subcategory']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Description *</label>
                <textarea id="description" name="description" class="form-input <?php echo isset($errors['description']) ? 'input-error' : ''; ?>" 
                          rows="6" required><?php echo htmlspecialchars($description ?? $_SESSION['service_creation']['description'] ?? ''); ?></textarea>
                <small class="form-hint">100-2000 characters</small>
                <?php if (isset($errors['description'])): ?>
                    <span class="form-error"><?php echo $errors['description']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="delivery_time" class="form-label">Delivery Time (days) *</label>
                <input type="number" id="delivery_time" name="delivery_time" class="form-input <?php echo isset($errors['delivery_time']) ? 'input-error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($delivery_time ?? $_SESSION['service_creation']['delivery_time'] ?? ''); ?>" 
                       min="1" max="90" required>
                <small class="form-hint">1-90 days</small>
                <?php if (isset($errors['delivery_time'])): ?>
                    <span class="form-error"><?php echo $errors['delivery_time']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="revisions" class="form-label">Revisions Included *</label>
                <input type="number" id="revisions" name="revisions" class="form-input <?php echo isset($errors['revisions']) ? 'input-error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($revisions ?? $_SESSION['service_creation']['revisions'] ?? ''); ?>" 
                       min="0" max="999" required>
                <small class="form-hint">0-999 </small>
                <?php if (isset($errors['revisions'])): ?>
                    <span class="form-error"><?php echo $errors['revisions']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="price" class="form-label">Price (USD) *</label>
                <input type="number" id="price" name="price" class="form-input <?php echo isset($errors['price']) ? 'input-error' : ''; ?>" 
                       value="<?php echo htmlspecialchars($price ?? $_SESSION['service_creation']['price'] ?? ''); ?>" 
                       min="5" max="10000" step="1" required>
                <small class="form-hint">$5 - $10,000</small>
                <?php if (isset($errors['price'])): ?>
                    <span class="form-error"><?php echo $errors['price']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary btn-full-width">Continue to Images</button>
                <a href="my-services.php" class="btn-secondary btn-full-width">Cancel</a>
            </div>
        </form>
        
    <?php elseif ($step === 2): ?>          <!-- STEP 2 FORM: Upload Images -->

        <h1 class="heading-primary">Create New Service</h1>
        <h2 class="heading-secondary">Step 2: Upload Images</h2>
        
        <form method="POST" action="create-service.php?step=2" enctype="multipart/form-data" class="form">
            
            <div class="form-group">
                <label for="image_1" class="form-label">Service Image 1 (required) *</label>
                <input type="file" id="image_1" name="image_1" class="form-input <?php echo isset($errors['image_1']) ? 'input-error' : ''; ?>" 
                       accept="image/jpeg,image/jpg,image/png" required>
                <small class="form-hint">JPG, PNG only. Max 5MB. Min 800x600px</small>
                <?php if (isset($errors['image_1'])): ?>
                    <span class="form-error"><?php echo $errors['image_1']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="image_2" class="form-label">Service Image 2 (optional)</label>
                <input type="file" id="image_2" name="image_2" class="form-input <?php echo isset($errors['image_2']) ? 'input-error' : ''; ?>" 
                       accept="image/jpeg,image/jpg,image/png">
                <?php if (isset($errors['image_2'])): ?>
                    <span class="form-error"><?php echo $errors['image_2']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="image_3" class="form-label">Service Image 3 (optional)</label>
                <input type="file" id="image_3" name="image_3" class="form-input <?php echo isset($errors['image_3']) ? 'input-error' : ''; ?>" 
                       accept="image/jpeg,image/jpg,image/png">
                <?php if (isset($errors['image_3'])): ?>
                    <span class="form-error"><?php echo $errors['image_3']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="form-label">Main Image *</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="main_image" value="1" checked>
                        <span>Image 1</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="main_image" value="2">
                        <span>Image 2</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="main_image" value="3">
                        <span>Image 3</span>
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary btn-full-width">Continue to Review</button>
                <a href="create-service.php?step=1" class="btn-secondary btn-full-width">Back</a>
            </div>
        </form>
        
    <?php else: ?>     <!-- STEP 3: Review & Confirm -->
       
        <?php $service_data = $_SESSION['service_creation']; ?>
        
        <h1 class="heading-primary">Create New Service</h1>
        <h2 class="heading-secondary">Step 3: Review & Confirm</h2>
        
        <div class="review-section">
            <h3>Service Details</h3>
            <p><strong>Title:</strong> <?php echo htmlspecialchars($service_data['title']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($service_data['category']); ?></p>
            <p><strong>Subcategory:</strong> <?php echo htmlspecialchars($service_data['subcategory']); ?></p>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($service_data['description'])); ?></p>
            <p><strong>Delivery Time:</strong> <?php echo $service_data['delivery_time']; ?> days</p>
            <p><strong>Revisions:</strong> <?php echo $service_data['revisions']; ?></p>
            <p><strong>Price:</strong> $<?php echo number_format($service_data['price'], 2); ?></p>
        </div>
        
        <div class="review-section"> 
            <h3>Service Images</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php foreach ($service_data['images'] as $key => $image_path): ?>  
                        <div style="position: relative;">
                            <img src="<?php echo htmlspecialchars($image_path); ?>" style="width: 200px; height: 150px; object-fit: cover; border-radius: 4px; border: 2px solid #DEE2E6;">
                            <?php $index = str_replace('image_', '', $key); ?>
                            <?php if ($index == $service_data['main_image']): ?>
                                <span style="position: absolute; top: 5px; left: 5px; background: #FFD700; color: #000; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">★ Main</span>
                            <?php endif; ?>
                        </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <form method="POST" action="create-service.php?step=3" class="form">
            <div class="form-actions">
                <button type="submit" name="confirm" value="1" class="btn-primary btn-full-width">Confirm Service</button>
                <a href="create-service.php?step=2" class="btn-secondary btn-full-width">Back </a>
            </div>
        </form>
        
    <?php endif; ?>
    
</div>

<?php require_once 'includes/footer.php'; ?>