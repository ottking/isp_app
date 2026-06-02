<?php
/**
 * =====================================================
 * OTTKING - Admin API
 * URL: /admin/api.php
 * =====================================================
 */

error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: text/html; charset=utf-8");

require_once __DIR__ . '/../init/config.php';

// ── লগইন চেক ──────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Error: Session expired! Please login again.");
}

$my_id = (int)$_SESSION['user_id'];
$role  = strtolower(trim($_SESSION['role'] ?? 'editor'));

// ═══════════════════════════════════════════════════════════
// GET — ডাটা পড়া
// ═══════════════════════════════════════════════════════════
if (isset($_GET['get'])) {
    $t = $_GET['get'];

    // ── চ্যানেল লিস্ট ──────────────────────────────────────
    if ($t === 'ch') {
        $res = $db->query("SELECT * FROM channels ORDER BY id DESC");
        echo '<div class="table-responsive">
              <table id="chTable" class="table table-sm table-hover border align-middle">
              <thead class="table-light"><tr>
                <th>#</th><th>Logo</th><th>চ্যানেল</th><th>Status</th><th>Token</th><th>Action</th>
              </tr></thead><tbody>';
        $i = 1;
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $js   = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
            $stBadge = $r['status'] === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            $tkBadge = $r['token_action'] === 'yes'
                ? '<span class="badge bg-primary">🔒 On</span>'
                : '<span class="badge bg-warning text-dark">🔓 Off</span>';
            echo "<tr>
                    <td>$i</td>
                    <td><img src='{$r['logo']}' width='32' height='32' style='border-radius:6px;object-fit:cover'
                        onerror=\"this.src='https://via.placeholder.com/32'\"></td>
                    <td><b>{$r['channel_name']}</b><br>
                        <small class='text-muted'><code>{$r['channel_slug']}</code></small></td>
                    <td>$stBadge</td>
                    <td>$tkBadge</td>
                    <td>
                      <button class='btn btn-sm btn-outline-info me-1' onclick='editCh($js)' title='Edit'>
                        <i class='fa fa-edit'></i></button>
                      <button class='btn btn-sm btn-outline-danger' onclick=\"del('ch',{$r['id']})\" title='Delete'>
                        <i class='fa fa-trash'></i></button>
                    </td>
                  </tr>";
            $i++;
        }
        echo '</tbody></table></div>';
    }

    // ── ক্যাটাগরি ───────────────────────────────────────────
    elseif ($t === 'cat') {
        $res = $db->query("SELECT * FROM categories ORDER BY ordering ASC, id ASC");
        echo '<div class="table-responsive">
              <table id="catTable" class="table table-sm border align-middle">
              <thead class="table-light"><tr>
                <th>#</th><th>Order</th><th>নাম</th><th>Slug</th><th>Action</th>
              </tr></thead><tbody>';
        $i = 1;
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $js = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
            echo "<tr>
                    <td>$i</td>
                    <td><span class='badge bg-light text-dark border'>{$r['ordering']}</span></td>
                    <td><b>{$r['cat_name']}</b></td>
                    <td><code>{$r['cat_id']}</code></td>
                    <td>
                      <button class='btn btn-sm btn-outline-info me-1' onclick='editCat($js)'><i class='fa fa-edit'></i></button>
                      <button class='btn btn-sm btn-outline-danger' onclick=\"del('cat',{$r['id']})\"><i class='fa fa-trash'></i></button>
                    </td>
                  </tr>";
            $i++;
        }
        echo '</tbody></table></div>';
    }

    // ── নোটিফিকেশন ─────────────────────────────────────────
    elseif ($t === 'notify') {
        $res = $db->query("SELECT * FROM notifications ORDER BY id DESC");
        echo '<div class="table-responsive">
              <table id="notTable" class="table table-sm border align-middle">
              <thead class="table-light"><tr>
                <th>#</th><th>Title</th><th>Message</th><th>Ends</th><th>Action</th>
              </tr></thead><tbody>';
        $i = 1;
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $js  = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
            $end = $r['end_time'] ?: '—';
            echo "<tr>
                    <td>$i</td>
                    <td><b>{$r['title']}</b></td>
                    <td class='text-muted small'>{$r['msg']}</td>
                    <td><small>$end</small></td>
                    <td>
                      <button class='btn btn-sm btn-outline-info me-1' onclick='editNotify($js)'><i class='fa fa-edit'></i></button>
                      <button class='btn btn-sm btn-outline-danger' onclick=\"del('notify',{$r['id']})\"><i class='fa fa-trash'></i></button>
                    </td>
                  </tr>";
            $i++;
        }
        echo '</tbody></table></div>';
    }

    // ── Flusonic সার্ভার লিস্ট ─────────────────────────────
    elseif ($t === 'fl_servers' && in_array($role, ['superadmin', 'admin'])) {
        $res = $db->query("SELECT * FROM flusonic_servers ORDER BY id DESC");
        echo '<div class="table-responsive">
              <table id="flTable" class="table table-sm border align-middle">
              <thead class="table-light"><tr>
                <th>#</th><th>Label</th><th>Server IP/Host</th><th>URL</th><th>Status</th><th>Action</th>
              </tr></thead><tbody>';
        $i = 1;
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            // Secret key মাস্ক করা — UI-তে দেখাবো না
            $display = $r;
            unset($display['secret_key']);
            $js = htmlspecialchars(json_encode($display), ENT_QUOTES, 'UTF-8');
            $stBadge = $r['status'] === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            echo "<tr>
                    <td>$i</td>
                    <td><b>{$r['label']}</b></td>
                    <td><code>{$r['server_ip']}</code></td>
                    <td><small class='text-muted'>{$r['server_url']}</small></td>
                    <td>$stBadge</td>
                    <td>
                      <button class='btn btn-sm btn-outline-info me-1' onclick='editFlServer($js)' title='Edit'>
                        <i class='fa fa-edit'></i></button>
                      <button class='btn btn-sm btn-outline-danger' onclick=\"del('fl_server',{$r['id']})\" title='Delete'>
                        <i class='fa fa-trash'></i></button>
                    </td>
                  </tr>";
            $i++;
        }
        echo '</tbody></table></div>';
    }

    // ── Flusonic Servers JSON (for channel modal dropdown) ──
    elseif ($t === 'fl_servers_json') {
        header("Content-Type: application/json; charset=utf-8");
        $res     = $db->query("SELECT id, label, server_ip, server_url FROM flusonic_servers WHERE status='active' ORDER BY id ASC");
        $servers = [];
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $servers[] = $r;
        }
        echo json_encode($servers);
        exit;
    }

    // ── User List ───────────────────────────────────────────
    elseif ($t === 'user' && in_array($role, ['superadmin', 'admin'])) {
        $res = $db->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC");
        echo '<div class="table-responsive">
              <table id="userTable" class="table table-hover border align-middle">
              <thead class="table-light"><tr>
                <th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Action</th>
              </tr></thead><tbody>';
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $u_name     = htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8');
            $u_role_raw = strtolower(trim($r['role']));
            
            // সব পিএইচপি ভার্সনে কাজ করার জন্য সুইচ স্টেটমেন্ট
            switch ($u_role_raw) {
                case 'superadmin':
                    $roleBadge = '<span class="badge bg-danger">Superadmin</span>';
                    break;
                case 'admin':
                    $roleBadge = '<span class="badge bg-primary">Admin</span>';
                    break;
                default:
                    $roleBadge = '<span class="badge bg-secondary">Editor</span>';
                    break;
            }
            $can_del = ($r['id'] !== $my_id)
                && !($role === 'admin' && $u_role_raw === 'superadmin');

            echo "<tr>
                    <td>{$r['id']}</td>
                    <td><b>$u_name</b></td>
                    <td>$roleBadge</td>
                    <td><small class='text-muted'>{$r['created_at']}</small></td>
                    <td>
                      <button class='btn btn-sm btn-outline-dark me-1'
                        onclick=\"editUser({$r['id']},'$u_name')\"><i class='fa fa-key'></i></button>";
            if ($can_del) {
                echo "<button class='btn btn-sm btn-outline-danger'
                        onclick=\"del('user',{$r['id']})\"><i class='fa fa-trash'></i></button>";
            }
            echo "</td></tr>";
        }
        echo '</tbody></table></div>';
    }

    exit;
}

