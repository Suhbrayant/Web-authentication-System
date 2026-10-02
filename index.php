<?php

session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
    ];
    $activeForm = $_SESSION['active_form'] ?? 'login';

    unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['active_form']);

    function showError($error) {
        return !empty($error)
            ? "<p class='error-message'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"
            : '';
    }

    function isActiveForm($formName, $activeForm) {
        return $formName === $activeForm ? 'active' : '';
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>form</title>
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>

    <div class="container">
        <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="POST">
                <h2>Login</h2>
                <?= showError($errors['login']); ?>
                <input type="email" name="email" placeholder="enter your email" required>
                <input type="password" name="password" placeholder="enter your password" required>
                <button type="submit" name="login">Login</button>
                <p> Don't have an Account? <a href="#" onclick="showForm('register-form')" >Register</a> </p>
            </form>
        </div>
    </div>
    

    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="login_register.php" method="POST">
                <h2>Register</h2>
                <?= showError($errors['register']); ?>
                <input type="text" name="name" placeholder="enter your name" required>
                <input type="email" name="email" placeholder="enter your email" required>
                <input type="password" name="password" placeholder="enter your password" required>
                <select name="role" required>
                    <option value="">--Select Role--</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" name="register">Register</button>
                <p> already have an Account? <a href="#" onclick="showForm('login-form')">Login</a> </p>
            </form>
        </div>
        <script src="script.js"></script>
</body>
</html>
