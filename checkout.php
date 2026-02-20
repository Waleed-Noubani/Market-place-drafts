<?php
// Use Case 10: Checkout and Place Order
//  $_SESSION['checkout']['payment_data']
//$_SESSION['checkout']['services_data']
// $_SESSION['checkout']['step']

require_once 'classes/Service.php';
session_start();
require_once 'config/db.inc.php';
require_once 'includes/functions.php';

require_client();

$page_title = 'Checkout';

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    redirect_with_message('cart.php', 'Your cart is empty', 'error');
}

// Validate all services in cart are still active
foreach ($_SESSION['cart'] as  $service) {
    $stmt = $pdo->prepare("SELECT status FROM services WHERE service_id = :id");
    $stmt->execute(['id' => $service->getServiceId()]);
    $service_data = $stmt->fetch();
}

// مهمممممممممممممممممممممممممممممم
if (!isset($_SESSION['checkout'])) {
    $_SESSION['checkout'] = [
        'step' => 1,
        'services_data' => [],
        'payment_data' => []
    ];
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : $_SESSION['checkout']['step'];
$errors = [];

$services = $_SESSION['cart'];
$subtotal = calculate_cart_subtotal();
$service_fee = calculate_service_fee($subtotal);
$total = $subtotal + $service_fee;  // Calculate totals


////////////////////////////////////////////////////////////////////////////
//////////////////// STEP 1: SERVICE REQUIREMENTS //////////////////////
////////////////////////////////////////////////////////////////////////////
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $all_valid = true;
    $services_data = [];

    foreach ($services as $index => $service) {
        $service_id = $service->getServiceId();
        
        $requirements = sanitize_input($_POST["requirements_$index"] ?? '');
        $instructions = sanitize_input($_POST["instructions_$index"] ?? '');
        $deadline = sanitize_input($_POST["deadline_$index"] ?? '');
        
        $service_errors = [];
        
        
        if (empty($requirements)) {
            $service_errors['requirements'] = 'Service requirements are required';
            $all_valid = false;
        } elseif (strlen($requirements) < 50 || strlen($requirements) > 1000) {
            $service_errors['requirements'] = 'Requirements must be 50-1000 characters';
            $all_valid = false;
        }
        
        if (!empty($instructions) && strlen($instructions) > 500) {
            $service_errors['instructions'] = 'Instructions must not exceed 500 characters';
            $all_valid = false;
        }
        
        if (!empty($deadline)) {
            $min_date = date('Y-m-d', strtotime("+{$service->getDeliveryTime()} days"));
            if ($deadline < $min_date) {
                $service_errors['deadline'] = "Deadline must be at least {$service->getDeliveryTime()} days from today";
                $all_valid = false;
            }
        }
        
        $uploaded_files = [];
        if (isset($_FILES["files_$index"])) {
            $file_validation = validate_requirement_files($_FILES["files_$index"], 3);
            if (!$file_validation['success']) {
                $service_errors['files'] = $file_validation['error'];
                $all_valid = false;
            } else {
                $uploaded_files = $file_validation['files'];
            }
        }
        
        $services_data[$service_id] = [
            'requirements' => $requirements,
            'instructions' => $instructions,
            'deadline' => $deadline,
            'files' => $uploaded_files,
            'errors' => $service_errors
        ];
        
        if (!empty($service_errors)) {
            $errors["service_$index"] = $service_errors;
        }
    }

    if ($all_valid) {
        $_SESSION['checkout']['services_data'] = $services_data;
        $_SESSION['checkout']['step'] = 2;
        header('Location: checkout.php?step=2');
        exit;
    }
}

