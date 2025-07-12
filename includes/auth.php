<?php
require_once 'config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function register($email, $password, $firstName = '', $lastName = '') {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("INSERT INTO users (email, password, first_name, last_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $hashedPassword, $firstName, $lastName]);
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                return ['success' => true, 'message' => 'Login successful'];
            }
            
            return ['success' => false, 'message' => 'Invalid credentials'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        session_start();
        session_destroy();
    }
    
    public function getCurrentUser() {
        session_start();
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                return $stmt->fetch();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }
    
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']);
    }
}
?>