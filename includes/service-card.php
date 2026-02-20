<?php
if (!isset($service)) {
    return;
}
?>
<!-- Image
     Title
     freelancer-name
     Category
     Price  -->

<div class="service-card">
    
    <!-- Image -->
    <a href="service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>" class="service-image-link">
        <?php if (!empty($service['image_1']) && file_exists($service['image_1'])): ?>
            <img 
                src="<?php echo htmlspecialchars($service['image_1']); ?>" 
                alt="<?php echo htmlspecialchars($service['title']); ?>"
                class="service-image"
            >
        <?php else: ?>
            <div class="service-image-placeholder">
                <span>📷</span>
                <p>No Image</p>
            </div>
        <?php endif; ?>
    </a>
    
    <!-- Featured  -->
    <?php if ($service['featured_status'] === 'Yes'): ?>
        <span class="badge-featured">Featured</span>
    <?php endif; ?>
    
    <div class="service-card-content">
        
        <!-- Service Title -->
        <h3 class="service-card-title">
            <a href="service-detail.php?id=<?php echo htmlspecialchars($service['service_id']); ?>">
                <?php echo htmlspecialchars($service['title']); ?>
            </a>
        </h3>
        
        <!-- Freelancer Info -->
        <div class="service-card-freelancer">
            <?php if (!empty($service['profile_photo']) && file_exists($service['profile_photo'])): ?>
                <img 
                    src="<?php echo htmlspecialchars($service['profile_photo']); ?>" 
                    alt="<?php echo htmlspecialchars($service['freelancer_name']); ?>"
                    class="freelancer-photo-small"
                >
            <?php else: ?>   
            <span class="freelancer-name"><?php echo htmlspecialchars($service['freelancer_name']); ?></span>
            <?php endif; ?>

        </div>
        
        <!-- Category -->
        <p class="service-card-category">
            <?php echo htmlspecialchars($service['category']); ?>
        </p>
        
        <!-- Price -->
        <p class="service-card-price">
            Starting at <?php echo format_price($service['price']); ?>
        </p>
        
    </div>
    
</div>