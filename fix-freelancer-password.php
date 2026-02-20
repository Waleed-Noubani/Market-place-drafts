<?php
/**
 * Fix Freelancer Passwords Script
 * احفظ هذا الملف باسم: fix-freelancer-password.php
 * شغله مرة واحدة من المتصفح لتحديث كل باسوردات الفريلانسرز
 */

require_once 'config/db.inc.php';

echo "<h1>Fix Freelancer Passwords</h1>";
echo "<hr>";

// الباسوورد الجديد للفريلانسرز
$new_password = 'freelancer123';

// ولّد hash جديد
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h3>New Password Info:</h3>";
echo "<p><strong>Plain Password:</strong> $new_password</p>";
echo "<p><strong>Hashed Password:</strong> <code>$hashed_password</code></p>";
echo "<hr>";

try {
    // جيب كل الفريلانسرز
    $stmt = $pdo->query("SELECT user_id, email, first_name, last_name FROM users WHERE role = 'Freelancer'");
    $freelancers = $stmt->fetchAll();
    
    echo "<h3>Freelancers Found: " . count($freelancers) . "</h3>";
    
    if (count($freelancers) > 0) {
        
        // حدّث الباسوورد لكل الفريلانسرز
        $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE role = 'Freelancer'");
        $result = $update_stmt->execute(['password' => $hashed_password]);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ SUCCESS! All freelancer passwords updated!</p>";
            
            echo "<h3>Updated Freelancers:</h3>";
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr><th>Name</th><th>Email</th><th>Password to Use</th></tr>";
            
            foreach ($freelancers as $freelancer) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($freelancer['first_name'] . ' ' . $freelancer['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($freelancer['email']) . "</td>";
                echo "<td style='background: #d4edda; font-weight: bold;'>" . htmlspecialchars($new_password) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<hr>";
            echo "<h2>✅ Now You Can Login With:</h2>";
            echo "<ul style='font-size: 16px;'>";
            foreach ($freelancers as $freelancer) {
                echo "<li><strong>Email:</strong> " . htmlspecialchars($freelancer['email']) . " | <strong>Password:</strong> $new_password</li>";
            }
            echo "</ul>";
            
            echo "<hr>";
            echo "<h3>🧪 Test the passwords now:</h3>";
            
            // اختبار الباسوردات
            $test_stmt = $pdo->query("SELECT email, password FROM users WHERE role = 'Freelancer'");
            $test_users = $test_stmt->fetchAll();
            
            echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
            echo "<tr><th>Email</th><th>Password Verification</th></tr>";
            
            foreach ($test_users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                
                if (password_verify($new_password, $user['password'])) {
                    echo "<td style='background: #d4edda; color: green; font-weight: bold;'>✅ VERIFIED - Password is correct!</td>";
                } else {
                    echo "<td style='background: #f8d7da; color: red; font-weight: bold;'>❌ FAILED - Something is wrong!</td>";
                }
                
                echo "</tr>";
            }
            
            echo "</table>";
            
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ ERROR: Failed to update passwords!</p>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No freelancers found in database!</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #007BFF; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Database Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p style='color: #6C757D; font-size: 12px;'>⚠️ After running this script successfully, you can delete this file for security.</p>";
?>