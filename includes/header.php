<?php
require_once 'functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">        <!--  في أي صفحة قبل استدعاء هذا الملف) :أين يتم تعريف )$page_title فعليًا؟-->
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Freelance Marketplace</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    
<header class="header">
    <div class="header-container">
        
        <div class="header-logo">
            <a href="index.php">
                <h1>Freelance Marketplace</h1>
            </a>
        </div>
        
        <!-- Search Bar -->
        <div class="header-search">
            <form action="index.php" method="GET" class="search-form">
                <input type="text"  name="search"  placeholder="Search services..."  class="search-input"
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" >
                <button type="submit" class="search-button">Search</button>
            </form>
        </div>
        
        <!--بدنا نتحكم باقصى اليمين شو تكون بالهيدر في حال كان ضيف او عميل او فريلانسر وكل حد بظهرله شو بناسبه  -->
        <div class="header-auth">
            <?php if (isset($_SESSION['user_id'])): ?>
                
                <?php if ($_SESSION['role'] === 'Client'): ?> <!-- Shopping Cart Icon (Clients Only) بتظهر بس للكلاينت السلة -->
                    <a href="cart.php" class="cart-icon">
                        <span class="cart-symbol">🛒</span>
                            <span class="cart-badge"><?php echo get_cart_count(); ?></span>
                    </a>
                <?php endif; ?>
                
                <!-- User Profile image and name  -->
                <a href="profile.php" class="profile-card profile-card-<?php echo strtolower($_SESSION['role']); ?>"> <!-- تحويل النص إلى أحرف صغيرة (lowercase) -->
                    <?php if (!empty($_SESSION['profile_photo']) && file_exists($_SESSION['profile_photo'])): ?> <!-- if image exists -->
                        <img src="<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" 
                             alt="Profile" 
                             class="profile-photo">

                    <?php else: ?> <!-- لو فش صورة ؟ -->
                        <div class="profile-photo profile-photo-default">
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?> <!-- اذا الصورة مش موجودة اظهر اول حرف من اسمه -->
                        </div>
                    <?php endif; ?>

                    <span class="profile-name"> 
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </span>
                </a>
                
                <!-- Logout  -->
                <a href="logout.php" class="logout-link">Logout</a>
                
            <?php else: ?> <!-- لو مش مسجل دخول -->
                <a href="login.php" class="btn-secondary">Login</a>
                <a href="register.php" class="btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
        
    </div>
</header>

<!-- Main Layout Container -->
<div class="page-layout"> 
    <?php include 'navigation.php'; ?>  
    <main class="main-content">
        <?php echo get_flash_message();?> 