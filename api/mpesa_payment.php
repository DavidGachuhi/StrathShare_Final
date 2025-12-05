<?php

/**
 * STRATHSHARE - M-PESA PAYMENT INTEGRATION
 */



header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/EmailNotifier.php';


// M-PESA CONFIGURATION


// DEMO MODE - Set to false to use real M-Pesa API
define('MPESA_DEMO_MODE', true);

// Daraja API Credentials (Get from https://developer.safaricom.co.ke/)
define('MPESA_CONSUMER_KEY', '4fG1aQnGeRzR7vpPiXAFv02jyHsSwtcadRVCsZeWJmVKcCEM');
define('MPESA_CONSUMER_SECRET', '3aWyFVHu3RAXFPyovO4w4OMh0gk4jAMvksEQoDQL8zPUVo5yEG34PPe9iGWCWhbM');
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Sandbox passkey
define('MPESA_SHORTCODE', '174379'); // Sandbox shortcode

// Environment: 'sandbox' or 'live'
define('MPESA_ENV', 'sandbox');

// Callback URL (must be publicly accessible for real M-Pesa)
// Use ngrok for testing: ngrok http 80
define('MPESA_CALLBACK_URL', 'https://your-domain.com/api/mpesa_callback.php');

// API URLs
if (MPESA_ENV === 'sandbox') {
    define('MPESA_AUTH_URL', 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
} else {
    define('MPESA_AUTH_URL', 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
}

// MAIN HANDLER


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['request_id', 'payer_id', 'receiver_id', 'amount', 'phone_number'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$request_id = intval($input['request_id']);
$payer_id = intval($input['payer_id']);
$receiver_id = intval($input['receiver_id']);
$amount = floatval($input['amount']);
$phone_number = sanitizePhoneNumber($input['phone_number']);

// Validate phone number format
if (!preg_match('/^254[0-9]{9}$/', $phone_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid phone number. Use format: 254XXXXXXXXX'
    ]);
    exit();
}

// Validate amount
if ($amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Amount must be at least KES 1']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify request exists and is awaiting payment
    $check_query = "SELECT r.*, 
                           seeker.first_name as seeker_fname, seeker.last_name as seeker_lname, seeker.user_email as seeker_email,
                           provider.first_name as provider_fname, provider.last_name as provider_lname, provider.user_email as provider_email
                    FROM requests r
                    JOIN users seeker ON r.seeker_id = seeker.user_id
                    JOIN users provider ON r.provider_id = provider.user_id
                    WHERE r.request_id = :request_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':request_id', $request_id);
    $check_stmt->execute();

    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }

    $request = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($request['status'] !== 'awaiting_payment') {
        echo json_encode(['success' => false, 'message' => 'Request is not awaiting payment']);
        exit();
    }

    if ($request['seeker_id'] != $payer_id) {
        echo json_encode(['success' => false, 'message' => 'Only the seeker can make payment']);
        exit();
    }

    // Create transaction record
    $trans_query = "INSERT INTO transactions 
                    (request_id, payer_id, receiver_id, amount, payment_method, status, mpesa_phone_number)
                    VALUES (:request_id, :payer_id, :receiver_id, :amount, 'mpesa', 'pending', :phone)";
    $trans_stmt = $db->prepare($trans_query);
    $trans_stmt->bindParam(':request_id', $request_id);
    $trans_stmt->bindParam(':payer_id', $payer_id);
    $trans_stmt->bindParam(':receiver_id', $receiver_id);
    $trans_stmt->bindParam(':amount', $amount);
    $trans_stmt->bindParam(':phone', $phone_number);
    $trans_stmt->execute();

    $transaction_id = $db->lastInsertId();

    // Process payment (Demo or Real)
    if (MPESA_DEMO_MODE) {
        $result = processDemoPayment($db, $transaction_id, $request_id, $amount, $phone_number, $request);
    } else {
        $result = processRealMpesaPayment($db, $transaction_id, $amount, $phone_number);
    }

    echo json_encode($result);
} catch (PDOException $e) {
    error_log("M-Pesa payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}


// DEMO PAYMENT PROCESSING


function processDemoPayment($db, $transaction_id, $request_id, $amount, $phone_number, $request)
{
    // Generate demo M-Pesa receipt
    $mpesa_receipt = 'DEMO' . strtoupper(substr(md5(time() . $transaction_id), 0, 10));

    // Simulate processing delay (would be instant in real scenario)
    // In frontend, we'll show a popup for 10 seconds

    try {
        $db->beginTransaction();

        // Update transaction to completed
        $update_trans = "UPDATE transactions 
                        SET status = 'completed', 
                            mpesa_reference = :receipt,
                            completed_at = NOW()
                        WHERE transaction_id = :trans_id";
        $stmt = $db->prepare($update_trans);
        $stmt->bindParam(':receipt', $mpesa_receipt);
        $stmt->bindParam(':trans_id', $transaction_id);
        $stmt->execute();

        // Update request to completed
        $update_request = "UPDATE requests 
                  SET status = 'completed', updated_at = NOW()
                  WHERE request_id = :request_id";

        $stmt2 = $db->prepare($update_request);
        $stmt2->bindParam(':request_id', $request_id);
        $stmt2->execute();

        // Provider notification – payment received
        $notif_query = "INSERT INTO notifications 
                        (user_id, type, title, message, reference_id, reference_type)
                        VALUES (:user_id, 'payment_received', 'Payment Received! 💰', :message, :ref_id, 'transaction')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $request['provider_id']);
        $message = "You received KES " . number_format($amount, 2) .
            " from " . $request['seeker_fname'] . " for: " . $request['title'];
        $notif_stmt->bindParam(':message', $message);
        $notif_stmt->bindParam(':ref_id', $transaction_id);
        $notif_stmt->execute();

        // Seeker notification – payment sent
        $notif_query2 = "INSERT INTO notifications 
                        (user_id, type, title, message, reference_id, reference_type)
                        VALUES (:user_id, 'payment_sent', 'Payment Sent ✅', :message, :ref_id, 'transaction')";
        $notif_stmt2 = $db->prepare($notif_query2);
        $notif_stmt2->bindParam(':user_id', $request['seeker_id']);
        $message2 = "Your payment of KES " . number_format($amount, 2) .
            " to " . $request['provider_fname'] . " was successful.";
        $notif_stmt2->bindParam(':message', $message2);
        $notif_stmt2->bindParam(':ref_id', $transaction_id);
        $notif_stmt2->execute();


        $db->commit();

        // Send email notifications
        try {
            $emailer = getEmailNotifier();

            // Email to provider
            $emailer->sendPaymentReceivedNotification(
                $request['provider_email'],
                $request['provider_fname'],
                $request['seeker_fname'] . ' ' . $request['seeker_lname'],
                $request['title'],
                $amount,
                $mpesa_receipt
            );

            // Email to seeker
            $emailer->sendPaymentConfirmationNotification(
                $request['seeker_email'],
                $request['seeker_fname'],
                $request['provider_fname'] . ' ' . $request['provider_lname'],
                $request['title'],
                $amount,
                $mpesa_receipt
            );
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            // Don't fail the transaction if email fails
        }

        // Log the demo payment
        logPayment($transaction_id, $phone_number, $amount, $mpesa_receipt, 'completed', true);

        return [
            'success' => true,
            'message' => 'Payment processed successfully (DEMO MODE)',
            'demo_mode' => true,
            'transaction_id' => $transaction_id,
            'mpesa_receipt' => $mpesa_receipt,
            'amount' => $amount,
            'phone' => $phone_number
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Demo payment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Payment processing failed'];
    }
}

// ===================================================================
// REAL M-PESA STK PUSH
// ===================================================================

function processRealMpesaPayment($db, $transaction_id, $amount, $phone_number)
{
    try {
        // Get access token
        $access_token = getMpesaAccessToken();

        if (!$access_token) {
            throw new Exception('Failed to get M-Pesa access token');
        }

        // Prepare STK Push request
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $stk_data = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int)$amount,
            'PartyA' => $phone_number,
            'PartyB' => MPESA_SHORTCODE,
            'PhoneNumber' => $phone_number,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => 'StrathShare',
            'TransactionDesc' => 'Payment for service - Trans #' . $transaction_id
        ];

        // Send STK Push
        $curl = curl_init(MPESA_STK_URL);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($stk_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $result = json_decode($response, true);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
            // STK Push sent successfully
            // Update transaction with checkout request ID
            $update_query = "UPDATE transactions 
                            SET status = 'processing',
                                mpesa_checkout_request_id = :checkout_id,
                                mpesa_merchant_request_id = :merchant_id
                            WHERE transaction_id = :trans_id";
            $stmt = $db->prepare($update_query);
            $stmt->bindParam(':checkout_id', $result['CheckoutRequestID']);
            $stmt->bindParam(':merchant_id', $result['MerchantRequestID']);
            $stmt->bindParam(':trans_id', $transaction_id);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'STK Push sent! Check your phone to complete payment.',
                'demo_mode' => false,
                'transaction_id' => $transaction_id,
                'checkout_request_id' => $result['CheckoutRequestID']
            ];
        } else {
            throw new Exception($result['errorMessage'] ?? 'STK Push failed');
        }
    } catch (Exception $e) {
        error_log("Real M-Pesa error: " . $e->getMessage());

        // Update transaction to failed
        $fail_query = "UPDATE transactions SET status = 'failed' WHERE transaction_id = :trans_id";
        $fail_stmt = $db->prepare($fail_query);
        $fail_stmt->bindParam(':trans_id', $transaction_id);
        $fail_stmt->execute();

        return [
            'success' => false,
            'message' => 'M-Pesa error: ' . $e->getMessage()
        ];
    }
}

