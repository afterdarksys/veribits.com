<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <nav>
        <div class="container">
            <a href="/" class="logo">VeriBits</a>
            <ul>
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li data-auth-item="true"><a href="/login.php">Login</a></li>
                <li data-auth-item="true"><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <div class="form-container">
        <h1 style="text-align: center; margin-bottom: 1rem;">Reset Password</h1>
        <p style="text-align: center; color: var(--text-secondary); margin-bottom: 2rem;">
            Enter your email address and we'll send you a link to reset your password.
        </p>

        <div id="alert-container"></div>

        <form id="reset-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Send Reset Link</button>
        </form>

        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary);">
                Remember your password? <a href="/login.php" style="color: var(--primary-color); text-decoration: none;">Back to Login</a>
            </p>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
            <p style="margin-top: 1rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script src="/assets/js/auth.js?v=<?= time() ?>"></script>
</body>
</html>
