<?php
/**
 * Security Helper Functions - Minimal Version
 * Login Rate Limiting, Session Timeout, Password Recovery
 */

// ============================================================================
// SESSION TIMEOUT MANAGEMENT
// ============================================================================

/**
 * Initialize session timeout tracking
 * Call this after successful login
 */
function initializeSessionTimeout($timeout_minutes = 30) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['session_timeout'] = $timeout_minutes * 60; // Convert to seconds
    $_SESSION['last_activity'] = time();
}

/**
 * Check if session has timed out
 * Returns true if timed out, false if still active
 */
function isSessionTimedOut() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['last_activity']) || !isset($_SESSION['session_timeout'])) {
        return false; // Not yet initialized
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    
    if ($elapsed > $_SESSION['session_timeout']) {
        return true;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Get remaining session time in seconds
 */
function getSessionTimeRemaining() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['last_activity']) || !isset($_SESSION['session_timeout'])) {
        return null;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = $_SESSION['session_timeout'] - $elapsed;
    
    return max(0, $remaining);
}

// ============================================================================
// LOGIN RATE LIMITING
// ============================================================================

/**
 * Check if login is rate limited for an email
 * Returns array: ['limited' => true/false, 'attempts' => int, 'remaining_seconds' => int]
 */
function checkLoginRateLimit($email, $max_attempts = 5, $lockout_minutes = 15) {
    $cache_key = 'login_attempts_' . md5($email);
    $file_path = sys_get_temp_dir() . '/' . $cache_key . '.txt';
    
    $now = time();
    $lockout_time = $lockout_minutes * 60;
    
    // Check if currently in lockout
    if (file_exists($file_path)) {
        $data = unserialize(file_get_contents($file_path));
        
        if (isset($data['lockout_until']) && $now < $data['lockout_until']) {
            $remaining = $data['lockout_until'] - $now;
            return [
                'limited' => true,
                'attempts' => $data['attempts'],
                'remaining_seconds' => $remaining
            ];
        }
        
        // Clear old lockout if expired
        if (isset($data['lockout_until']) && $now >= $data['lockout_until']) {
            $data['attempts'] = 0;
            $data['lockout_until'] = null;
        }
    } else {
        $data = [
            'attempts' => 0,
            'first_attempt' => $now,
            'lockout_until' => null
        ];
    }
    
    return [
        'limited' => false,
        'attempts' => $data['attempts'],
        'remaining_seconds' => 0
    ];
}

/**
 * Record a failed login attempt
 */
function recordFailedLoginAttempt($email, $max_attempts = 5, $lockout_minutes = 15) {
    $cache_key = 'login_attempts_' . md5($email);
    $file_path = sys_get_temp_dir() . '/' . $cache_key . '.txt';
    
    $now = time();
    $lockout_time = $lockout_minutes * 60;
    
    if (file_exists($file_path)) {
        $data = unserialize(file_get_contents($file_path));
    } else {
        $data = [
            'attempts' => 0,
            'first_attempt' => $now,
            'lockout_until' => null
        ];
    }
    
    $data['attempts']++;
    
    if ($data['attempts'] >= $max_attempts) {
        $data['lockout_until'] = $now + $lockout_time;
    }
    
    file_put_contents($file_path, serialize($data));
    chmod($file_path, 0600);
}

/**
 * Clear failed login attempts for an email
 */
function clearLoginAttempts($email) {
    $cache_key = 'login_attempts_' . md5($email);
    $file_path = sys_get_temp_dir() . '/' . $cache_key . '.txt';
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// ============================================================================
// PASSWORD RECOVERY
// ============================================================================

/**
 * Generate password reset token
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Store password reset token in database
 */
function storeResetToken($conn, $email, $token, $expires_minutes = 60) {
    $expiration = date('Y-m-d H:i:s', strtotime("+$expires_minutes minutes"));
    
    $stmt = $conn->prepare(
        "UPDATE members 
         SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL ? MINUTE)
         WHERE email = ?"
    );
    $stmt->bind_param('sss', $token, $expiration, $email);
    
    return $stmt->execute();
}

/**
 * Verify and retrieve password reset token
 */
function verifyResetToken($conn, $token) {
    $stmt = $conn->prepare(
        "SELECT member_id, email, reset_expires 
         FROM members 
         WHERE reset_token = ? AND reset_expires > NOW()"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ?: null;
}

/**
 * Update password and clear reset token
 */
function updatePasswordWithToken($conn, $token, $new_password) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare(
        "UPDATE members 
         SET password = ?, reset_token = NULL, reset_expires = NULL 
         WHERE reset_token = ? AND reset_expires > NOW()"
    );
    $stmt->bind_param('ss', $hashed, $token);
    
    return $stmt->execute() && $stmt->affected_rows > 0;
}

/**
 * Clear reset token for an email
 */
function clearResetToken($conn, $email) {
    $stmt = $conn->prepare(
        "UPDATE members 
         SET reset_token = NULL, reset_expires = NULL 
         WHERE email = ?"
    );
    $stmt->bind_param('s', $email);
    return $stmt->execute();
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * HTML escape helper
 */
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }
    
    return $errors;
}

/**
 * Send email helper
 */
function sendEmail($to, $subject, $html_message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@drivenow.com" . "\r\n";
    
    return mail($to, $subject, $html_message, $headers);
}
?>
