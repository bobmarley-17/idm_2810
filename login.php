<?php
// This must be the very first line to start sessions and provide security functions.
require_once 'bootstrap.php';

// If a user is already logged in, they should not see the login page.
// Redirect them immediately to the main dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Manager</title>
    
    <!-- External CSS from Bootstrap for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- All custom styling is contained here -->
    <style>
        /*
        ========================================================================
        == CUSTOMIZATION AREA: Change the values below to brand this page. ==
        ========================================================================
        */
        :root {
            /* 1. Change the main color for the button */
            --primary-color: #005A9C; /* A professional blue. Change to your company's hex color. */
            
            /* 2. Change the background color of the whole page */
            --background-color: #f4f7f6;
            
            /* 3. Change the size of your logo */
            --logo-height: 40px;
            
            /* 4. Change the width of the login box */
            --card-max-width: 1900px;
        }
        /*
        ========================================================================
        */

        body {
            background-color: var(--background-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .login-card {
            width: 25vw;
            max-width: var(--card-max-width); /* Uses the variable from above */
            background: white;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .login-header {
            text-align: center;
            padding: 2.5rem 2rem 1.5rem 2rem;
            border-bottom: 1px solid #efefef;
        }
        .logo {
            height: var(--logo-height); /* Uses the variable from above */
            margin-bottom: 1rem;
        }
        .login-body {
            padding: 2rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: bold;
            padding: 0.75rem;
            transition: opacity 0.2s;
        }
        .btn-primary:hover {
            opacity: 0.9;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .login-footer {
            font-size: 0.8rem;
            text-align: center;
            color: #6c757d;
            margin-top: 1.5rem;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 1rem;
            margin: 0 2rem;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>

<main class="login-container">
    <div class="login-card">
        <div class="login-header">
            <!-- CUSTOMIZE: Put your company's logo file in an 'assets/images/' folder -->
            <img src="undefined.png" alt="Company Logo" class="logo">
            <h4>Identity Manager</h4>
        </div>
        
        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="error-message mt-4">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['login_error']); ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <div class="login-body">
            <form action="handle_login.php" method="POST">
                <?php echo csrf_input(); // Security token ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </div>
            </form>
        </div>
    </div>
    <div class="login-footer">
        <!-- CUSTOMIZE: Update your company name here -->
        &copy; <?php echo date('Y'); ?> United Online, Inc.
    </div>
</main>

</body>
</html>
