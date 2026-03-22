<?php
require_once 'auth.php';
requireLogin();
?>


<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Session Login</title>
</head>

<body>

    <h2>Login</h2>

    <?php if (!isSecure()): ?>

        <form method="post">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>

        <p style="color:red;">
            <?php if (isset($error)) echo $error; ?>
        </p>

    <?php else: ?>
        <?php
        header("Location: dashboard.php");
        exit;
        ?>

    <?php endif; ?>

</body>

</html>