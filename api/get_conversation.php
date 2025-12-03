<?php
header("Content-Type: application/json");

// Include the database configuration
require_once "../config/database.php";

// Connect to the database using MySQLi (assuming database.php defines DB_HOST, DB_NAME, DB_USER, DB_PASS)
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $mysqli->connect_error]);
    exit;
}

// Get user IDs from GET parameters
$user1 = intval($_GET['user1_id'] ?? 0);
$user2 = intval($_GET['user2_id'] ?? 0);

if (!$user1 || !$user2) {
    echo json_encode(["success" => false, "message" => "Missing user IDs"]);
    exit;
}

// Prepare the SQL query to prevent SQL injection
$sql = "
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?)
       OR (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
";

// Prepare statement
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $mysqli->error]);
    exit;
}

// Bind parameters (i for integer)
$stmt->bind_param("iiii", $user1, $user2, $user2, $user1);

// Execute the query
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Execute failed: " . $stmt->error]);
    exit;
}

// Get result
$result = $stmt->get_result();

// Fetch all messages
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// Close statement and connection
$stmt->close();
$mysqli->close();

// Output the result
echo json_encode(["success" => true, "messages" => $messages]);
