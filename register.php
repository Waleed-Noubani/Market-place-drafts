<?php
 // Use Case 1: User Registration

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Create Account';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $role = $_POST['role'] ?? '';
    $bio = sanitize_input($_POST['bio'] ?? '');
    $age_verification = isset($_POST['age_verification']);
    
    ///////////////////////////////  Validation ///////////////////////////////////

    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required';
    } elseif (strlen($first_name) < 2 || strlen(string: $first_name) > 50) {
        $errors['first_name'] = 'First name must be between 2 and 50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $errors['first_name'] = 'First name can only contain letters and spaces';
    }
    

    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required';
    } elseif (strlen($last_name) < 2 || strlen($last_name) > 50) {
        $errors['last_name'] = 'Last name must be between 2 and 50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $errors['last_name'] = 'Last name can only contain letters and spaces';
    }
    

    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email already exists';
        }
    }
    

    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (!validate_password($password)) {
        $errors['password'] = 'Password must be at least 8 characters with 1 uppercase, 1 lowercase, 1 number, and 1 special character';
    }
    

    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    

    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!validate_phone($phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits';
    }
    

    if (empty($country)) {
        $errors['country'] = 'Country is required';
    }
    

    if (empty($city)) {
        $errors['city'] = 'City is required';
    }
    

    if (empty($role)) {
        $errors['role'] = 'Account type is required';
    } 
    
    // Validation - Bio (Required for Freelancers)
    if ($role === 'Freelancer') {
        if (empty($bio)) {
            $errors['bio'] = 'Bio is required for freelancers';
        } elseif (strlen($bio) < 5 || strlen($bio) > 500) {
            $errors['bio'] = 'Bio must be between 5 and 500 characters';
        }
    }
    
    if (!$age_verification) {
        $errors['age_verification'] = 'You must confirm that you are 18+ years old';
    }
    
    if (empty($errors)) {    
        // Generate unique User ID
        $user_id = generate_unique_id($pdo, 'users', 'user_id');
        
        //  Hash password
         $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    user_id,
                    first_name,
                    last_name,
                    email,
                    password,
                    phone,
                    country,
                    city,
                    role,
                    bio,
                    status,
                    registration_date
                ) VALUES (
                    :user_id,
                    :first_name,
                    :last_name,
                    :email,
                    :password,
                    :phone,
                    :country,
                    :city,
                    :role,
                    :bio,
                    'Active',
                    NOW()
                )
            ");
            
            $stmt->execute([
                'user_id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password' => $hashed_password,
                'phone' => $phone,
                'country' => $country,
                'city' => $city,
                'role' => $role,
                'bio' => ($role === 'Freelancer' ? $bio : null)
            ]);
            
            redirect_with_message('login.php', 'Account created successfully! Please login.', 'success');
            
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container" style="max-width: 600px; margin: 0 auto;">
    
    <h1 class="heading-primary">Create Your Account</h1>
    
    <!-- Display general errors -->
    <?php if (!empty($errors['general'])): ?>
        <div class="message-error">
            <?php echo htmlspecialchars($errors['general']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="register.php" class="form">
        
        <!-- Personal Information Section -->
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
                    value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                    required
                >
                <?php if (isset($errors['first_name'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['first_name']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Last Name -->
            <div class="form-group">
                <label for="last_name" class="form-label">Last Name *</label>
                <input 
                    type="text" 
                    id="last_name" 
                    name="last_name" 
                    class="form-input <?php echo isset($errors['last_name']) ? 'input-error' : ''; ?>"
                    value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                    required
                >
                <?php if (isset($errors['last_name'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['last_name']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Email -->
            <div class="form-group">
                <label for="email" class="form-label">Email *</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input <?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                    value="<?php echo htmlspecialchars($email ?? ''); ?>"
                    placeholder="example@email.com"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Phone -->
            <div class="form-group">
                <label for="phone" class="form-label">Phone Number *</label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    class="form-input <?php echo isset($errors['phone']) ? 'input-error' : ''; ?>"
                    value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                    placeholder="0599123456"
                    maxlength="10"
                    required
                >
                <?php if (isset($errors['phone'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['phone']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Country -->
            <div class="form-group">
                <label for="country" class="form-label">Country *</label>
                <select 
                    id="country" 
                    name="country" 
                    class="form-input <?php echo isset($errors['country']) ? 'input-error' : ''; ?>"
                    required
                >
                    <option value="">Select Country</option>             <!-- <option selected > Palestine </option> -->
                    <option value="Palestine" >Palestine</option>
                    <option value="Jordan" >Jordan</option>
                    <option value="Egypt">Egypt</option>
                    <option value="Lebanon" >Lebanon</option>
                    <option value="Syria" >Syria</option>
                    <option value="Saudi Arabia" >Saudi Arabia</option>
                    <option value="UAE" >UAE</option>
                    <option value="Other" >Other</option>
                </select>
                <?php if (isset($errors['country'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['country']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- City -->
            <div class="form-group">
                <label for="city" class="form-label">City *</label>
                <input 
                    type="text" 
                    id="city" 
                    name="city" 
                    class="form-input <?php echo isset($errors['city']) ? 'input-error' : ''; ?>"
                    value="<?php echo htmlspecialchars($city ?? ''); ?>"
                    required
                >
                <?php if (isset($errors['city'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['city']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Account Security  -->
        <div class="form-section">
            <h2 class="heading-secondary">Account Security</h2>
            
            <!-- Password -->
            <div class="form-group">
                <label for="password" class="form-label">Password *</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input <?php echo isset($errors['password']) ? 'input-error' : ''; ?>"
                    required
                >
                <small class="form-hint">Min 8 characters: 1 uppercase, 1 lowercase, 1 number, 1 special character</small>
                <?php if (isset($errors['password'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Confirm Password -->
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    class="form-input <?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>"
                    required
                >
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Account Type  -->
        <div class="form-section">
            <h2 class="heading-secondary">Account Type</h2>
            
            <div class="form-group">
                <label class="form-label">I want to *</label>
                
                <div class="radio-group">
                    <label class="radio-label">
                        <input 
                            type="radio" 
                            name="role" 
                            value="Client"
                            <?php echo ($role ?? '') === 'Client' ? 'checked' : ''; ?>
                            required
                        >
                        <span>Client</span>
                    </label>
                    
                    <label class="radio-label">
                        <input 
                            type="radio" 
                            name="role" 
                            value="Freelancer"
                            <?php echo ($role ?? '') === 'Freelancer' ? 'checked' : ''; ?>
                            required
                        >
                        <span>Freelancer</span>
                    </label>
                </div>
                
                <?php if (isset($errors['role'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['role']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Bio ( only required for Freelancers) -->
            <div class="form-group">
                <label for="bio" class="form-label"> Bio/About requird only required for Freelancer </label>
                <textarea 
                    id="bio" 
                    name="bio" 
                    class="form-input <?php echo isset($errors['bio']) ? 'input-error' : ''; ?>"
                    rows="4"
                    maxlength="500"
                    placeholder="Tell us about yourself and your skills..."
                ><?php echo htmlspecialchars($bio ?? ''); ?></textarea>
                <small class="form-hint">  Required for freelancers (5-500 characters)</small>
                
                <?php if (isset($errors['bio'])): ?>
                    <span class="form-error"><?php echo htmlspecialchars($errors['bio']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Age Verification -->
        <div class="form-group">
            <label class="checkbox-label">
                <input 
                    type="checkbox" 
                    name="age_verification"
                    <?php echo $age_verification ?? false ? 'checked' : ''; ?>
                    required
                >
                <span>I am 18+ years old *</span>
            </label>

            <?php if (isset($errors['age_verification'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['age_verification']); ?></span>
            <?php endif; ?>
        </div>
        
        <!-- Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn-primary btn-full-width">Create Account</button>
            <a href="index.php" class="btn-secondary btn-full-width">Cancel</a>
        </div>
        
    </form>
    
    <div class="form-footer">
        <p class="text-center">
            Already have an account? 
            <a href="login.php" style="color: #007BFF; font-weight: 600;">Login</a>
        </p>
    </div>
    
</div>

<?php require_once 'includes/footer.php'; ?>