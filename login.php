<?php
// Use Case 2: User Login

session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Login';
$errors = [];

// Handle form submission &&  Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!validate_email($email)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }


    
    if (empty($errors)) {
        
        // أول مرة يفتح الصفحة نخزن 
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempt_time'] = time();  // time() في PHP ترجع الوقت الحالي بوحدة الثواني
        }
        
        // إعادة التصفير بعد 30 دقيقة
        if (time() - $_SESSION['login_attempt_time'] > 1800) {  // الفرق بين الوقت الحالي مع اخر محاولة
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_attempt_time'] = time();
        }
        
        // Check if account is locked
        if ($_SESSION['login_attempts'] >= 5) {   // العبرة هان لو صار عندي اكثر من 5 محاولات ما رح نفوت على الكيسز الثانيةالي بتفحص تسجيل الدخول
            $remaining = 1800 - (time() - $_SESSION['login_attempt_time']);   
            $minutes = ceil($remaining / 60);                           //  حساب الوقت المتبقي لفك القفل

            $errors['general'] = "Account locked. Please try again after $minutes minutes.";

        } else {           
            $stmt = $pdo->prepare("
                SELECT 
                    user_id,
                    first_name,
                    last_name,
                    email,
                    password,
                    role,
                    status,
                    profile_photo
                FROM users 
                WHERE email = :email
            ");
            
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

           if ($user && password_verify($password, $user['password'])) {
           //if ($user && $password === $user['password']) {
                
                // Check status
                if ($user['status'] !== 'Active') {
                    $errors['general'] = 'Your account is inactive. Please contact support.';
                } else {
                    
                    // Successful login --> create session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_photo'] = $user['profile_photo'];
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;

                    // $redirect = ($user['role'] === 'Client') ? 'index.php' : 'my-services.php';
                    // header("Location: $redirect");
                    
                    // Redirect based on role
                    if (isset($_SESSION['redirect_after_login'])) {
                        $redirect = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: $redirect");
                    } else {
                    $redirect = ($user['role'] === 'Client') ? 'index.php' : 'my-services.php';
                    header("Location: $redirect");
                   }
                    exit;
                }
                
            } else {   // لو الباسوورد غلط
                
                $_SESSION['login_attempts']++; // بزيد المحاولات الفاشلة
                $remaining_attempts = 5 - $_SESSION['login_attempts'];
                
                if ($remaining_attempts > 0 && $_SESSION['login_attempts'] >= 3) {
                    $errors['general'] = "Invalid email or password. $remaining_attempts attempts remaining.";
                } else {
                    $errors['general'] = 'Invalid email or password';
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container" style="max-width: 400px; margin: 0 auto;">
    
    <h1 class="heading-primary">Login to Your Account</h1>
    
    <!--في حال هاي مش التجربة الاولى بطبع نتيجة فشل العملية السابقة -->
    <?php if (!empty($errors['general'])): ?>
        <div class="message-error">
            <?php echo htmlspecialchars($errors['general']); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="login.php" class="form">
        
        <div class="form-group">
            <label for="email" class="form-label">Email *</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-input <?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                required
            >
            <?php if (isset($errors['email'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['email']); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">Password *</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-input <?php echo isset($errors['password']) ? 'input-error' : ''; ?>"
                required >

            <?php if (isset($errors['password'])): ?>
                <span class="form-error"><?php echo htmlspecialchars($errors['password']); ?></span>
            <?php endif; ?>
        </div>
        
        <!-- Remember Me  -->
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="remember" disabled>
                <span class="text-muted">Remember Me </span>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary btn-full-width">Login</button>
        </div>
        
    </form>

    
    <!-- Additional Links -->
    <div class="form-footer">
        <p class="text-center">
            <a href="#" class="text-muted" style="text-decoration: underline;">Forgot password?</a>
            <span class="text-muted">(Coming Soon)</span>
        </p>
        <p class="text-center" style="margin-top: 15px;">
            Don't have an account? 
            <a href="register.php" style="color: #007BFF; font-weight: 600;">Sign up</a>
        </p>
    </div>
    
</div>

<?php require_once 'includes/footer.php'; ?>