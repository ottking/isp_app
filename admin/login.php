<?php
/**
 * OTTKING Admin Login
 */
require_once __DIR__ . '/../init/config.php';

// ইতিমধ্যে লগইন থাকলে রিডাইরেক্ট
if (isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Brute-force: সামান্য delay
    usleep(200000); // 0.2s

    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (empty($user) || empty($pass)) {
        $error = "Username ও Password দিন।";
    } elseif ($user === ROOT_USER && $pass === ROOT_PASS) {
        // Root login
        session_regenerate_id(true);
        $_SESSION['user_id']  = 0;
        $_SESSION['username'] = ROOT_USER;
        $_SESSION['role']     = 'superadmin';
        header("Location: index.php"); exit;
    } else {
        // DB login
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
        $stmt->bindValue(':u', $user, SQLITE3_TEXT);
        $res  = $stmt->execute();
        $data = $res->fetchArray(SQLITE3_ASSOC);

        if ($data && password_verify($pass, $data['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $data['id'];
            $_SESSION['username'] = $data['username'];
            $_SESSION['role']     = !empty($data['role']) ? trim($data['role']) : 'editor';
            header("Location: index.php"); exit;
        } else {
            $error = "ইউজারনেম বা পাসওয়ার্ড ভুল!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — OTTKING</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
  body{background:linear-gradient(135deg,#1a1c2e 0%,#4361ee 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
  .login-card{max-width:420px;width:100%;border:none;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
  .brand-icon{width:70px;height:70px;background:linear-gradient(135deg,#4361ee,#3f37c9);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
  .form-control{border-radius:10px;padding:12px 16px;border:1.5px solid #dee2e6}
  .form-control:focus{border-color:#4361ee;box-shadow:0 0 0 .2rem rgba(67,97,238,.2)}
  .btn-login{background:linear-gradient(135deg,#4361ee,#3f37c9);border:none;border-radius:10px;padding:12px;font-weight:700;letter-spacing:.5px}
  .btn-login:hover{opacity:.9}
</style>
</head>
<body>
<div class="card login-card">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="brand-icon">
        <i class="fa fa-play text-white fs-3" style="margin-left:4px"></i>
      </div>
      <h4 class="fw-bold mb-0">OTTKING <span class="text-primary">ADMIN</span></h4>
      <p class="text-muted small">স্বাগতম! লগইন করুন</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small text-center rounded-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb-3">
        <label class="form-label small fw-bold text-muted text-uppercase">Username</label>
        <input type="text" name="username" class="form-control" placeholder="Username দিন" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label small fw-bold text-muted text-uppercase">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Password দিন" required>
      </div>
      <button type="submit" class="btn btn-login btn-primary w-100 text-white">
        <i class="fa fa-sign-in-alt me-2"></i> লগইন করুন
      </button>
    </form>
  </div>
  <div class="card-footer text-center small text-muted py-3">
    &copy; <?= date('Y') ?> OTTKING Admin Panel
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