// ═══════════════════════════════════════════════════════════
// POST — ডাটা লেখা
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $a = $_POST['action'] ?? '';

    // ── চ্যানেল সেভ ────────────────────────────────────────
    if ($a === 'save_ch') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $url  = trim($_POST['url']  ?? '');
        $logo = trim($_POST['logo'] ?? '');
        $cat  = trim($_POST['cat']  ?? '');
        $st   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
        $tk   = in_array($_POST['token']  ?? '', ['yes','no'])          ? $_POST['token']  : 'yes';

        if (empty($name) || empty($slug) || empty($url)) {
            die("Error: Name, Slug and URL are required!");
        }

        if ($id > 0) {
            $sql = "UPDATE channels SET channel_name=:n, channel_slug=:s, channel_url=:u,
                    logo=:l, category_id=:c, status=:st, token_action=:tk WHERE id=:id";
        } else {
            $sql = "INSERT INTO channels (channel_name,channel_slug,channel_url,logo,category_id,status,token_action)
                    VALUES (:n,:s,:u,:l,:c,:st,:tk)";
        }
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':n',  $name, SQLITE3_TEXT);
        $stmt->bindValue(':s',  $slug, SQLITE3_TEXT);
        $stmt->bindValue(':u',  $url,  SQLITE3_TEXT);
        $stmt->bindValue(':l',  $logo, SQLITE3_TEXT);
        $stmt->bindValue(':c',  $cat,  SQLITE3_TEXT);
        $stmt->bindValue(':st', $st,   SQLITE3_TEXT);
        $stmt->bindValue(':tk', $tk,   SQLITE3_TEXT);
        if ($id > 0) $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

        $stmt->execute() ? print("Success: Channel saved!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── ক্যাটাগরি সেভ ──────────────────────────────────────
    elseif ($a === 'save_cat') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name']   ?? '');
        $ci   = trim($_POST['cat_id'] ?? '');
        $ord  = isset($_POST['order']) && $_POST['order'] !== '' ? (int)$_POST['order'] : -1;

        if (empty($name) || empty($ci)) die("Error: Name and Slug are required!");

        if ($id > 0) {
            $sql  = "UPDATE categories SET cat_name=:n, cat_id=:ci" . ($ord >= 0 ? ", ordering=:ord" : "") . " WHERE id=:id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            if ($ord < 0) $ord = (int)$db->querySingle("SELECT COALESCE(MAX(ordering),0)+1 FROM categories");
            $stmt = $db->prepare("INSERT INTO categories (cat_name,cat_id,ordering) VALUES (:n,:ci,:ord)");
        }
        $stmt->bindValue(':n',   $name, SQLITE3_TEXT);
        $stmt->bindValue(':ci',  $ci,   SQLITE3_TEXT);
        if ($ord >= 0) $stmt->bindValue(':ord', $ord, SQLITE3_INTEGER);

        $stmt->execute() ? print("Success: Category saved!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── নোটিফিকেশন সেভ ─────────────────────────────────────
    elseif ($a === 'save_notify') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $msg   = trim($_POST['msg']   ?? '');
        $end   = trim($_POST['end_time'] ?? '');

        if (empty($title) || empty($msg)) die("Error: Title and Message are required!");

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE notifications SET title=:t,msg=:m,end_time=:e WHERE id=:id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare("INSERT INTO notifications (title,msg,end_time) VALUES (:t,:m,:e)");
        }
        $stmt->bindValue(':t', $title, SQLITE3_TEXT);
        $stmt->bindValue(':m', $msg,   SQLITE3_TEXT);
        $stmt->bindValue(':e', $end,   SQLITE3_TEXT);

        $stmt->execute() ? print("Success: Notification saved!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── Flusonic সার্ভার সেভ ───────────────────────────────
    elseif ($a === 'save_fl_server' && in_array($role, ['superadmin', 'admin'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label']      ?? '');
        $ip    = trim($_POST['server_ip']  ?? '');
        $url   = trim($_POST['server_url'] ?? '');
        $key   = trim($_POST['secret_key'] ?? '');
        $st    = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (empty($label) || empty($ip) || empty($url)) {
            die("Error: Label, IP and URL are required!");
        }

        // Edit করলে key খালি থাকলে পুরনো key রাখো
        if ($id > 0 && empty($key)) {
            $oldKey = $db->querySingle("SELECT secret_key FROM flusonic_servers WHERE id=$id");
            $key    = $oldKey ?: '';
        }

        if (empty($key)) die("Error: Secret Key is required!");

        if ($id > 0) {
            $sql  = "UPDATE flusonic_servers SET label=:l,server_ip=:ip,server_url=:u,secret_key=:k,status=:st WHERE id=:id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO flusonic_servers (label,server_ip,server_url,secret_key,status)
                 VALUES (:l,:ip,:u,:k,:st)"
            );
        }
        $stmt->bindValue(':l',  $label, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $ip,    SQLITE3_TEXT);
        $stmt->bindValue(':u',  $url,   SQLITE3_TEXT);
        $stmt->bindValue(':k',  $key,   SQLITE3_TEXT);
        $stmt->bindValue(':st', $st,    SQLITE3_TEXT);

        $stmt->execute() ? print("Success: Flusonic server saved!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── সেটিংস আপডেট ───────────────────────────────────────
    elseif ($a === 'update_settings') {
        $skip = ['action', 'csrf_token'];
        foreach ($_POST as $k => $v) {
            if (in_array($k, $skip)) continue;
            if (!is_array($v)) {
                set_conf($k, $v);
            }
        }
        echo "Success: Settings updated!";
    }

    // ── নতুন ইউজার ─────────────────────────────────────────
    elseif ($a === 'add_user' && in_array($role, ['superadmin', 'admin'])) {
        $uname = trim($_POST['username'] ?? '');
        $upass = $_POST['password'] ?? '';
        $urole = strtolower(trim($_POST['role'] ?? 'editor'));

        if (empty($uname) || empty($upass)) die("Error: Username and Password are required!");
        if ($role === 'admin' && $urole === 'superadmin') die("Error: You cannot create a Superadmin user!");
        if (strlen($upass) < 6) die("Error: Password must be at least 6 characters!");

        $hash = password_hash($upass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("INSERT INTO users (username,password,role) VALUES (:u,:p,:r)");
        $stmt->bindValue(':u', $uname, SQLITE3_TEXT);
        $stmt->bindValue(':p', $hash,  SQLITE3_TEXT);
        $stmt->bindValue(':r', $urole, SQLITE3_TEXT);

        $stmt->execute() ? print("Success: New user created!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── পাসওয়ার্ড আপডেট ────────────────────────────────────
    elseif ($a === 'save_user') {
        $uid     = (int)($_POST['id'] ?? 0);
        $newPass = $_POST['password'] ?? '';

        if (strlen($newPass) < 6) die("Error: Password must be at least 6 characters!");

        // নিজের পাসওয়ার্ড বদলাচ্ছে — okay
        // admin অন্যের পাসওয়ার্ড বদলাচ্ছে — check role
        if ($uid !== $my_id) {
            $targetRole = strtolower(trim($db->querySingle("SELECT role FROM users WHERE id=$uid")));
            if ($role === 'editor') die("Error: Permission denied!");
            if ($role === 'admin' && $targetRole === 'superadmin') die("Error: Cannot change Superadmin password!");
        }

        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("UPDATE users SET password=:p WHERE id=:id");
        $stmt->bindValue(':p',  $hash, SQLITE3_TEXT);
        $stmt->bindValue(':id', $uid,  SQLITE3_INTEGER);

        $stmt->execute() ? print("Success: Password updated!") : print("Error: " . $db->lastErrorMsg());
    }

    // ── ডিলিট ───────────────────────────────────────────────
    elseif ($a === 'del') {
        $id   = (int)($_POST['id']   ?? 0);
        $type = $_POST['type'] ?? '';
        $tbl  = '';

        if ($type === 'ch')     $tbl = 'channels';
        if ($type === 'cat')    $tbl = 'categories';
        if ($type === 'notify') $tbl = 'notifications';

        if ($type === 'fl_server' && in_array($role, ['superadmin', 'admin'])) {
            $tbl = 'flusonic_servers';
        }

        if ($type === 'user' && in_array($role, ['superadmin', 'admin'])) {
            if ($id === $my_id) die("Error: You cannot delete yourself!");
            $targetRole = strtolower(trim($db->querySingle("SELECT role FROM users WHERE id=$id")));
            if ($role === 'admin' && $targetRole === 'superadmin') die("Error: Cannot delete a Superadmin!");
            $tbl = 'users';
        }

        if (empty($tbl)) die("Error: Permission denied!");

        $stmt = $db->prepare("DELETE FROM $tbl WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute() ? print("Success: Deleted!") : print("Error: " . $db->lastErrorMsg());
    }

    exit;
}
?>
