<?php

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';
require_login();
$page_title = 'My Profile';

$errors = [];
$success = false;

// Fetch current user data
$stmt = $pdo->prepare("
    SELECT user_id,first_name,last_name,email,phone,country,city,role,profile_photo,registration_date,professional_title, bio, skills,years_experience
    FROM users 
    WHERE user_id = :user_id
");

$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();


if ($user['role'] === 'Freelancer') {  // Get statistics
    $stats = get_freelancer_stats($pdo, $_SESSION['user_id']);
}

// Handle formmmm
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    
    // Password fields 
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Professional fields (freelancers only)
    if ($user['role'] === 'Freelancer') {
        $professional_title = sanitize_input($_POST['professional_title'] ?? '');
        $bio = sanitize_input($_POST['bio'] ?? '');
        $skills = sanitize_input($_POST['skills'] ?? '');
        $years_experience = sanitize_input($_POST['years_experience'] ?? '');
    }
    
    ///////////////////////////////////////// Validation //////////////////////////////////////////////

    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required';
    } elseif (strlen($first_name) < 2 || strlen($first_name) > 50) {
        $errors['first_name'] = 'First name must be 2-50 characters';
    }
    
    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required';
    } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
        $errors['last_name'] = 'Last name must be 2-50 characters';
    }
    
    // Email validation
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors['email'] = 'Invalid email format';
    } elseif ($email !== $user['email']) {
        $email_check = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
        $email_check->execute(['email' => $email, 'user_id' => $_SESSION['user_id']]);
        if ($email_check->fetch()) {
            $errors['email'] = 'Email already in use';
        }
    }
    
    // Phone validation
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!validate_phone($phone)) {
        $errors['phone'] = 'Phone must be exactly 10 digits';
    }
    
    // Country & City
    if (empty($country)) {
        $errors['country'] = 'Country is required';
    }
    
    if (empty($city)) {
        $errors['city'] = 'City is required';
    } 
    
    //////////////////////////////////// Password validation //////////////////////////////////////////

    $password_changing = !empty($new_password) || !empty($confirm_password) || !empty($current_password); //true   ||
    
    if ($password_changing) { 
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password required to change password';
        } else {
            // Verify current password
            $pwd_stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = :user_id");
            $pwd_stmt->execute(['user_id' => $_SESSION['user_id']]);
            $stored_password = $pwd_stmt->fetchColumn();
            
            if (!password_verify($current_password, $stored_password)) {  // بفحص اذا فيه باسوورد مطابق للي دخله
                $errors['current_password'] = 'Current password is incorrect';
            }
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required';
        } elseif (!validate_password($new_password)) {
            $errors['new_password'] = 'Password must be min 8 chars with 1 upper, 1 lower, 1 number, 1 special char';
        }
        
        if (empty($confirm_password)) {
            $errors['confirm_password'] = 'Please confirm new password';
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }
    
    // Profile photo validation
    $new_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $validation = validate_file_upload($_FILES['profile_photo'], $allowed_types, $max_size);
        
        if (!$validation['success']) {
            $errors['profile_photo'] = $validation['error'];
        } else {

            $image_info = getimagesize($_FILES['profile_photo']['tmp_name']);
            if ($image_info[0] < 300 || $image_info[1] < 300) {
                $errors['profile_photo'] = 'Image must be at least 300x300 pixels';
            } else {
                $user_dir = "uploads/profiles/{$_SESSION['user_id']}";
                if (!is_dir($user_dir)) {
                    mkdir($user_dir, 0777, true);
                }
                
                // Delete old photo 
                if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                }
                
                // Save new photo
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION); // .png
                $new_photo_path = "$user_dir/profile_photo.$ext";
                
                if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $new_photo_path)) {
                    $errors['profile_photo'] = 'Failed to upload photo';
                    $new_photo_path = null;
                }
            }
        }
    }
    
    // Professional info validation (Freelancers only)
    if ($user['role'] === 'Freelancer') {
        if (empty($professional_title)) {
            $errors['professional_title'] = 'Professional title is required';
        } elseif (strlen($professional_title) < 5 || strlen($professional_title) > 100) {
            $errors['professional_title'] = 'Professional title must be 5-100 characters';
        }
        
        if (empty($bio)) {
            $errors['bio'] = 'Bio is required';
        } 
        
        if (!empty($skills) && strlen($skills) > 200) {
            $errors['skills'] = 'Skills must not exceed 200 characters';
        }
        
        if (!empty($years_experience)) {
            if (!is_numeric($years_experience) || $years_experience < 0 || $years_experience > 50) {
                $errors['years_experience'] = 'Years of experience must be 0-50';
            }
        }
    }
    
    // If no errors  update database
    if (empty($errors)) {
        
        try {
            $pdo->beginTransaction();
            
            // Update basic info
            $update_query = "UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                country = :country,
                city = :city,
                professional_title = :professional_title,
                bio = :bio,
                skills = :skills,
                years_experience = :years_experience";
            
            $update_params = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'country' => $country,
                'city' => $city,
                'user_id' => $_SESSION['user_id'],
                'professional_title' => $professional_title ,
                'bio' => $bio ,
                'skills' => $skills ,
                'years_experience' => $years_experience
            ];
            
            // Add photo if uploaded
            if ($new_photo_path) {
                $update_query .= ", profile_photo = :profile_photo";
                $update_params['profile_photo'] = $new_photo_path;
            }
            
            // Add password if changing
            if ($password_changing && !isset($errors['current_password']) && !isset($errors['new_password'])  && !isset($errors['confirm_password'])) {
                $update_query .= ", password = :password";
                $update_params['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $update_query .= " WHERE user_id = :user_id";

            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);
            
            $pdo->commit();
            
            // Update session 
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            if ($new_photo_path) {
                $_SESSION['profile_photo'] = $new_photo_path;
            }
            
            $success = true;
            header('Location: profile.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}

if (isset($_GET['success'])) {
    $success = true;
}

require_once 'includes/header.php';
?>

<!-- Success Message -->
<?php if ($success): ?>
    <div class="message-success">   Profile updated successfully!   </div>
<?php endif; ?>

<!-- General Error -->
<?php if (isset($errors['general'])): ?>
    <div class="message-error">      <?php echo htmlspecialchars($errors['general']); ?>     </div>
<?php endif; ?>

<div class="profile-layout">
    
    <!--------------------------------------------------- Left Column ------------------------------------------------------------->
    <div class="profile-left">
        
        <!-------------------------------- Profile Card ------------------->
        <div class="profile-card-large">
            
            <div class="profile-photo-container">
                <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                         alt="Profile Photo"   class="profile-photo-large">
                <?php else: ?>
                    <div class="profile-photo-large profile-photo-placeholder">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-card-info">
                <h2 class="profile-card-name">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </h2>
                
                <p class="profile-card-email"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <span class="role-badge role-badge-<?php echo strtolower($user['role']); ?>">
                    <?php echo htmlspecialchars($user['role']); ?>
                </span>
                
                <p class="profile-card-member">Member since <?php echo date('M Y', strtotime($user['registration_date'])); ?></p>
            </div>
            
        </div>
        
        <!-- Statistics Card (Freelancers Only) -->
        <?php if ($user['role'] === 'Freelancer'): ?>
            <div class="profile-stats-card">
                <h3>Statistics</h3>
                <div class="stats-grid">
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_services']; ?></div>
                        <div class="stat-label">Total Services</div>
                    </div>
                    
                    <div class="stat-item stat-item-active">
                        <div class="stat-number"><?php echo $stats['active_services']; ?></div>
                        <div class="stat-label">Active Services</div>
                    </div>
                    
                    <div class="stat-item stat-item-featured">
                        <div class="stat-number"><?php echo $stats['featured_services']; ?>/3</div>
                        <div class="stat-label">Featured Services</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    
                </div>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!------------------------------------------------------------ Right Column ---------------------------------------------------->
    <div class="profile-right">
        
        <h1 class="heading-primary">Edit Profile</h1>
        
        <form method="POST" enctype="multipart/form-data" class="form">
            
            <!----------------------- Account Information ------------------------->
            <div class="form-section">
                <h2 class="heading-secondary">Account Information</h2>
                
                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input  type="email"  id="email" name="email" 
                        class="form-input <?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                        value="<?php echo htmlspecialchars($email ?? $user['email']); ?>"
                        required
                    >
                    <?php if (isset($errors['email'])): ?>
                        <span class="form-error"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Current Password -->
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input  type="password"  id="current_password"  name="current_password" 
                        class="form-input <?php echo isset($errors['current_password']) ? 'input-error' : ''; ?>"
                        placeholder="Required if changing password"
                    >
                    <span class="form-hint">Only required if you want to change your password</span>
                    <?php if (isset($errors['current_password'])): ?>
                        <span class="form-error"><?php echo $errors['current_password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- New Password -->
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input  type="password"  id="new_password"  name="new_password" 
                        class="form-input <?php echo isset($errors['new_password']) ? 'input-error' : ''; ?>"
                    >
                    <span class="form-hint">Min 8 chars: 1 upper, 1 lower, 1 number, 1 special char</span>
                    <?php if (isset($errors['new_password'])): ?>
                        <span class="form-error"><?php echo $errors['new_password']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input  type="password"  id="confirm_password"  name="confirm_password" 
                        class="form-input <?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>"
                    >
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="form-error"><?php echo $errors['confirm_password']; ?></span>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <!----------------------------- Personal Information--------------------- -->
            <div class="form-section">
                <h2 class="heading-secondary">Personal Information</h2>
                
                <!-- First Name -->
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name *</label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        class="form-input <?php echo isset($errors['first_name']) ? 'input-error' : ''; ?>"
                        value="<?php echo htmlspecialchars($first_name ?? $user['first_name']); ?>"
                        required
                    >
                    <?php if (isset($errors['first_name'])): ?>
                        <span class="form-error"><?php echo $errors['first_name']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Last Name -->
                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input  type="text" id="last_name" name="last_name" 
                        class="form-input <?php echo isset($errors['last_name']) ? 'input-error' : ''; ?>"
                        value="<?php echo htmlspecialchars($last_name ?? $user['last_name']); ?>"
                        required
                    >
                    <?php if (isset($errors['last_name'])): ?>
                        <span class="form-error"><?php echo $errors['last_name']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Phone -->
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input  type="text" id="phone" name="phone" 
                        class="form-input <?php echo isset($errors['phone']) ? 'input-error' : ''; ?>"
                        value="<?php echo htmlspecialchars($phone ?? $user['phone']); ?>"
                        placeholder="10 digits"  maxlength="10"
                        required
                    >
                    <?php if (isset($errors['phone'])): ?>
                        <span class="form-error"><?php echo $errors['phone']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Country -->
                <div class="form-group">
                    <label for="country" class="form-label">Country *</label>
                    <select  id="country"  name="country" 
                        class="form-select <?php echo isset($errors['country']) ? 'input-error' : ''; ?>"
                        required
                    >
                        <option value="">Select Country</option>             <!-- <option selected > Palestine </option> -->
                        <option value="Palestine" selected>Palestine </option>
                        <option value="Jordan" >Jordan</option>
                        <option value="Egypt">Egypt</option>
                        <option value="Lebanon" >Lebanon</option>
                        <option value="Syria" >Syria</option>
                        <option value="Saudi Arabia" >Saudi Arabia</option>
                        <option value="UAE" >UAE</option>
                        <option value="Other" >Other</option>
                    </select>
                    <?php if (isset($errors['country'])): ?>
                        <span class="form-error"><?php echo $errors['country']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- City -->
                <div class="form-group">
                    <label for="city" class="form-label">City *</label>
                    <input type="text" id="city" name="city" 
                        class="form-input <?php echo isset($errors['city']) ? 'input-error' : ''; ?>"
                        value="<?php echo htmlspecialchars($city ?? $user['city']); ?>"
                        required
                    >
                    <?php if (isset($errors['city'])): ?>
                        <span class="form-error"><?php echo $errors['city']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Photo -->
                <div class="form-group">
                    <label for="profile_photo" class="form-label">Profile Photo</label>
                    <input  type="file"  id="profile_photo" name="profile_photo" 
                        class="form-input <?php echo isset($errors['profile_photo']) ? 'input-error' : ''; ?>"
                        accept=".jpg,.jpeg,.png"
                    >
                    <span class="form-hint">JPG, JPEG, or PNG. Max 2MB. Min 300x300px</span>
                    <?php if (isset($errors['profile_photo'])): ?>
                        <span class="form-error"><?php echo $errors['profile_photo']; ?></span>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <!-- Professional Information (Freelancers Only) -->
            <?php if ($user['role'] === 'Freelancer'): ?>
                <div class="form-section">
                    <h2 class="heading-secondary">Professional Information</h2>
                    
                    <!-- Professional Title -->
                    <div class="form-group">
                        <label for="professional_title" class="form-label">Professional Title *</label>
                        <input type="text" id="professional_title"  name="professional_title" 
                            class="form-input <?php echo isset($errors['professional_title']) ? 'input-error' : ''; ?>"
                            value="<?php echo htmlspecialchars($professional_title ?? $user['professional_title'] ?? ''); ?>"
                            placeholder="e.g., Full Stack Web Developer"
                            required
                        >
                        <span class="form-hint">10-100 characters</span>
                        <?php if (isset($errors['professional_title'])): ?>
                            <span class="form-error"><?php echo $errors['professional_title']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Bio -->
                    <div class="form-group">
                        <label for="bio" class="form-label">Bio/Description *</label>
                        <textarea id="bio" name="bio" class="form-textarea <?php echo isset($errors['bio']) ? 'input-error' : ''; ?>"
                            rows="5"required > <?php echo htmlspecialchars($bio ?? $user['bio'] ?? ''); ?></textarea>
                        <span class="form-hint">50-500 characters </span>
                        <?php if (isset($errors['bio'])): ?>
                            <span class="form-error"><?php echo $errors['bio']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Skills -->
                    <div class="form-group">
                        <label for="skills" class="form-label">Skills (Optional)</label>
                        <input type="text" id="skills" name="skills" 
                            class="form-input <?php echo isset($errors['skills']) ? 'input-error' : ''; ?>"
                            value="<?php echo htmlspecialchars($skills ?? $user['skills'] ?? ''); ?>"
                            placeholder="e.g., PHP, JavaScript, MySQL, React"
                        >
                        <span class="form-hint">Comma-separated, max 200 characters</span>
                        <?php if (isset($errors['skills'])): ?>
                            <span class="form-error"><?php echo $errors['skills']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Years of Experience -->
                    <div class="form-group">
                        <label for="years_experience" class="form-label">Years of Experience (Optional)</label>
                        <input type="number" id="years_experience" name="years_experience" 
                            class="form-input <?php echo isset($errors['years_experience']) ? 'input-error' : ''; ?>"
                            value="<?php echo htmlspecialchars($years_experience ?? $user['years_experience'] ?? ''); ?>"
                            min="0"  max="50"  placeholder="0-50"
                        >
                        <?php if (isset($errors['years_experience'])): ?>
                            <span class="form-error"><?php echo $errors['years_experience']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                </div>
            <?php endif; ?>
            
            <!-- Button Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-primary btn-full-width">Save Changes</button>
                <a href="index.php" class="btn-secondary btn-full-width">Cancel</a>
            </div>
            
        </form>
        
    </div>
    
</div>

<?php require_once 'includes/footer.php'; ?>