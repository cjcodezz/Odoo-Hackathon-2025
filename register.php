<?php
require_once 'includes/auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    if (empty($email) || empty($password) || empty($first_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        $result = $auth->register($email, $password, $first_name, $last_name);
        if ($result['success']) {
            $success = 'Registration successful! You can now sign in.';
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - StackIt</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div style="background: var(--surface-color); border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); padding: 3rem; width: 100%; max-width: 550px; margin: 2rem; position: relative; overflow: hidden;">
        <!-- Decorative background -->
        <div style="position: absolute; top: -50%; left: -50%; width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); opacity: 0.05; border-radius: 50%; transform: rotate(-45deg);"></div>
        
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 3rem; position: relative; z-index: 1;">
            <div style="display: inline-flex; align-items: center; color: var(--primary-color); font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem;">
                <i class="fas fa-comments" style="margin-right: 0.75rem; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                <span style="background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">StackIt</span>
            </div>
            <p style="color: var(--text-secondary); font-size: 1.1rem; font-weight: 500;">Join our community of developers</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fecaca; color: #dc2626; padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 2rem; text-align: center; position: relative; z-index: 1;">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0; color: #166534; padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 2rem; text-align: center; position: relative; z-index: 1;">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 1.5rem;">
                    <a href="login.php" class="btn btn-primary">Sign In Now</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <?php if (!$success): ?>
            <form method="POST" style="position: relative; z-index: 1;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label for="first_name" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-primary);">
                            <i class="fas fa-user" style="color: var(--primary-color);"></i>
                            First Name *
                        </label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-primary);">
                            <i class="fas fa-user" style="color: var(--primary-color);"></i>
                            Last Name
                        </label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-envelope" style="color: var(--primary-color);"></i>
                        Email Address *
                    </label>
                    <div style="position: relative;">
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               style="padding-left: 3rem;">
                        <i class="fas fa-envelope" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"></i>
                    </div>
                </div>

                <div class="form-group" style="position: relative;">
                    <label for="password" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-lock" style="color: var(--primary-color);"></i>
                        Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" required 
                               style="padding-left: 3rem; padding-right: 3rem;" minlength="6">
                        <i class="fas fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"></i>
                        <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.5rem;">
                        Must be at least 6 characters long
                    </div>
                </div>

                <div class="form-group" style="position: relative;">
                    <label for="confirm_password" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-lock" style="color: var(--primary-color);"></i>
                        Confirm Password *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               style="padding-left: 3rem; padding-right: 3rem;" minlength="6">
                        <i class="fas fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"></i>
                        <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; margin-bottom: 2rem; font-weight: 600;">
                    <i class="fas fa-user-plus" style="margin-right: 0.75rem;"></i>
                    Create Account
                </button>
            </form>
        <?php endif; ?>

        <!-- Links -->
        <div style="text-align: center; position: relative; z-index: 1;">
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 1rem;">
                Already have an account? 
                <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                    Sign in here
                </a>
            </p>
            <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;">
                <i class="fas fa-arrow-left"></i>
                Back to StackIt
            </a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = 'var(--error-color)';
                this.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = '';
            }
        });

        // Auto-focus first input
        document.getElementById('first_name').focus();

        // Add floating label effect
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-1px)';
                });
                
                input.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>