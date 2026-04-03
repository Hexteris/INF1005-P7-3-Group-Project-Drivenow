<?php
function initializeSessionTimeout($timeout_minutes = 30) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['session_timeout'] = $timeout_minutes * 60; // Convert to seconds
    $_SESSION['last_activity'] = time();
}

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

function clearLoginAttempts($email) {
    $cache_key = 'login_attempts_' . md5($email);
    $file_path = sys_get_temp_dir() . '/' . $cache_key . '.txt';
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

function generateResetToken() {
    try {
        return bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        error_log('Password reset: failed to generate secure token: ' . $e->getMessage());
        $fallback = openssl_random_pseudo_bytes(32);
        return $fallback !== false ? bin2hex($fallback) : '';
    }
}

function storeResetToken($conn, $email, $token, $expires_minutes = 60) {
    $expires_minutes = max(1, (int)$expires_minutes);
    $expiration = date('Y-m-d H:i:s', time() + ($expires_minutes * 60));

    $stmt = $conn->prepare(
        "UPDATE members
         SET reset_token = ?, reset_expires = ?
         WHERE email = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('Password reset: storeResetToken prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('sss', $token, $expiration, $email);

    if (!$stmt->execute()) {
        error_log('Password reset: storeResetToken execute failed for ' . $email . ': ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $updated = $stmt->affected_rows > 0;
    if (!$updated) {
        error_log('Password reset: storeResetToken updated 0 rows for ' . $email);
    }

    $stmt->close();
    return $updated;
}

function verifyResetToken($conn, $token) {
    $stmt = $conn->prepare(
        "SELECT member_id, email, reset_expires
         FROM members
         WHERE reset_token = ? AND reset_expires IS NOT NULL AND reset_expires > NOW()
         LIMIT 1"
    );
    if (!$stmt) {
        error_log('Password reset: verifyResetToken prepare failed: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('s', $token);

    if (!$stmt->execute()) {
        error_log('Password reset: verifyResetToken execute failed: ' . $stmt->error);
        $stmt->close();
        return null;
    }

    $stmt->bind_result($member_id, $email, $reset_expires);
    $row = null;

    if ($stmt->fetch()) {
        $row = [
            'member_id' => $member_id,
            'email' => $email,
            'reset_expires' => $reset_expires,
        ];
    }

    $stmt->close();
    return $row;
}

function updatePasswordWithToken($conn, $token, $new_password) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "UPDATE members
         SET password = ?, reset_token = NULL, reset_expires = NULL
         WHERE reset_token = ? AND reset_expires IS NOT NULL AND reset_expires > NOW()
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('Password reset: updatePasswordWithToken prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('ss', $hashed, $token);

    if (!$stmt->execute()) {
        error_log('Password reset: updatePasswordWithToken execute failed: ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $updated = $stmt->affected_rows > 0;
    if (!$updated) {
        error_log('Password reset: updatePasswordWithToken affected 0 rows for token lookup');
    }

    $stmt->close();
    return $updated;
}

function clearResetToken($conn, $email) {
    $stmt = $conn->prepare(
        "UPDATE members
         SET reset_token = NULL, reset_expires = NULL
         WHERE email = ?
         LIMIT 1"
    );
    if (!$stmt) {
        error_log('Password reset: clearResetToken prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('s', $email);

    if (!$stmt->execute()) {
        error_log('Password reset: clearResetToken execute failed for ' . $email . ': ' . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

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

function sendEmail($to, $subject, $html_message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@drivenow.com" . "\r\n";
    
    return mail($to, $subject, $html_message, $headers);
}
?>
