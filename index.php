<?php
// --- 数据库配置 ---
$host = 'localhost';
$db   = '5SK';
$user = 'root';
$pass = 'yeexuanwei091017'; // 根据你的本地或线上数据库密码修改

$message = '';
$msgType = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// --- 处理表单提交 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $message = "昵称和密码不能为空！";
        $msgType = "error";
    } elseif (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        $message = "请上传你的正面照片作为头像！";
        $msgType = "error";
    } else {
        $file = $_FILES['avatar'];
        
        // 校验文件类型 (只允许 JPG, PNG, WEBP)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $message = "只支持 JPG、PNG 或 WEBP 格式的图片！";
            $msgType = "error";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 限制 5MB
            $message = "图片大小不能超过 5MB！";
            $msgType = "error";
        } else {
            // 确保 uploads 文件夹存在
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }

            // 生成唯一文件名防止冲突
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid('avatar_') . '.' . $ext;
            $destination = 'uploads/' . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // 密码安全加密
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // 随机给予一个初始地图坐标 (pos_x, pos_y)
                $posX = rand(100, 700);
                $posY = rand(100, 500);

                try {
                    $stmt = $pdo->prepare("INSERT INTO characters (username, password, avatar, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashedPassword, $destination, $posX, $posY]);
                    
                    $message = "注册成功！你的角色已成功创建。";
                    $msgType = "success";
                } catch (PDOException $e) {
                    // 检查是否昵称重复
                    if ($e->getCode() == 23000) {
                        $message = "该昵称已经被注册了，请换一个名字！";
                    } else {
                        $message = "注册失败，请稍后再试。";
                    }
                    $msgType = "error";
                    // 如果数据库写入失败，删除已上传的图片
                    @unlink($destination);
                }
            } else {
                $message = "图片上传失败，请重试。";
                $msgType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班级纪念空间 - 创建角色</title>
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
        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }
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
    </style>
</head>
<body>

<div class="card">
    <h2>🎓 membina character sendiri</h2>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
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
</div>

<script>
    // 简单的前端图片预览特效
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