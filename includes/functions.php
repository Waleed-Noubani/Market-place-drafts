<?php
require_once __DIR__ . '/../classes/Service.php';

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);  // وتطبّق عليه نفس الدالة sanitize_inputتمرّ على كل عنصر في , الـ Array
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
/**
* trim($data) : يشيل الفراغات
*strip_tags(...) : يشيل أي HTML:"<b>Hello</b>" → "Hello"
* htmlspecialchars(...) : يحوّل الرموز الخطيرة لنص آمن: > <
*/



 // Validate email format

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * Min 8 chars: 1 upper, 1 lower, 1 number, 1 special char
 */
function validate_password($password) {
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/';
    return preg_match($pattern, $password);
}

/**
 * Validate phone number (10 digits)
 */
function validate_phone($phone) {
    return preg_match('/^\d{10}$/', $phone);
}

// ID Generation
/**
 * Generate unique 10-digit numeric ID
 * 
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @return string 10-digit unique ID
 */
function generate_unique_id($pdo, $table, $column) {
    do {
        // Generate random 10-digit number 
        $id = str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
/**
 * rand(0, 9999999999) : يولد رقم عشوائي : ممكن يكون أقل من 10 أرقام
 *str_pad(..., 10, '0', STR_PAD_LEFT) :  :يضيف أصفار من اليسار :: لو الرقم أقل من 10 خانات
*/        
       
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = :id");  // بتاكد اذا فيه رقم مشابه بالداتابيس
        $stmt->execute(['id' => $id]);
        
    } while ($stmt->fetchColumn() > 0); // لو لقيت اعد العملية
    
    return $id;
}


/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is a client
 */
function is_client() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Client';
}

/**
 * Check if user is a freelancer
 */
function is_freelancer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Freelancer';
}

/**
 * Require user to be logged in
 * Redirects to login if not authenticated  : ميثود بفحص اذا مسجلين دخول
 */
function require_login($redirect = 'login.php') { // لو ما حط لوكيشن معين يفترض اللوغ ان
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI']; // 👉 بعد ما يسجّل دخول :  يرجع تلقائيًا لنفس الصفحة

        header("Location: $redirect"); 
        exit;
    }
}

/**
 * Require user to be a client
 */
