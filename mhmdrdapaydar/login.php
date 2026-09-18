<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

// Credentials (change these for production)
$USERNAME = "Mhmdrdapaydar";
$PASSWORD = "mohamadrza";  // Change to a stronger password!

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user === $USERNAME && $pass === $PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "نام کاربری یا رمز عبور اشتباه است.";
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Vazirmatn', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #fff;
            overflow-x: hidden;
        }
        
        .background-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
        }
        
        .shape-1 {
            width: 300px;
            height: 300px;
            background: linear-gradient(#00ff6a, #00cc55);
            top: -100px;
            right: -100px;
        }
        
        .shape-2 {
            width: 200px;
            height: 200px;
            background: linear-gradient(#00cc55, #008c3a);
            bottom: -50px;
            left: -50px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            background: rgba(30, 30, 46, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            z-index: 10;
        }
        
        .login-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(to right, #00ff6a, #00cc55);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            font-size: 18px;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-size: 16px;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #00cc55;
            box-shadow: 0 0 0 3px rgba(0, 204, 85, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #00ff6a, #00cc55);
            border: none;
            border-radius: 12px;
            color: #000;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 204, 85, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .login-footer a {
            color: #00cc55;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background: rgba(255, 68, 68, 0.2);
            border: 1px solid rgba(255, 68, 68, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            color: #ff4444;
            animation: shake 0.5s ease;
            display: none;
        }
        
        .security-tip {
            background: rgba(0, 204, 85, 0.1);
            border: 1px solid rgba(0, 204, 85, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            color: #00cc55;
            font-size: 0.85rem;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            margin: 0 auto;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid #00cc55;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 18px;
            z-index: 10;
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 15px;
            }
            
            .login-header {
                padding: 25px 15px;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .login-body {
                padding: 25px 20px;
            }
            
            .input-group input {
                padding: 12px 15px 12px 45px;
                font-size: 14px;
            }
            
            .login-btn {
                padding: 14px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <h1>ورود به پنل مدیریت</h1>
            <p>لطفا اطلاعات حساب خود را وارد کنید</p>
        </div>
        
        <div class="login-body">
            <form method="post" action="" id="loginForm">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="نام کاربری" required>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="رمز عبور" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <button type="submit" class="login-btn" id="loginButton">
                    <span id="buttonText">ورود به سیستم</span>
                </button>
            </form>
            
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i> 
                <span id="errorText"><?= htmlspecialchars($error) ?></span>
            </div>
            
            <div class="security-tip">
                <i class="fas fa-shield-alt"></i> 
                اطمینان حاصل کنید که از یک رمز عبور قوی استفاده می‌کنید
            </div>
            
            <div class="loading" id="loadingIndicator">
                <div class="spinner"></div>
                <p>در حال بررسی اطلاعات...</p>
            </div>
        </div>
        
        <div class="login-footer">
           تمامی حقوق مادی و معنوی متعلق به این سایت است
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingIndicator = document.getElementById('loadingIndicator');
            
            // Show error message if exists
            if(errorText.textContent.trim() !== '') {
                errorMessage.style.display = 'block';
            }
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading indicator
                buttonText.textContent = '';
                loginButton.disabled = true;
                loadingIndicator.style.display = 'block';
                
                // Simulate network delay for demo
                setTimeout(() => {
                    // Submit the form
                    loginForm.submit();
                }, 1500);
            });
            
            // Add animation to button on hover
            loginButton.addEventListener('mouseover', function() {
                this.style.background = 'linear-gradient(to right, #00cc55, #00ff6a)';
            });
            
            loginButton.addEventListener('mouseout', function() {
                this.style.background = 'linear-gradient(to right, #00ff6a, #00cc55)';
            });
        });
    </script>
</body>
</html>