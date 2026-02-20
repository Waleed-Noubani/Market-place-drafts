<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']); // بتجيبلي اسم الصفحة
// عشان اذا انا ضغطت على لينك معين بدي يكون اله ستايل واضح يبينلي انه هو الصفحة الشغالة
?>

<nav class="navigation">
    <ul class="nav-list">
        
        <?php if (!isset($_SESSION['user_id'])): ?>              <!-- Guest Navigation --> 
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo ($current_page === 'index.php') ? 'nav-link-active' : ''; ?>"> Home </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="nav-link">Browse Services</a>
            </li>
            <li class="nav-item">
                <a href="login.php" class="nav-link <?php echo ($current_page === 'login.php') ? 'nav-link-active' : ''; ?>"> Login</a>
            </li>
            <li class="nav-item">
                <a href="register.php" class="nav-link <?php echo ($current_page === 'register.php') ? 'nav-link-active' : ''; ?>"> Sign Up </a>
            </li> 
            
        <?php elseif ($_SESSION['role'] === 'Client'): ?>        <!-- Client Navigation -->
                <a href="index.php" class="nav-link nav-link-client <?php echo ($current_page === 'index.php') ? 'nav-link-active' : ''; ?>">Home </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="nav-link nav-link-client"> Browse Services </a>
            </li>
            <li class="nav-item">
                <a href="cart.php" class="nav-link nav-link-client <?php echo ($current_page === 'cart.php') ? 'nav-link-active' : ''; ?>">Shopping Cart
                        <span class="badge-warning"><?php echo get_cart_count(); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my-orders.php" class="nav-link nav-link-client <?php echo ($current_page === 'my-orders.php') ? 'nav-link-active' : ''; ?>">
                    My Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link nav-link-client <?php echo ($current_page === 'profile.php') ? 'nav-link-active' : ''; ?>">
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link nav-link-client">
                    Logout
                </a>
            </li>
            
        <?php else: ?>                                 <!-- Freelancer Navigation -->        
            <li class="nav-item">
                <a href="index.php" class="nav-link nav-link-freelancer <?php echo ($current_page === 'index.php') ? 'nav-link-active' : ''; ?>">
                    Home
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="nav-link nav-link-freelancer">
                    Browse Services
                </a>
            </li>
            <li class="nav-item">
                <a href="my-services.php" class="nav-link nav-link-freelancer <?php echo ($current_page === 'my-services.php') ? 'nav-link-active' : ''; ?>">
                    My Services
                </a>
            </li>
            <li class="nav-item">
                <a href="my-orders.php" class="nav-link nav-link-freelancer <?php echo ($current_page === 'my-orders.php') ? 'nav-link-active' : ''; ?>">
                    My Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link nav-link-freelancer <?php echo ($current_page === 'profile.php') ? 'nav-link-active' : ''; ?>">
                    My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link nav-link-freelancer">
                    Logout
                </a>
            </li>
            
        <?php endif; ?>
        
    </ul>
        
</nav>
