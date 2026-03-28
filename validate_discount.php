<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db-connect.php';

// Only respond to AJAX requests
if (!isset($_POST['action']) || $_POST['action'] !== 'validate_discount') {
    http_response_code(400);
    exit();
}

$code = trim($_POST['referral_code'] ?? '');
$mid  = $_SESSION['member_id'] ?? 0;
$hours = (float)($_POST['hours'] ?? 0);
$price_per_hr = (float)($_POST['price_per_hr'] ?? 0);

$response = [
    'valid' => false,
    'discountedCost' => round($hours * $price_per_hr, 2),
    'message' => ''
];

if (!empty($code) && $hours > 0 && $price_per_hr > 0) {
    // First, check if user has already used a discount before
    $usedChk = $conn->prepare("SELECT discount_used FROM referral_records WHERE referred_user_id = ? AND discount_used = TRUE");
    $usedChk->bind_param("i", $mid);
    $usedChk->execute();
    $usedChk->store_result();
    $hasUsedDiscount = $usedChk->num_rows > 0;
    $usedChk->close();

    if ($hasUsedDiscount) {
        $response['message'] = 'You’ve already used a referral code for your first booking.';
    } else {
        // Check if the code is valid
        $stmt = $conn->prepare("SELECT member_id FROM members WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && $result['member_id'] != $mid) {

            $discountPercent = 15;
            $discountMultiplier = 1 - ($discountPercent / 100);
            $response['valid'] = true;
            $response['discountpercent'] = $discountPercent;
            $response['discountedCost'] = round($hours * $price_per_hr * $discountMultiplier, 2);
            $response['message'] = $discountPercent . '% discount applied!';
        } elseif ($result && $result['member_id'] == $mid) {
            $response['message'] = 'You cannot use your own referral code.';
        } else {
            $response['message'] = 'Invalid referral code.';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();