////////////////////////////////////////////////////////////////////////////
////////////////////// Step 2: Payment information ////////////////////////
////////////////////////////////////////////////////////////////////////////
if ($step === 2) {
    if (!isset($_SESSION['checkout']['services_data']) || empty($_SESSION['checkout']['services_data'])) {
        header('Location: checkout.php?step=1');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payment_method = sanitize_input($_POST['payment_method'] ?? '');
        $card_number = sanitize_input($_POST['card_number'] ?? '');
        $card_name = sanitize_input($_POST['card_name'] ?? '');
        $expiry_date = sanitize_input($_POST['expiry_date'] ?? '');
        $cvv = sanitize_input($_POST['cvv'] ?? '');
        
        $address_line1 = sanitize_input($_POST['address_line1'] ?? '');
        $address_line2 = sanitize_input($_POST['address_line2'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $postal_code = sanitize_input($_POST['postal_code'] ?? '');
        $country = sanitize_input($_POST['country'] ?? '');
        
        // Validation
        if (empty($payment_method)) {
            $errors['payment_method'] = 'Payment method is required';
        }
        
        if ($payment_method === 'Credit Card') {
            if (empty($card_number)) {
                $errors['card_number'] = 'Card number is required';
            } elseif (!validate_credit_card($card_number)) {
                $errors['card_number'] = 'Invalid card number (must be 16 digits)';
            }
            
            if (empty($card_name)) {
                $errors['card_name'] = 'Cardholder name is required';
            } elseif (!preg_match('/^[a-zA-Z\s]+$/', $card_name)) {
                $errors['card_name'] = 'Cardholder name can only contain letters and spaces';
            }
            
            if (empty($expiry_date)) {
                $errors['expiry_date'] = 'Expiration date is required';
            } elseif (!validate_expiry_date($expiry_date)) {
                $errors['expiry_date'] = 'Invalid expiration date (MM/YY format, must be future date)';
            }
            
            if (empty($cvv)) {
                $errors['cvv'] = 'CVV is required';
            } elseif (!preg_match('/^\d{3}$/', $cvv)) {
                $errors['cvv'] = 'CVV must be 3 digits';
            }
        }
        
        if (empty($address_line1)) {
            $errors['address_line1'] = 'Address is required';
        }
        
        if (empty($city)) {
            $errors['city'] = 'City is required';
        }
        
        if (empty($state)) {
            $errors['state'] = 'State/Province is required';
        }
        
        if (empty($postal_code)) {
            $errors['postal_code'] = 'Postal code is required';
        }
        
        if (empty($country)) {
            $errors['country'] = 'Country is required';
        }
        
        if (empty($errors)) {
            $_SESSION['checkout']['payment_data'] = [
                'payment_method' => $payment_method,
                'card_name' => $card_name,
                'card_last4' => substr($card_number, -4),
                'address_line1' => $address_line1,
                'address_line2' => $address_line2,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'country' => $country
            ];
            $_SESSION['checkout']['step'] = 3;
            header('Location: checkout.php?step=3');
            exit;
        }
    }
}

////////////////////////////////////////////////////////////////////////////
////////////// step 3: Review & Confirmation /////////////////////////////////
////////////////////////////////////////////////////////////////////////////
if ($step === 3) {
    if (!isset($_SESSION['checkout']['services_data']) || !isset($_SESSION['checkout']['payment_data'])) {
        header('Location: checkout.php?step=1');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
        
        if (!isset($_POST['terms_agree'])) {
            $errors['terms'] = 'You must agree to the Terms of Service and Privacy Policy';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $transaction_id = generate_transaction_id();
                $created_orders = [];
                
                foreach ($services as $service) {
                    $service_id = $service->getServiceId();
                    $service_data = $_SESSION['checkout']['services_data'][$service_id];
                    $payment_data = $_SESSION['checkout']['payment_data'];
                    
                    $order_id = generate_unique_id($pdo, 'orders', 'order_id');
                    $expected_delivery = calculate_expected_delivery($service->getDeliveryTime());
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO orders (
                            order_id, client_id, freelancer_id, service_id,
                            service_title, price, delivery_time, revisions_included,
                            requirements, special_instructions, preferred_deadline,
                            status, payment_method, transaction_id,
                            order_date, expected_delivery
                        ) VALUES (
                            :order_id, :client_id, :freelancer_id, :service_id,
                            :service_title, :price, :delivery_time, :revisions,
                            :requirements, :instructions, :deadline,
                            'Pending', :payment_method, :transaction_id,
                            NOW(), :expected_delivery
                        )
                    ");
                    
                    $stmt->execute([
                        'order_id' => $order_id,
                        'client_id' => $_SESSION['user_id'],
                        'freelancer_id' => $service->getFreelancerId(),
                        'service_id' => $service_id,
                        'service_title' => $service->getTitle(),
                        'price' => $service->getPrice(),
                        'delivery_time' => $service->getDeliveryTime(),
                        'revisions' => $service->getRevisionsIncluded(),
                        'requirements' => $service_data['requirements'],
                        'instructions' => $service_data['instructions'] ?: null,
                        'deadline' => $service_data['deadline'] ?: null,
                        'payment_method' => $payment_data['payment_method'],
                        'transaction_id' => $transaction_id,
                        'expected_delivery' => $expected_delivery
                    ]);
                    
                    if (!empty($service_data['files'])) {
                        save_requirement_files($order_id, $service_data['files'], $pdo);
                    }
                    
                    $created_orders[] = $order_id;
                }
                
                $pdo->commit();
                unset($_SESSION['checkout'], $_SESSION['cart']);
                
                $_SESSION['recent_orders'] = $created_orders;
                header('Location: order-success.php');
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors['general'] = 'Failed to place order. Please try again.';
                error_log("Order creation error: " . $e->getMessage());
            }
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Progress Breadcrumb -->
<div class="breadcrumb-progress">
    <div class="step-container">
        <div class="step <?php echo $step >= 1 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 1 ? 'step-active' : ''; ?>">
            <?php if ($step > 1): ?>
                <a href="checkout.php?step=1" class="step-icon">✓</a>
            <?php else: ?>
                <div class="step-icon">1</div>
            <?php endif; ?>
            <div class="step-label">Service Requirements</div>
        </div>
        
        <div class="step-connector <?php echo $step >= 2 ? 'connector-completed' : ''; ?>"></div>
        
        <div class="step <?php echo $step >= 2 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 2 ? 'step-active' : ''; ?>">
            <?php if ($step > 2): ?> 
                <a href="checkout.php?step=2" class="step-icon">✓</a>
            <?php else: ?>
                <div class="step-icon">2</div>
            <?php endif; ?>
            <div class="step-label">Payment Information</div>
        </div>
        
        <div class="step-connector <?php echo $step >= 3 ? 'connector-completed' : ''; ?>"></div>
        
        <div class="step <?php echo $step >= 3 ? 'step-completed' : 'step-inactive'; ?> <?php echo $step === 3 ? 'step-active' : ''; ?>">
            <div class="step-icon">3</div>
            <div class="step-label">Review & Confirmation</div>
        </div>
    </div>
</div>

<div class="page-header">
    <h1>Checkout</h1>
</div>

<?php if (!empty($errors['general'])): ?>
<div class="message-error"><?php echo htmlspecialchars($errors['general']); ?></div>
<?php endif; ?>

<?php

////////////////////////////////////////////////////////////////////////////
// STEP 1 DISPLAY 
////////////////////////////////////////////////////////////////////////////
if ($step === 1):
?>

<h2 class="heading-secondary">Step 1: Service Requirements</h2>
<p class="checkout-step-intro">
    Please provide requirements for each service. All services must have requirements to continue.
</p>

<form method="POST" action="checkout.php?step=1" enctype="multipart/form-data">
    
    <?php foreach ($services as $index => $service): ?>
        
        <div class="service-requirement-card">
            
            <div class="requirement-card-header">
                <div>
                    <h3 class="requirement-card-title">
                        <?php echo htmlspecialchars($service->getTitle()); ?>
                    </h3>
                    <p class="requirement-card-meta">
                        <strong>Freelancer:</strong> <?php echo htmlspecialchars($service->getFreelancerName()); ?>
                    </p>
                    <p class="requirement-card-meta">
                        <strong>Delivery:</strong> <?php echo $service->getDeliveryTime(); ?> days
                    </p>
                </div>
                <div class="requirement-card-price">
                    <p class="requirement-card-price-amount">
                        <?php echo format_price($service->getPrice()); ?>
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="requirements_<?php echo $index; ?>" class="form-label">Service Requirements *</label>
                <textarea 
                    id="requirements_<?php echo $index; ?>"
                    name="requirements_<?php echo $index; ?>"
                    class="form-input <?php echo isset($errors["service_$index"]['requirements']) ? 'input-error' : ''; ?>"
                    rows="5"
                    placeholder="Describe what you need for this service..."
                    required
                ><?php echo isset($_POST["requirements_$index"]) ? htmlspecialchars($_POST["requirements_$index"]) : ''; ?></textarea>
                <span class="form-hint">50-1000 characters</span>
                <?php if (isset($errors["service_$index"]['requirements'])): ?>
                    <span class="form-error"><?php echo $errors["service_$index"]['requirements']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="instructions_<?php echo $index; ?>" class="form-label">Special Instructions (Optional)</label>
                <textarea 
                    id="instructions_<?php echo $index; ?>"
                    name="instructions_<?php echo $index; ?>"
                    class="form-input"
                    rows="3"
                    placeholder="Any special notes or preferences..."
                ><?php echo isset($_POST["instructions_$index"]) ? htmlspecialchars($_POST["instructions_$index"]) : ''; ?></textarea>
                <span class="form-hint">Up to 500 characters</span>
            </div>
            
            <div class="form-group">
                <label for="deadline_<?php echo $index; ?>" class="form-label">Preferred Deadline (Optional)</label>
                <input 
                    type="date"
                    id="deadline_<?php echo $index; ?>"
                    name="deadline_<?php echo $index; ?>"
                    class="form-input"
                    min="<?php echo date('Y-m-d', strtotime("+{$service->getDeliveryTime()} days")); ?>"
                    value="<?php echo isset($_POST["deadline_$index"]) ? htmlspecialchars($_POST["deadline_$index"]) : ''; ?>"
                >
                <span class="form-hint">Must be at least <?php echo $service->getDeliveryTime(); ?> days from today</span>
                <?php if (isset($errors["service_$index"]['deadline'])): ?>
                    <span class="form-error"><?php echo $errors["service_$index"]['deadline']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="form-label">Requirement Files (Optional)</label>
                <p class="form-hint checkout-files-hint">
                    Upload up to 3 files (PDF, DOC, TXT, ZIP, JPG, PNG) - Max 10MB each
                </p>
                
                <input
                    type="file"
                    name="files_<?php echo $index; ?>[]"
                    class="form-input checkout-file-input"
                    accept=".pdf,.doc,.docx,.txt,.zip,.jpg,.jpeg,.png"
                    multiple
                >
                
                <?php if (isset($errors["service_$index"]['files'])): ?>
                    <span class="form-error"><?php echo $errors["service_$index"]['files']; ?></span>
                <?php endif; ?>
            </div>
            
        </div>
        
    <?php endforeach; ?>

    <div class="form-actions checkout-actions">
        <button type="submit" class="btn-primary checkout-btn-lg checkout-btn-min-200">
            Continue to Payment
        </button>
        <a href="cart.php" class="btn-secondary checkout-btn-lg checkout-btn-min-200 checkout-btn-flex">
            Edit Cart
        </a>
    </div>
    
</form>


<?php elseif ($step === 2): ?>

<!-- ////////////////////////////////////////////////////////////////////////////
/////////////////////// Step 2 /////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////// -->


<h2 class="heading-secondary">Step 2: Payment Information</h2>

<form method="POST" action="checkout.php?step=2" class="form checkout-form-narrow">
    
    <div class="form-section">
        <h3 class="heading-secondary checkout-subheading">Payment Method</h3>
        
        <div class="form-group">
            <label class="form-label">Select Payment Method *</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="payment_method" value="Credit Card" 
                           <?php echo (!isset($payment_method) || $payment_method === 'Credit Card') ? 'checked' : ''; ?> required>
                    <span>Credit Card</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="payment_method" value="PayPal" 
                           <?php echo ($payment_method ?? '') === 'PayPal' ? 'checked' : ''; ?>>
                    <span>PayPal</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="payment_method" value="Bank Transfer" 
                           <?php echo ($payment_method ?? '') === 'Bank Transfer' ? 'checked' : ''; ?>>
                    <span>Bank Transfer</span>
                </label>
            </div>
            <?php if (isset($errors['payment_method'])): ?>
                <span class="form-error"><?php echo $errors['payment_method']; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div id="card-details" class="form-section">
        <h3 class="heading-secondary checkout-subheading">Card Details</h3>
        
        <div class="form-group">
            <label for="card_number" class="form-label">Card Number *</label>
            <input 
                type="text"
                id="card_number"
                name="card_number"
                class="form-input <?php echo isset($errors['card_number']) ? 'input-error' : ''; ?>"
                placeholder="1234 5678 9012 3456"
                maxlength="16"
                value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>"
            >
            <span class="form-hint">16 digits</span>
            <?php if (isset($errors['card_number'])): ?>
                <span class="form-error"><?php echo $errors['card_number']; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="card_name" class="form-label">Cardholder Name *</label>
            <input 
                type="text"
                id="card_name"
                name="card_name"
                class="form-input <?php echo isset($errors['card_name']) ? 'input-error' : ''; ?>"
                placeholder="John Doe"
                value="<?php echo isset($_POST['card_name']) ? htmlspecialchars($_POST['card_name']) : ''; ?>"
            >
            <?php if (isset($errors['card_name'])): ?>
                <span class="form-error"><?php echo $errors['card_name']; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="checkout-grid-2">
            <div class="form-group">
                <label for="expiry_date" class="form-label">Expiration Date *</label>
                <input 
                    type="text"
                    id="expiry_date"
                    name="expiry_date"
                    class="form-input <?php echo isset($errors['expiry_date']) ? 'input-error' : ''; ?>"
                    placeholder="MM/YY"
                    maxlength="5"
                    value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : ''; ?>"
                >
                <?php if (isset($errors['expiry_date'])): ?>
                    <span class="form-error"><?php echo $errors['expiry_date']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="cvv" class="form-label">CVV *</label>
                <input 
                    type="text"
                    id="cvv"
                    name="cvv"
                    class="form-input <?php echo isset($errors['cvv']) ? 'input-error' : ''; ?>"
                    placeholder="123"
                    maxlength="3"
                    value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>"
                >
                <?php if (isset($errors['cvv'])): ?>
                    <span class="form-error"><?php echo $errors['cvv']; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="heading-secondary checkout-subheading">Billing Address</h3>
        
        <div class="form-group">
            <label for="address_line1" class="form-label">Address Line 1 *</label>
            <input 
                type="text"
                id="address_line1"
                name="address_line1"
                class="form-input <?php echo isset($errors['address_line1']) ? 'input-error' : ''; ?>"
                placeholder="Street address, P.O. box"
                value="<?php echo isset($_POST['address_line1']) ? htmlspecialchars($_POST['address_line1']) : ''; ?>"
                required
            >
            <?php if (isset($errors['address_line1'])): ?>
                <span class="form-error"><?php echo $errors['address_line1']; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="address_line2" class="form-label">Address Line 2 (Optional)</label>
            <input 
                type="text"
                id="address_line2"
                name="address_line2"
                class="form-input"
                placeholder="Apartment, suite, unit, building, floor, etc."
                value="<?php echo isset($_POST['address_line2']) ? htmlspecialchars($_POST['address_line2']) : ''; ?>"
            >
        </div>
        
        <div class="checkout-grid-2">
            <div class="form-group">
                <label for="city" class="form-label">City *</label>
                <input 
                    type="text"
                    id="city"
                    name="city"
                    class="form-input <?php echo isset($errors['city']) ? 'input-error' : ''; ?>"
                    value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>"
                    required
                >
                <?php if (isset($errors['city'])): ?>
                    <span class="form-error"><?php echo $errors['city']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="state" class="form-label">State/Province *</label>
                <input 
                    type="text"
                    id="state"
                    name="state"
                    class="form-input <?php echo isset($errors['state']) ? 'input-error' : ''; ?>"
                    value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>"
                    required
                >
                <?php if (isset($errors['state'])): ?>
                    <span class="form-error"><?php echo $errors['state']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="checkout-grid-2">
            <div class="form-group">
                <label for="postal_code" class="form-label">Postal Code *</label>
                <input 
                    type="text"
                    id="postal_code"
                    name="postal_code"
                    class="form-input <?php echo isset($errors['postal_code']) ? 'input-error' : ''; ?>"
                    value="<?php echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : ''; ?>"
                    required
                >
                <?php if (isset($errors['postal_code'])): ?>
                    <span class="form-error"><?php echo $errors['postal_code']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="country" class="form-label">Country *</label>
                <select 
                    id="country"
                    name="country"
                    class="form-input <?php echo isset($errors['country']) ? 'input-error' : ''; ?>"
                    required
                >
                    <option value="">Select Country</option>
                    <option value="Palestine" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Palestine') ? 'selected' : ''; ?>>Palestine</option>
                    <option value="Jordan" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Jordan') ? 'selected' : ''; ?>>Jordan</option>
                    <option value="Egypt" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Egypt') ? 'selected' : ''; ?>>Egypt</option>
                    <option value="Lebanon" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Lebanon') ? 'selected' : ''; ?>>Lebanon</option>
                    <option value="Syria" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Syria') ? 'selected' : ''; ?>>Syria</option>
                    <option value="Saudi Arabia" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Saudi Arabia') ? 'selected' : ''; ?>>Saudi Arabia</option>
                    <option value="UAE" <?php echo (isset($_POST['country']) && $_POST['country'] === 'UAE') ? 'selected' : ''; ?>>UAE</option>
                    <option value="Other" <?php echo (isset($_POST['country']) && $_POST['country'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <?php if (isset($errors['country'])): ?>
                    <span class="form-error"><?php echo $errors['country']; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="form-actions checkout-actions">
        <button type="submit" class="btn-primary checkout-btn-lg checkout-btn-min-200">
            Continue to Review
        </button>
        <a href="checkout.php?step=1" class="btn-secondary checkout-btn-lg checkout-btn-min-200 checkout-btn-flex">
            Back to Requirements
        </a>
    </div>
    
</form>

<!-- ////////////////////////////////////////////////////////////////////////////
////////////////////////// Step 3  ////////////////////////////
//////////////////////////////////////////////////////////////////////////// -->
<?php else: // Step 3 ?>

<?php
    $services_data = $_SESSION['checkout']['services_data'];
    $payment_data = $_SESSION['checkout']['payment_data'];
?>

<h2 class="heading-secondary">Step 3: Review & Confirmation</h2>

<div class="checkout-review-layout">
    
    <div class="checkout-review-main">
        
        <form method="POST" action="checkout.php?step=3">
            
            <div class="checkout-review-section">
                <h3 class="checkout-section-title">Service Requirements</h3>
                
                <?php foreach ($services as $index => $service): ?>
                    <?php $service_id = $service->getServiceId(); ?>
                    <?php $data = $services_data[$service_id]; ?>
                    
                    <details open class="checkout-details">
                        <summary class="checkout-details-summary">
                            <?php echo htmlspecialchars($service->getTitle()); ?>
                            <span class="checkout-details-by">
                                by <?php echo htmlspecialchars($service->getFreelancerName()); ?>
                            </span>
                        </summary>
                        
                        <div class="checkout-details-body">

                            <!--Requirements-->
                            <div class="checkout-review-block">
                                <strong class="checkout-review-label">Requirements:</strong>
                                <p class="checkout-review-text">
                                    <?php echo nl2br(htmlspecialchars($data['requirements'])); ?>
                                </p>
                            </div>
                            
                            <!--instructions-->
                            <?php if (!empty($data['instructions'])): ?>
                            <div class="checkout-review-block">
                                <strong class="checkout-review-label">Special Instructions:</strong>
                                <p class="checkout-review-text">
                                    <?php echo nl2br(htmlspecialchars($data['instructions'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <!--deadline-->
                            <?php if (!empty($data['deadline'])): ?>
                            <div class="checkout-review-block">
                                <strong class="checkout-review-label">Preferred Deadline:</strong>
                                <p class="checkout-review-text">
                                    <?php echo date('M d, Y', strtotime($data['deadline'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!--Requirement Files-->
                            <div class="checkout-review-block">
                                <strong class="checkout-review-label checkout-review-label-files">Requirement Files:</strong>
                                
                                <?php if (!empty($data['files'])): ?>
                                    <?php foreach ($data['files'] as $file): ?>
                                        <?php $icon_class = get_file_icon_class($file['mime_type']); ?>
                                        
                                        <div class="checkout-file-item">
                                            <div class="checkout-file-icon <?php echo htmlspecialchars($icon_class); ?>">
                                                <?php echo get_file_type_text($file['mime_type']); ?>
                                            </div>
                                            
                                            <div class="checkout-file-info">
                                                <div class="checkout-file-name">
                                                    <?php echo htmlspecialchars($file['original_name']); ?>
                                                </div>
                                                <div class="checkout-file-size">
                                                    <?php echo format_file_size($file['size']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="checkout-file-empty">No files uploaded</p>
                                <?php endif; ?>
                            </div>
                            
                        </div>
                    </details>
                    
                <?php endforeach; ?>
             </div>   
                
            
            <div class="checkout-review-section">
                <h3 class="checkout-section-title">Payment Information</h3>
                
                <div class="checkout-payment-card">
                    <div class="checkout-review-block">
                        <strong class="checkout-review-label">Payment Method:</strong>
                        <p class="checkout-review-text">
                            <?php echo htmlspecialchars($payment_data['payment_method']); ?>
                        </p>
                    </div>
                    
                    <?php if ($payment_data['payment_method'] === 'Credit Card'): ?>
                    <div class="checkout-review-block">
                        <strong class="checkout-review-label">Cardholder Name:</strong>
                        <p class="checkout-review-text">
                            <?php echo htmlspecialchars($payment_data['card_name']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="checkout-review-block checkout-review-block-last">
                        <strong class="checkout-review-label">Billing Address:</strong>
                        <p class="checkout-review-text checkout-address">
                            <?php echo htmlspecialchars($payment_data['address_line1']); ?><br>
                            <?php if (!empty($payment_data['address_line2'])): ?>
                                <?php echo htmlspecialchars($payment_data['address_line2']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($payment_data['city']); ?>, 
                            <?php echo htmlspecialchars($payment_data['state']); ?> 
                            <?php echo htmlspecialchars($payment_data['postal_code']); ?><br>
                            <?php echo htmlspecialchars($payment_data['country']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="checkout-edit-link-wrap checkout-edit-link-wrap-tight">
                    <a href="checkout.php?step=2" class="checkout-edit-link">← Edit Payment Information </a>
                </div>
            </div>
            
            <div class="checkout-terms-box">
                <label class="checkbox-label checkout-terms-label">
                    <input type="checkbox" name="terms_agree" id="terms_agree" required class="checkout-terms-checkbox">
                    <span class="checkout-terms-text">
                        I agree to the <a href="terms-of-service.php" target="_blank" class="checkout-terms-link">Terms of Service</a> 
                        and <a href="privacy-policy.php" target="_blank" class="checkout-terms-link">Privacy Policy</a>. 
                    </span>
                </label>
                <?php if (isset($errors['terms'])): ?>
                    <span class="form-error checkout-terms-error"><?php echo $errors['terms']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="checkout-place-order-wrap">
                <button type="submit" name="place_order" class="btn-success checkout-btn-lg checkout-btn-min-250 checkout-btn-bold">
                    Place Order
                </button>
            </div>
            
        </form>
        
    </div>
    
    <!-- Right-->
    <div class="checkout-review-sidebar">
        <div class="cart-summary-card checkout-summary-card">
            
            <h3 class="checkout-summary-title">Order Summary</h3>
            
            <p class="checkout-summary-count">
                You will place <strong class="checkout-summary-count-number"><?php echo count($services); ?></strong> 
                order
            </p>
            
            <div class="checkout-summary-items">
                <?php foreach ($services as $service): ?>
                    <div class="checkout-summary-item">
                        <div class="checkout-summary-check">✓</div>
                        
                        <!-- title -->
                        <div class="checkout-summary-item-body">
                            <h4 class="checkout-summary-item-title">
                                <?php echo htmlspecialchars($service->getTitle()); ?>
                            </h4>
                            
                            <!-- freelancer name -->
                            <p class="checkout-summary-freelancer">
                                by <a href="#" class="checkout-summary-freelancer-link">
                                    <?php echo htmlspecialchars($service->getFreelancerName()); ?>
                                </a>
                            </p>
                            
                            <div class="checkout-summary-row">
                                <span class="checkout-summary-label">Price:</span> 
                                <strong class="checkout-summary-value"><?php echo format_price($service->getPrice()); ?></strong>
                            </div>
                            <div class="checkout-summary-row">
                                <span class="checkout-summary-label">Service Fee:</span> 
                                <strong class="checkout-summary-value"><?php echo format_price($service->calculateServiceFee()); ?></strong>
                            </div>
                            <div class="checkout-summary-total-line">
                                Total: <?php echo format_price($service->getTotalWithFee()); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="checkout-totals-card">
                <div class="checkout-totals-row">
                    <span class="checkout-totals-label">Subtotal:</span>
                    <span class="checkout-totals-value"><?php echo format_price($subtotal); ?></span>
                </div>
                <div class="checkout-totals-row checkout-totals-row-divider">
                    <span class="checkout-totals-label">Service Fee (5%):</span>
                    <span class="checkout-totals-value"><?php echo format_price($service_fee); ?></span>
                </div>
                <div class="checkout-totals-row checkout-totals-grand">
                    <span class="checkout-totals-grand-label">Grand Total:</span>
                    <span class="checkout-totals-grand-value"><?php echo format_price($total); ?></span>
                </div>
            </div>
            
        </div>
    </div>
    
</div>

<?php endif; ?>


<?php
require_once 'includes/footer.php';
?>