<?php
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already authenticated
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'admin') {
        header('Location: /STI_Queueing-System/admin-dashboard');
        exit;
    } elseif ($user['role'] === 'doctor') {
        header('Location: /STI_Queueing-System/doctor-dashboard');
        exit;
    } else {
        header('Location: /STI_Queueing-System/staff-dashboard');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login - Hospital Queueing System</title>
    <link rel="stylesheet" href="http://localhost/STI_Queueing-System/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Placeholder -->
            <div class="logo-placeholder">
                <img src="http://localhost/STI_Queueing-System/images/hospital_logo.png" alt="Hospital Logo" class="logo">
            </div>

            <!-- Login Header -->
            <div class="login-header">
                <h1>Hospital Portal</h1>
                <p>Admin • Staff • Doctor Access</p>
            </div>

            <!-- Login Form -->
            <form class="login-form">
                <!-- Username Input -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username" 
                        required
                        autocomplete="username"
                    >
                </div>

                <!-- Password Input -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                        autocomplete="current-password"
                    >
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>
    </div>

    <script src="http://localhost/STI_Queueing-System/js/staff-login.js?v=<?php echo time(); ?>"></script>
</body>
</html>
