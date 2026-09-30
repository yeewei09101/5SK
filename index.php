<?php
// 从 Render 环境变量中获取 Supabase 数据库配置
$host = getenv('DB_HOST'); 
$db   = getenv('DB_NAME') ?: 'postgres'; 
$user = getenv('DB_USER') ?: 'postgres'; 
$pass = getenv('DB_PASS'); 
$port = getenv('DB_PORT') ?: '5432'; 

$message = '';
$msgType = '';
$isLoggedIn = false;
$currentUser = '';

// 开启 Session 用于保存登录状态
session_start();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 检查是否已经登录
if (isset($_SESSION['user'])) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['user'];
}

// --- 处理表单提交 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. 注册逻辑 (Mendaftar)
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $message = "Nama dan kata laluan tidak boleh kosong!";
            $msgType = "error";
        } elseif (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            $message = "Sila muat naik gambar muka depan (Front-facing photo)!";
            $msgType = "error";
        } else {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $message = "Hanya format JPG, PNG atau WEBP sahaja dibenarkan!";
                $msgType = "error";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = "Saiz gambar tidak boleh melebihi 5MB!";
                $msgType = "error";
            } else {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('avatar_') . '.' . $ext;
                $destination = 'uploads/' . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $posX = rand(100, 700);
                    $posY = rand(100, 500);

                    try {
                        $stmt = $pdo->prepare("INSERT INTO characters (username, password, avatar, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hashedPassword, $destination, $posX, $posY]);
                        
                        $message = "Pendaftaran berjaya! Anda kini boleh log masuk.";
                        $msgType = "success";
                    } catch (PDOException $e) {
                        if ($e->getCode() == '23505') {
                            $message = "Nama ini sudah wujud, sila guna nama lain!";
                        } else {
                            $message = "Pendaftaran gagal, sila cuba lagi.";
                        }
                        $msgType = "error";
                        @unlink($destination);
                    }
                } else {
                    $message = "Gagal memuat naik gambar, sila cuba lagi.";
                    $msgType = "error";
                }
            }
        }
    }

    // 2. 登录逻辑 (Log Masuk)
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $message = "Sila masukkan nama dan kata laluan!";
            $msgType = "error";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM characters WHERE username = ?");
            $stmt->execute([$username]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord && password_verify($password, $userRecord['password'])) {
                $_SESSION['user'] = $userRecord['username'];
                $_SESSION['avatar'] = $userRecord['avatar'];
                $isLoggedIn = true;
                $currentUser = $userRecord['username'];
                $message = "Selamat kembali, {$currentUser}!";
                $msgType = "success";
            } else {
                $message = "Nama atau kata laluan salah!";
                $msgType = "error";
            }
        }
    }

    // 3. 退出登录逻辑 (Log Keluar)
    if ($action === 'logout') {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班级纪念空间 - Muka Utama</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; font-weight: 600; color: #888; }
        .tab.active { color: #4f46e5; border-bottom: 2px solid #4f46e5; margin-bottom: -2px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 14px; }
        input[type="text"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #4338ca; }
        .logout-btn { background: #dc2626; }
        .logout-btn:hover { background: #b91c1c; }
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .form-section { display: none; }
        .form-section.active { display: block; }
        .preview-img {
            display: block;
            margin: 10px auto 0;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4f46e5;
            display: none;
        }
        .welcome-box { text-align: center; }
        .welcome-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid #4f46e5; }
    </style>
</head>
<body>

<div class="card">
    <?php if ($isLoggedIn): ?>
        <!-- 登录成功后的状态 -->
        <div class="welcome-box">
            <h2>🎓 Selamat Kembali!</h2>
            <?php if (isset($_SESSION['avatar'])): ?>
                <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" class="welcome-avatar" alt="Avatar">
            <?php endif; ?>
            <p style="font-size: 18px; font-weight: bold; color: #333; margin-bottom: 20px;"><?= htmlspecialchars($currentUser) ?></p>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Anda telah berjaya log masuk ke ruang memori kelas.</p>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Log Keluar (Logout)</button>
            </form>
        </div>
    <?php else: ?>
        <!-- 未登录：显示 登录 / 注册 标签页 -->
        <h2>🎓 Ruang Memori Kelas</h2>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('login')">Log Masuk</div>
            <div class="tab" onclick="switchTab('register')">Membina Character</div>
        </div>

        <!-- 登录表单 -->
        <form id="login-form" class="form-section active" action="" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="login-username">Nama：</label>
                <input type="text" id="login-username" name="username" required placeholder="contoh: Yee">
            </div>
            <div class="form-group">
                <label for="login-password">Kata Laluan：</label>
                <input type="password" id="login-password" name="password" required placeholder="masukan kata laluan">
            </div>
            <button type="submit">Log Masuk</button>
        </form>

        <!-- 注册表单（保留了你原本的文字和样式） -->
        <form id="register-form" class="form-section" action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label for="username">Nama：</label>
                <input type="text" id="username" name="username" required placeholder="contoh: Yee">
            </div>

            <div class="form-group">
                <label for="password">Kata Laluan：</label>
                <input type="password" id="password" name="password" required placeholder="masukan kata laluan">
            </div>

            <div class="form-group">
                <label for="avatar">Sila masuk gambar：</label>
                <input type="file" id="avatar" name="avatar" accept="image/*" required onchange="previewFile(this)">
                <img id="preview" class="preview-img" alt="Gambar muka depan/Front-facing photo">
            </div>

            <button type="submit">Submit</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // 切换登录与注册面板
    function switchTab(tabName) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));

        if (tabName === 'login') {
            document.querySelectorAll('.tab')[0].classList.add('active');
            document.getElementById('login-form').classList.add('active');
        } else {
            document.querySelectorAll('.tab')[1].classList.add('active');
            document.getElementById('register-form').classList.add('active');
        }
    }

    // 图片预览特效
    function previewFile(input) {
        const preview = document.getElementById('preview');
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }
</script>

</body>
</html>