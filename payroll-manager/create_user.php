<?php
// --- ការកំណត់ DATABASE ---
$servername = "localhost";
$username_db = "samann1_payroll-manager";
$password_db = "payroll-manager@2025";
$dbname = "samann1_payroll-manager";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("ការតភ្ជាប់បានបរាជ័យ: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- បញ្ជី USER ដែលត្រូវបង្កើត ---
$users = [
    [
        'username' => 'manager',
        'password' => 'manager123', // កំណត់ពាក្យសម្ងាត់ដំបូង
        'role' => 'manager'
    ],
    [
        'username' => 'account',
        'password' => 'account123', // កំណត់ពាក្យសម្ងាត់ដំបូង
        'role' => 'account'
    ],
    [
        'username' => 'admin_staff',
        'password' => 'admin123', // កំណត់ពាក្យសម្ងាត់ដំបូង
        'role' => 'administration'
    ]
];

// --- កូដសម្រាប់បញ្ចូល USER ទៅក្នុង DATABASE ---
$sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    foreach ($users as $user) {
        // Encrypt the password
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        
        $stmt->bind_param("sss", $user['username'], $hashed_password, $user['role']);
        
        if ($stmt->execute()) {
            echo "User '" . htmlspecialchars($user['username']) . "' ត្រូវបានបង្កើតដោយជោគជ័យ។<br>";
        } else {
            echo "Error creating user '" . htmlspecialchars($user['username']) . "': " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
}

$conn->close();

echo "<hr><strong>សំខាន់: សូមលុប File នេះ (create_user.php) ចេញពី Server របស់អ្នកឥឡូវនេះ!</strong>";
?>