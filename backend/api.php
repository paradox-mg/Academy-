<?php
// backend/api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ==================== DATABASE CONFIGURATION ====================
// Localhost ke liye
$host = 'localhost';
$dbname = 'sangeet_academy';
$username = 'root';
$password = '';

// InfinityFree ke liye (baad mein change karna)
// $host = 'sql123.infinityfree.com';
// $dbname = 'if0_12345678_sangeet';
// $username = 'if0_12345678';
// $password = 'YourPassword123';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==================== API 1: LOGIN ====================
if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = mysqli_real_escape_string($conn, $data['email']);
    $password = $data['password'];
    
    $sql = "SELECT * FROM students WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        if ($password === $student['password']) {
            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => $student['id'],
                    'name' => $student['name'],
                    'email' => $student['email'],
                    'location' => $student['location']
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Wrong password']);
        }
    } else {
        echo json_encode(['error' => 'Email not found']);
    }
    exit;
}

// ==================== API 2: REGISTER ====================
if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = mysqli_real_escape_string($conn, $data['name']);
    $email = mysqli_real_escape_string($conn, $data['email']);
    $password = mysqli_real_escape_string($conn, $data['password']);
    $location = mysqli_real_escape_string($conn, $data['location']);
    $phone = mysqli_real_escape_string($conn, $data['phone']);
    $join_date = date('Y-m-d');
    
    $sql = "INSERT INTO students (name, email, password, location, phone, join_date) 
            VALUES ('$name', '$email', '$password', '$location', '$phone', '$join_date')";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Student registered successfully! Please login.'
        ]);
    } else {
        echo json_encode(['error' => 'Registration failed: ' . $conn->error]);
    }
    exit;
}

// ==================== API 3: DASHBOARD ====================
if ($method === 'GET' && $action === 'dashboard') {
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    
    if ($studentId === 0) {
        echo json_encode(['error' => 'Student ID required']);
        exit;
    }
    
    $currentMonth = date('F');
    $currentYear = date('Y');
    
    // 3.1 - Current month fee
    $sql = "SELECT * FROM fees WHERE student_id = $studentId AND month = '$currentMonth' AND year = $currentYear";
    $currentFeeResult = $conn->query($sql);
    $currentFee = $currentFeeResult->num_rows > 0 ? $currentFeeResult->fetch_assoc() : ['amount' => 2500, 'status' => 'pending'];
    
    // 3.2 - Total paid
    $sql = "SELECT SUM(amount) as total_paid FROM fees WHERE student_id = $studentId AND status = 'paid'";
    $totalPaidResult = $conn->query($sql);
    $totalPaid = $totalPaidResult->fetch_assoc()['total_paid'] ?: 0;
    
    // 3.3 - Payment history
    $sql = "SELECT * FROM fees WHERE student_id = $studentId ORDER BY year DESC, FIELD(month, 'January','February','March','April','May','June','July','August','September','October','November','December') DESC";
    $historyResult = $conn->query($sql);
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode([
        'student_id' => $studentId,
        'current_month_fee' => $currentFee,
        'total_paid' => $totalPaid,
        'history' => $history
    ]);
    exit;
}

// ==================== API 4: PAY FEE ====================
if ($method === 'POST' && $action === 'pay-fee') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $student_id = (int)$data['student_id'];
    $month = mysqli_real_escape_string($conn, $data['month']);
    $year = (int)$data['year'];
    $amount = (float)$data['amount'];
    $payment_date = date('Y-m-d');
    
    // Check if already paid
    $checkSql = "SELECT * FROM fees WHERE student_id = $student_id AND month = '$month' AND year = $year";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult->num_rows > 0) {
        // Update existing
        $sql = "UPDATE fees SET status = 'paid', payment_date = '$payment_date' WHERE student_id = $student_id AND month = '$month' AND year = $year";
    } else {
        // Insert new
        $sql = "INSERT INTO fees (student_id, month, year, amount, status, payment_date) 
                VALUES ($student_id, '$month', $year, $amount, 'paid', '$payment_date')";
    }
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Fee paid successfully!'
        ]);
    } else {
        echo json_encode(['error' => 'Payment failed: ' . $conn->error]);
    }
    exit;
}

// ==================== API 5: GET TEACHERS ====================
if ($method === 'GET' && $action === 'teachers') {
    $sql = "SELECT * FROM teachers";
    $result = $conn->query($sql);
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    echo json_encode($teachers);
    exit;
}

// ==================== API 6: GET CLASSES ====================
if ($method === 'GET' && $action === 'classes') {
    $sql = "SELECT * FROM classes";
    $result = $conn->query($sql);
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    echo json_encode($classes);
    exit;
}

// ==================== DEFAULT RESPONSE ====================
echo json_encode(['error' => 'Invalid API request']);
?>
