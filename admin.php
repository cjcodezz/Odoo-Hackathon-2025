<?php
session_start();

// Database Connection using provided class
class Database {
    private $host = 'localhost';
    private $dbname = 'u564191134_stackk';
    private $username = 'u564191134_stackk';
    private $password = 'Stackit@123';
    private $pdo;

    public function getConnection() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $this->username, $this->password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }
}

$db = (new Database())->getConnection();

// Handle login
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
    } else {
        $error = "Invalid credentials!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Handle add admin
if (isset($_POST['action']) && $_POST['action'] == 'add_admin' && isset($_SESSION['admin_id'])) {
    $email = $_POST['new_email'];
    $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
    $stmt->execute([$email, $password]);
}

// Handle update credentials
if (isset($_POST['action']) && $_POST['action'] == 'update_credentials' && isset($_SESSION['admin_id'])) {
    $email = $_POST['update_email'];
    $password = password_hash($_POST['update_password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE admins SET email = ?, password = ? WHERE id = ?");
    $stmt->execute([$email, $password, $_SESSION['admin_id']]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <style>
        body { font-family: Arial; background: #f2f2f2; padding: 30px; }
        .container { width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
        h2 { text-align: center; }
        form input, form button { padding: 10px; margin: 5px 0; width: 100%; }
        .logout { text-align: right; }
        .error { color: red; }
        .user-list { background: #f9f9f9; padding: 10px; margin-top: 20px; border-radius: 5px; }
        .user-list li { padding: 5px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
<div class="container">
    <?php if (!isset($_SESSION['admin_id'])): ?>
        <h2>Admin Login</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <input type="email" name="email" placeholder="Admin Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    <?php else: ?>
        <div class="logout">
            <a href="?logout=1">Logout</a>
        </div>
        <h2>Admin Dashboard</h2>

        <!-- Add New Admin -->
        <h3>Add New Admin</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_admin">
            <input type="email" name="new_email" placeholder="New Admin Email" required>
            <input type="password" name="new_password" placeholder="New Admin Password" required>
            <button type="submit">Add Admin</button>
        </form>

        <!-- Update Credentials -->
        <h3>Update Your Email/Password</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_credentials">
            <input type="email" name="update_email" placeholder="New Email" required>
            <input type="password" name="update_password" placeholder="New Password" required>
            <button type="submit">Update Credentials</button>
        </form>

        <!-- List of Users -->
        <h3>All Users</h3>
        <div class="user-list">
            <ul>
                <?php
                $users = $db->query("SELECT id, email FROM users ORDER BY id DESC");
                foreach ($users as $user) {
                    echo "<li>User ID: {$user['id']} - {$user['email']}</li>";
                }
                ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