function require_client() { // لو مش كلاينت رجعه عصفحة الاندكس
    require_login();
    if (!is_client()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Require user to be a freelancer
 */
function require_freelancer() { // لو مش فريلانسر رجعه عصفحة الاندكس
    require_login();
    if (!is_freelancer()) {
        header('Location: index.php');
        exit;
    }
}


/**
 * Get cart count
 */
function get_cart_count() { // تعطيك عدد العناصر الموجودة في سلة الشراء (Cart) : غالبًا تُستخدم لعرض رقم صغير جنب أيقونة السلة في الـ header.
    return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}

/**
* Check if service is in cart
* تُستخدم غالبًا:
*لمنع إضافة نفس الخدمة مرتين
* لتغيير زر Add to Cart إلى In Cart 
 */
function is_in_cart($service_id) { // هل هذه الخدمة (Service) موجودة أصلًا في سلة الشراء؟
    if (!isset($_SESSION['cart'])) { // إذا السلة غير موجودة أصلًا
        return false;
    }
    
    foreach ($_SESSION['cart'] as $item) {
        if ($item instanceof Service && $item->getServiceId() == $service_id) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calculate cart subtotal
 */
function calculate_cart_subtotal() {  // حساب مجموع أسعار الخدمات الموجودة في سلة المشتريات (Cart).
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $service) {
        $subtotal += $service->getPrice();
    }
    
    return $subtotal;
}

/**
 * Calculate service fee (5%)
 */
function calculate_service_fee($amount) { // تحسب عمولة المنصّة بنسبة 5% من أي مبلغ يُمرَّر لها.
    return $amount * 0.05;
}

/**
 * Calculate cart total (subtotal + service fee)
 */
function calculate_cart_total() { // تحسب المبلغ النهائي المطلوب من العميل:
    $subtotal = calculate_cart_subtotal();
    return $subtotal + calculate_service_fee($subtotal);
}

// Formatting Functions
// Format price with currency
 
function format_price($amount) {      // تحوّل رقم إلى سعر بصيغة مالية مع علامة الدولار وكسور عشرية.
    return '$' . number_format($amount, 2);
}

//Format date (MMM DD, YYYY)
function format_date($date) {  // format_date('2025-12-23');  // "Dec 23, 2025"
    return date('M d, Y', strtotime($date));
}

/**
 * Format datetime
 */
function format_datetime($datetime) {       // format_datetime('2025-12-23 18:30:00');  // "Dec 23, 2025 06:30 PM"
    return date('M d, Y h:i A', strtotime($datetime));
}

/**
 * Format file size to human readable
 */
function format_file_size($bytes) {  // echo format_file_size(5242880); // النتيجة: "5.00 MB" ← واضح وسهل! 👍
    if ($bytes >= 1073741824) { //  1073741824 = 1024 × 1024 × 1024
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// File Upload Functions
/**
 * Validate uploaded file
 * 
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array ['success' => bool, 'error' => string]
 */
function validate_file_upload($file, $allowed_types, $max_size) { //بتتحقق إنه الملف اللي رفعه اليوزر آمن وصالح
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {  // لرفع نجح : UPLOAD_ERR_OK = 0 ← // ا أي رقم تاني = في مشكلة 
        return ['success' => false, 'error' => 'File upload failed'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) { 
        $max_mb = $max_size / (1024 * 1024);
        return ['success' => false, 'error' => "File size exceeds {$max_mb}MB limit"];
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {        // .pdf
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    return ['success' => true];
}

/**
 * Generate safe filename
 */
function generate_safe_filename($original_filename) { // generate_safe_filename("My File@2025.jpg"); // الناتج ممكن يكون: "My_File_2025_1766164923.jpg"
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = pathinfo($original_filename, PATHINFO_FILENAME);
    
    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    
    // Add timestamp to prevent conflicts
    return $filename . '_' . time() . '.' . $extension; // 1766164923
}


////////////////////////////// Statistics Functions (for Freelancers) /////////////////////////////////////////////
function get_freelancer_stats($pdo, $freelancer_id) {
    $stats = [];
    
    // Total services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :id");
    $stmt->execute(['id' => $freelancer_id]);
    $stats['total_services'] = $stmt->fetchColumn();
    
    // Active services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :id AND status = 'Active'");
    $stmt->execute(['id' => $freelancer_id]);
    $stats['active_services'] = $stmt->fetchColumn();
    
    // Featured services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE freelancer_id = :id AND featured_status = 'Yes'");
    $stmt->execute(['id' => $freelancer_id]);
    $stats['featured_services'] = $stmt->fetchColumn();
    
    // Completed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE freelancer_id = :id AND status = 'Completed'");
    $stmt->execute(['id' => $freelancer_id]);
    $stats['completed_orders'] = $stmt->fetchColumn();
    
    return $stats;
}

///////////////////////////// Error Handling ////////////////////////////////////

// Display error message
function display_error($message) {
    return '<div class="message-error">' . htmlspecialchars($message) . '</div>';
}

//Display success message
function display_success($message) {
    return '<div class="message-success">' . htmlspecialchars($message) . '</div>';
}


 // Display warning message
function display_warning($message) {
    return '<div class="message-warning">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display info message
 */
function display_info($message) {
    return '<div class="message-info">' . htmlspecialchars($message) . '</div>';
}

//////////////////////////////////////// Redirect Functions ////////////////////////////////////////////////////

// Redirect with message
function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['message'] = $message; // → نص الرسالة (مثلاً: "تمت العملية بنجاح")
    $_SESSION['message_type'] = $type; //  → نوع الرسالة (مثلاً: 'success', 'error', 'warning')
    header("Location: $url");
    exit;
} // النتيجة: بعد إعادة التوجيه، الرسالة موجودة في السيشن ولكن لم تظهر بعد للمستخدم.

// Get and clear flash message
// تأخذ الرسالة المخزنة في السيشن بعد إعادة التوجيه ، تعرضها للمستخدم
// ثم تمسحها من السيشن (لأنها "flash message" تظهر مرة واحدة فقط)
function get_flash_message() {  
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        switch ($type) {
            case 'error':
                return display_error($message);
            case 'success':
                return display_success($message);
            case 'warning':
                return display_warning($message);
            default:
                return display_info($message);
        }
    }
    
    return '';
} //         النتيجة: الرسالة تظهر للمستخدم على الصفحة التي تم إعادة التوجيه إليها ثم تختفي إذا عمل المستخدم تحديث للصفحة.


//////////////////// edit  ststus in my-service /////////////////////////
function toggle_service_status($pdo, $service_id, $freelancer_id) {

    $stmt = $pdo->prepare("
        UPDATE services
        SET status = IF(status = 'Active', 'Inactive', 'Active'),
            featured_status = IF(status = 'Active', 'No', featured_status)
        WHERE service_id = :id AND freelancer_id = :fid
    ");

    return $stmt->execute([
        'id' => $service_id,
        'fid' => $freelancer_id
    ]);
}

/**
 * Checkout & Order Functions
 * Add these to the end of functions.php
 * Generate unique transaction ID
 */
function generate_transaction_id(): string{
    return 'TXN' . time() . '_' . str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate expected delivery date
 * @param int $delivery_days Number of days for delivery
 * @return string Date in Y-m-d format
 */
function calculate_expected_delivery(int $delivery_days): string
{
    return date('Y-m-d', strtotime("+{$delivery_days} days"));
}

/**
 * Validate uploaded files for checkout requirements
 * Expected input: $files = $_FILES['requirements'] where:
 * $files['name'][0..], $files['tmp_name'][0..], $files['error'][0..], $files['size'][0..]
 *
 * @param array $files  A single $_FILES entry (e.g. $_FILES['requirements'])
 * @param int   $max_files Maximum number of files allowed
 * @return array ['success' => bool, 'error' => string, 'files' => array]
 */
function validate_requirement_files(array $files, int $max_files = 3): array
{
    $result = [
        'success' => true,
        'error'   => '',
        'files'   => [],
    ];

    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'image/jpeg',
        'image/png',
    ];

    $max_size = 10 * 1024 * 1024; // 10MB

    // If this isn't a multiple-upload structure, fail clearly
    if (!isset($files['name']) || !is_array($files['name'])) {
        // Treat as "no files provided" rather than fatal
        return $result;
    }

    $count = min(count($files['name']), $max_files);

    for ($i = 0; $i < $count; $i++) {
        // Skip empty slot
        if (!isset($files['error'][$i]) || $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $result['success'] = false;
            $result['error']   = "File upload error for file " . ($i + 1);
            return $result;
        }

        if (!isset($files['size'][$i]) || $files['size'][$i] > $max_size) {
            $result['success'] = false;
            $result['error']   = "File " . ($i + 1) . " exceeds 10MB limit";
            return $result;
        }

        $tmp_name = $files['tmp_name'][$i] ?? '';
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            $result['success'] = false;
            $result['error']   = "Invalid uploaded file for file " . ($i + 1);
            return $result;
        }

        // Detect MIME type safely
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = $finfo ? finfo_file($finfo, $tmp_name) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mime_type || !in_array($mime_type, $allowed_types, true)) {
            $result['success'] = false;
            $result['error']   = "File " . ($i + 1) . " type not allowed";
            return $result;
        }

        $result['files'][] = [
            'tmp_name'       => $tmp_name,
            'original_name'  => $files['name'][$i] ?? ('file_' . ($i + 1)),
            'size'           => $files['size'][$i],
            'mime_type'      => $mime_type,
        ];
    }

    return $result;
}

/**
 * Save uploaded files to order directory and insert attachments into DB
 *
 * @param string $order_id
 * @param array  $files    Output from validate_requirement_files()['files']
 * @param PDO    $pdo
 * @return bool
 */
function save_requirement_files(string $order_id, array $files, PDO $pdo): bool
{
    // Create directory for this order
    $order_dir = "uploads/orders/{$order_id}/requirements/";

    if (!is_dir($order_dir)) {
        if (!mkdir($order_dir, 0755, true)) {
            return false;
        }
    }

    foreach ($files as $index => $file) {
        $extension = pathinfo($file['original_name'] ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? strtolower($extension) : 'dat';

        $safe_filename = 'req_' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) . '.' . $extension;
        $file_path = $order_dir . $safe_filename;

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO file_attachments (
                    order_id, file_path, original_filename,
                    file_size, mime_type, file_type
                ) VALUES (
                    :order_id, :file_path, :original_filename,
                    :file_size, :mime_type, 'requirement'
                )
            ");

            $stmt->execute([
                'order_id'          => $order_id,
                'file_path'         => $file_path,
                'original_filename' => $file['original_name'],
                'file_size'         => $file['size'],
                'mime_type'         => $file['mime_type'],
            ]);
        } catch (PDOException $e) {
            error_log("File insert error: " . $e->getMessage());
            return false;
        }
    }

    return true;
}

/**
 * Get file type icon class based on mime type
 * @param string $mime_type
 * @return string
 */
function get_file_icon_class(string $mime_type): string
{
    if (strpos($mime_type, 'pdf') !== false) {
        return 'file-icon-pdf';
    }

    if (strpos($mime_type, 'word') !== false || strpos($mime_type, 'msword') !== false) {
        return 'file-icon-doc';
    }

    if (strpos($mime_type, 'image') !== false) {
        return 'file-icon-img';
    }

    if (strpos($mime_type, 'zip') !== false) {
        return 'file-icon-zip';
    }

    return 'file-icon-generic';
}

/**
 * Get file type display text
 * @param string $mime_type
 * @return string
 */
function get_file_type_text(string $mime_type): string
{
    if (strpos($mime_type, 'pdf') !== false) {
        return 'PDF';
    }

    if (strpos($mime_type, 'word') !== false || strpos($mime_type, 'msword') !== false) {
        return 'DOC';
    }

    if (strpos($mime_type, 'image') !== false) {
        return 'IMG';
    }

    if (strpos($mime_type, 'zip') !== false) {
        return 'ZIP';
    }

    return 'FILE';
}

/**
 * Validate credit card number (simplified for project)
 * @param string $number
 * @return bool
 */
function validate_credit_card(string $number): bool
{
    $number = preg_replace('/[\s-]/', '', $number);

    // Check if 16 digits
    if (!preg_match('/^\d{16}$/', $number)) {
        return false;
    }

    // For simplicity in this project, return true after format check
    return true;
}

/**
 * Validate expiration date (MM/YY format)
 * @param string $expiry
 * @return bool
 */
function validate_expiry_date(string $expiry): bool
{
    // Expect MM/YY
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
        return false;
    }

    [$month, $year] = explode('/', $expiry);
    $year = '20' . $year;

    $expiry_date = strtotime("{$year}-{$month}-01");
    $current_date = strtotime(date('Y-m-01'));

    return $expiry_date !== false && $expiry_date >= $current_date;
}

/**
 * Clear checkout session data
 */
function clear_checkout_session(): void
{
    unset($_SESSION['checkout'], $_SESSION['cart']);
}





/**
 * Validate multiple file uploads with comprehensive checks
 * 
 * @param array $files $_FILES array for multiple files (e.g., $_FILES['file_name'])
 * @param int $max_files Maximum number of files allowed (default: 5)
 * @param int $max_size_mb Maximum size per file in MB (default: 50)
 * @param array|null $allowed_types Array of allowed MIME types (null = use default extended list)
 * @return array [
 *     'success' => bool,        // Whether validation passed
 *     'error' => string,        // Error message if validation failed
 *     'files' => array          // Array of validated file info
 * ]
 * 
 * @example
 * $result = validate_requirement_files($_FILES['documents'], 3, 10);
 * if ($result['success']) {
 *     foreach ($result['files'] as $file) {
 *         // Process each file
 *     }
 * } else {
 *     echo $result['error'];
 * }
 */
/**
 * Validate multiple file uploads with comprehensive checks
 * 
 * @param array $files $_FILES array for multiple files (e.g., $_FILES['file_name'])
 * @param int $max_files Maximum number of files allowed (default: 5)
 * @param int $max_size_mb Maximum size per file in MB (default: 50)
 * @param array|null $allowed_types Array of allowed MIME types (null = use default extended list)
 * @return array [
 *     'success' => bool,        // Whether validation passed
 *     'error' => string,        // Error message if validation failed
 *     'files' => array          // Array of validated file info
 * ]
 * 
 * @example
 * $result = validate_requirement_files($_FILES['documents'], 3, 10);
 * if ($result['success']) {
 *     foreach ($result['files'] as $file) {
 *         // Process each file
 *     }
 * } else {
 *     echo $result['error'];
 * }
 */
function validate_requirement_files2(
    array $files, 
    int $max_files = 5, 
    int $max_size_mb = 50,
    ?array $allowed_types = null
): array {
    
    // ============================================
    // INITIALIZE RESULT
    // ============================================
    $result = [
        'success' => true,
        'error'   => '',
        'files'   => [],
    ];
    
    // ============================================
    // DEFAULT ALLOWED MIME TYPES (EXTENDED LIST)
    // ============================================
    if ($allowed_types === null) {
        $allowed_types = [
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'text/rtf',
            
            // Archives
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-rar',
            'application/x-7z-compressed',
            'application/gzip',
            'application/x-tar',
            
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
            
            // Design Files
            'application/postscript',           // AI, EPS
            'image/vnd.adobe.photoshop',        // PSD
            'application/illustrator',          // AI
            
            // Video
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/webm',
            
            // Audio
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/webm',
            
            // Code/Text
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'text/xml',
            'application/xml',
        ];
    }
    
    // ============================================
    // CALCULATE MAX SIZE IN BYTES
    // ============================================
    $max_size_bytes = $max_size_mb * 1024 * 1024;
    
    // ============================================
    // CHECK IF FILES ARRAY IS VALID
    // ============================================
    if (!isset($files['name']) || !is_array($files['name'])) {
        // Not a multiple file upload structure
        // Treat as "no files provided" rather than error
        return $result;
    }
    
    // ============================================
    // DETERMINE NUMBER OF FILES TO PROCESS
    // ============================================
    $file_count = count($files['name']);
    $count = min($file_count, $max_files);
    
    // ============================================
    // VALIDATE EACH FILE
    // ============================================
    for ($i = 0; $i < $count; $i++) {
        
        // Skip empty upload slots
        if (!isset($files['error'][$i]) || $files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        // --------------------------------------------
        // 1. CHECK UPLOAD ERROR
        // --------------------------------------------
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $result['success'] = false;
            $result['error'] = get_upload_error_message($files['error'][$i], $i + 1);
            return $result;
        }
        
        // --------------------------------------------
        // 2. CHECK FILE SIZE
        // --------------------------------------------
        if (!isset($files['size'][$i]) || $files['size'][$i] > $max_size_bytes) {
            $result['success'] = false;
            $result['error'] = "File " . ($i + 1) . " exceeds {$max_size_mb}MB limit";
            return $result;
        }
        
        // Check for zero-byte files
        if ($files['size'][$i] === 0) {
            $result['success'] = false;
            $result['error'] = "File " . ($i + 1) . " is empty (0 bytes)";
            return $result;
        }
        
        // --------------------------------------------
        // 3. VALIDATE UPLOADED FILE
        // --------------------------------------------
        $tmp_name = $files['tmp_name'][$i] ?? '';
        
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            $result['success'] = false;
            $result['error'] = "Invalid uploaded file for file " . ($i + 1);
            return $result;
        }
        
        // --------------------------------------------
        // 4. DETECT MIME TYPE SAFELY
        // --------------------------------------------
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = '';
        
        if ($finfo) {
            $mime_type = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
        }
        
        if (!$mime_type) {
            $result['success'] = false;
            $result['error'] = "Could not determine file type for file " . ($i + 1);
            return $result;
        }
        
        // --------------------------------------------
        // 5. CHECK MIME TYPE AGAINST ALLOWED LIST
        // --------------------------------------------
        if (!in_array($mime_type, $allowed_types, true)) {
            $result['success'] = false;
            $result['error'] = "File " . ($i + 1) . " type not allowed ({$mime_type})";
            return $result;
        }
        
        // --------------------------------------------
        // 6. SANITIZE ORIGINAL FILENAME
        // --------------------------------------------
        $original_name = $files['name'][$i] ?? ('file_' . ($i + 1));
        $original_name = basename($original_name); // Remove path info
        
        // --------------------------------------------
        // 7. ADD TO VALIDATED FILES
        // --------------------------------------------
        $result['files'][] = [
            'tmp_name'      => $tmp_name,
            'original_name' => $original_name,
            'size'          => $files['size'][$i],
            'mime_type'     => $mime_type,
        ];
    }
    
    return $result;
}

/**
 * Get human-readable upload error message
 * 
 * @param int $error_code PHP upload error code
 * @param int $file_number File number for error message
 * @return string Error message
 */
function get_upload_error_message(int $error_code, int $file_number): string {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => "File {$file_number} exceeds server upload limit",
        UPLOAD_ERR_FORM_SIZE  => "File {$file_number} exceeds form upload limit",
        UPLOAD_ERR_PARTIAL    => "File {$file_number} was only partially uploaded",
        UPLOAD_ERR_NO_FILE    => "No file was uploaded for file {$file_number}",
        UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder for file {$file_number}",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file {$file_number} to disk",
        UPLOAD_ERR_EXTENSION  => "File {$file_number} upload blocked by extension",
    ];
    
    return $messages[$error_code] ?? "Unknown upload error for file {$file_number}";
}

?>