// ===================================================================
// HELPER FUNCTIONS
// ===================================================================

function getMpesaAccessToken()
{
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

    $curl = curl_init(MPESA_AUTH_URL);
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, true);

    return $data['access_token'] ?? null;
}

function sanitizePhoneNumber($phone)
{
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Convert 07... to 2547...
    if (substr($phone, 0, 2) === '07') {
        $phone = '254' . substr($phone, 1);
    }
    // Convert 7... to 2547...
    if (strlen($phone) === 9 && $phone[0] === '7') {
        $phone = '254' . $phone;
    }
    // Convert +254... to 254...
    if (substr($phone, 0, 4) === '+254') {
        $phone = substr($phone, 1);
    }

    return $phone;
}

function logPayment($transaction_id, $phone, $amount, $receipt, $status, $is_demo = false)
{
    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . 'payments_' . date('Y-m-d') . '.log';
    $mode = $is_demo ? 'DEMO' : 'LIVE';
    $log_entry = sprintf(
        "[%s] [%s] Transaction: %d | Phone: %s | Amount: KES %s | Receipt: %s | Status: %s\n",
        date('Y-m-d H:i:s'),
        $mode,
        $transaction_id,
        $phone,
        number_format($amount, 2),
        $receipt,
        $status
    );

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}
