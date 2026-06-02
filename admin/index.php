<?php
require_once __DIR__ . '/../init/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

$role    = strtolower(trim($_SESSION['role'] ?? 'editor'));
$csrf    = $_SESSION['csrf_token'];
$my_id   = $_SESSION['user_id'];
$my_name = $_SESSION['username'] ?? 'User';

$is_admin = in_array($role, ['superadmin', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — OTTKING</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────── -->
<nav class="navbar navbar-dark sticky-top mb-4 py-2">
  <div class="container-fluid px-4">
    <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuDrawer">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand d-flex align-items-center" href="#">
      <i class="fa fa-play-circle text-primary me-2 fs-3"></i>
      <span>OTTKING<span class="text-primary">ADMIN</span></span>
    </a>
    <div class="dropdown">
      <button class="btn btn-primary btn-sm btn-rounded dropdown-toggle px-3" data-bs-toggle="dropdown">
        <i class="fa fa-user-circle me-1"></i> <?= strtoupper($role) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
        <li><a class="dropdown-item" href="#" onclick="openMyPassModal()">
          <i class="fa fa-key me-2"></i>Change Password</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php">
          <i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid px-lg-5">
  <div class="row">

    <!-- ── Desktop Sidebar ───────────────────────────────────── -->
    <div class="col-lg-3 col-md-4 mb-4 d-none d-md-block">
      <div class="card card-sidebar p-3">
        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
          <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-channels" onclick="loadChannels()">
            <i class="fa fa-tv me-2"></i> Channel Management
          </button>
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-categories" onclick="loadCategories()">
            <i class="fa fa-folder me-2"></i> Category List
          </button>
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-notifications" onclick="loadNotifications()">
            <i class="fa fa-bell me-2"></i> Push Notifications
          </button>
          <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-settings">
            <i class="fa fa-cogs me-2"></i> App Settings
          </button>
          <?php if ($is_admin): ?>
          <hr class="my-2 text-secondary">
          <button class="nav-link text-warning" data-bs-toggle="pill" data-bs-target="#tab-flusonic" onclick="loadFlServers()">
            <i class="fa fa-server me-2"></i> Flusonic Servers
          </button>
          <button class="nav-link text-danger" data-bs-toggle="pill" data-bs-target="#tab-users" onclick="loadUsers()">
            <i class="fa fa-user-shield me-2"></i> User Control
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Main Content ──────────────────────────────────────── -->
    <div class="col-lg-9 col-md-8 col-12">
      <div class="tab-content main-content-card shadow-sm">

        <!-- Channels -->
        <div class="tab-pane fade show active" id="tab-channels">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">Live Channels <span id="ch-count" class="count-badge">0</span></h4>
            <button class="btn btn-primary btn-sm btn-rounded" onclick="openChModal()">
              <i class="fa fa-plus me-1"></i> Add Channel
            </button>
          </div>
          <div id="channel-list-container">
            <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i></div>
          </div>
        </div>

        <!-- Categories -->
        <div class="tab-pane fade" id="tab-categories">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">Categories <span id="cat-count" class="count-badge">0</span></h4>
            <button class="btn btn-info text-white btn-sm btn-rounded" onclick="openCatModal()">
              <i class="fa fa-plus me-1"></i> Add Category
            </button>
          </div>
          <div id="category-list-container"></div>
        </div>

        <!-- Notifications -->
        <div class="tab-pane fade" id="tab-notifications">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">Notifications <span id="not-count" class="count-badge">0</span></h4>
            <button class="btn btn-warning btn-sm btn-rounded text-dark" onclick="openNotifyModal()">
              <i class="fa fa-plus me-1"></i> New Message
            </button>
          </div>
          <div id="notify-list-container"></div>
        </div>

        <!-- Settings -->
        <div class="tab-pane fade" id="tab-settings">
          <h4 class="fw-bold mb-4 border-start border-primary border-4 ps-3">App Configuration</h4>
          <form class="ajax-form p-4 bg-light rounded-4 border">
            <input type="hidden" name="action"     value="update_settings">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="row g-4">
              <div class="col-12">
                <label class="form-label small fw-bold text-uppercase">System Logo URL</label>
                <input type="text" name="sys_logo" class="form-control"
                  value="<?= htmlspecialchars(get_conf('sys_logo')) ?>" placeholder="https://example.com/logo.png">
              </div>
              <div class="col-md-6">
                <div class="p-3 bg-white rounded-3 border-start border-primary border-4 shadow-sm">
                  <label class="form-label fw-bold text-primary"><i class="fa fa-mobile-alt me-2"></i>Mobile APK Link</label>
                  <input type="text" name="app_mobile" class="form-control"
                    value="<?= htmlspecialchars(get_conf('app_mobile')) ?>" placeholder="Mobile APK URL">
                </div>
              </div>
              <div class="col-md-6">
                <div class="p-3 bg-white rounded-3 border-start border-danger border-4 shadow-sm">
                  <label class="form-label fw-bold text-danger"><i class="fa fa-tv me-2"></i>Android TV APK Link</label>
                  <input type="text" name="app_tv" class="form-control"
                    value="<?= htmlspecialchars(get_conf('app_tv')) ?>" placeholder="TV APK URL">
                </div>
              </div>
              <div class="col-12 text-end mt-2">
                <button type="submit" class="btn btn-success btn-rounded px-5">
                  <i class="fa fa-save me-2"></i>Save All Settings
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- ── Flusonic Servers ─────────────────────────────── -->
        <?php if ($is_admin): ?>
        <div class="tab-pane fade" id="tab-flusonic">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-warning">
              <i class="fa fa-server me-2"></i>Flusonic Servers <span id="fl-count" class="count-badge bg-warning text-dark">0</span>
            </h4>
            <button class="btn btn-warning btn-sm btn-rounded text-dark" onclick="openFlModal()">
              <i class="fa fa-plus me-1"></i> Add Server
            </button>
          </div>

          <div class="alert alert-info border-0 rounded-3 mb-4 small">
            <i class="fa fa-info-circle me-2"></i>
            <b>Auth Endpoint:</b>
            <code><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/auth/flussonic.php</code>
            — Add this URL to Flusonic's <b>Auth Backend</b>.<br>
            <i class="fa fa-key me-2 mt-2"></i>
            <b>Token API:</b>
            <code>/auth/token.php?slug=channel-slug</code>
            — Mobile app fetches the play token from here.
          </div>

          <div id="fl-server-container">
            <div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-warning"></i></div>
          </div>
        </div>

        <!-- Users -->
        <div class="tab-pane fade" id="tab-users">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0 text-danger">Admin Users</h4>
            <button class="btn btn-danger btn-sm btn-rounded" onclick="openUserModal()">
              <i class="fa fa-plus me-1"></i> Add Admin
            </button>
          </div>
          <div id="user-list-container"></div>
        </div>
        <?php endif; ?>

      </div><!-- tab-content -->
    </div>
  </div>
</div>

<!-- ── Mobile Drawer ────────────────────────────────────────── -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="menuDrawer">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><i class="fa fa-play-circle text-primary me-2"></i>OTTKING ADMIN</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div class="nav flex-column nav-pills p-3" id="mobile-menu" role="tablist">
      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-channels" onclick="loadChannels()">
        <i class="fa fa-tv me-2"></i> Channels
      </button>
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-categories" onclick="loadCategories()">
        <i class="fa fa-folder me-2"></i> Categories
      </button>
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-notifications" onclick="loadNotifications()">
        <i class="fa fa-bell me-2"></i> Notifications
      </button>
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-settings">
        <i class="fa fa-cogs me-2"></i> Settings
      </button>
      <?php if ($is_admin): ?>
      <hr class="my-2">
      <button class="nav-link text-warning" data-bs-toggle="pill" data-bs-target="#tab-flusonic" onclick="loadFlServers()">
        <i class="fa fa-server me-2"></i> Flusonic
      </button>
      <button class="nav-link text-danger" data-bs-toggle="pill" data-bs-target="#tab-users" onclick="loadUsers()">
        <i class="fa fa-user-shield me-2"></i> Users
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ════════════ MODALS ════════════ -->

<!-- Channel Modal -->
<div class="modal fade" id="chModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form" id="chForm">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title fw-bold"><i class="fa fa-tv me-2"></i>Channel Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="save_ch">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id"         id="ch_id">

        <!-- Row 1: Name + Channel Slug -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Channel Name</label>
            <input type="text" name="name" id="ch_name" class="form-control" placeholder="e.g. Somoy TV" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Channel Slug <small class="text-muted">(unique, lowercase)</small></label>
            <input type="text" name="slug" id="ch_slug" class="form-control" placeholder="somoy-tv" required>
          </div>
        </div>

        <!-- Stream Type Toggle -->
        <div class="mb-3">
          <label class="form-label small fw-bold d-block">Stream Type</label>
          <div class="d-flex gap-2">
            <input type="radio" class="btn-check" name="stream_type" id="st_flusonic" value="flusonic" checked>
            <label class="btn btn-outline-warning fw-semibold px-4" for="st_flusonic">
              <i class="fa fa-server me-2"></i>Flusonic Stream
            </label>
            <input type="radio" class="btn-check" name="stream_type" id="st_external" value="external">
            <label class="btn btn-outline-secondary fw-semibold px-4" for="st_external">
              <i class="fa fa-link me-2"></i>External Stream
            </label>
          </div>
        </div>

        <!-- Flusonic Options -->
        <div id="flusonic-fields" class="rounded-3 border border-warning p-3 mb-3" style="background:#fffbf0;">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-bold">Flusonic Server</label>
              <select id="fl_server_select" class="form-select">
                <option value="">— Loading servers... —</option>
              </select>
              <div class="form-text text-muted">Select the Flusonic server this channel belongs to.</div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Flusonic Stream Slug
                <span class="badge bg-warning text-dark ms-1">same as Flusonic stream name</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-warning text-dark fw-bold" id="fl_base_url_preview">http://server/</span>
                <input type="text" id="fl_stream_slug" class="form-control" placeholder="live/channel-name">
              </div>
              <div class="form-text text-muted">Enter the exact stream slug as configured in Flusonic (e.g. <code>live/somoy-tv</code>).</div>
            </div>
            <!-- Hidden: built URL goes here -->
            <input type="hidden" name="url" id="ch_url_flusonic">
          </div>
        </div>

        <!-- External URL Options -->
        <div id="external-fields" class="rounded-3 border border-secondary p-3 mb-3" style="display:none; background:#f8f9fa;">
          <label class="form-label small fw-bold">Streaming URL <small class="text-muted">(HLS / M3U8 / RTMP)</small></label>
          <input type="text" name="url_external" id="ch_url_external" class="form-control"
            placeholder="http://server.com/live/channel/index.m3u8">
          <div class="form-text text-muted">Direct stream URL — token auth is not applicable for external streams.</div>
        </div>

        <!-- Row: Logo + Category -->
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <label class="form-label small fw-bold">Logo URL</label>
            <input type="text" name="logo" id="ch_logo" class="form-control" placeholder="https://link.com/logo.png">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Category</label>
            <select name="cat" id="ch_cat" class="form-select" required>
              <option value="">Select...</option>
            </select>
          </div>
        </div>

        <!-- Row: Status + Token -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Status</label>
            <select name="status" id="ch_status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-md-6" id="token_field_wrap">
            <label class="form-label small fw-bold">Token Security</label>
            <select name="token" id="ch_token" class="form-select">
              <option value="yes">🔒 Enable (Secure)</option>
              <option value="no">🔓 Disable (Open)</option>
            </select>
          </div>
        </div>

      </div><!-- modal-body -->
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="submit" class="btn btn-primary w-100 btn-rounded py-2 shadow">
          <i class="fa fa-save me-2"></i>Save Channel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form">
      <div class="modal-header bg-info text-white border-0">
        <h5 class="modal-title fw-bold">Category Settings</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="save_cat">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id"         id="cat_pk">
        <div class="mb-3">
          <label class="form-label small fw-bold">Category Name</label>
          <input type="text" name="name" id="cat_name" class="form-control" placeholder="e.g. Sports" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Slug (unique)</label>
          <input type="text" name="cat_id" id="cat_uid" class="form-control" placeholder="sports" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Display Order</label>
          <input type="number" name="order" id="cat_order" class="form-control" placeholder="1" min="0">
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-info text-white w-100 btn-rounded">
          <i class="fa fa-save me-2"></i>Save Category
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Notification Modal -->
<div class="modal fade" id="notifyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form">
      <div class="modal-header bg-warning text-dark border-0">
        <h5 class="modal-title fw-bold">Send Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="save_notify">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id"         id="notify_id">
        <div class="mb-3">
          <label class="form-label small fw-bold">Title</label>
          <input type="text" name="title" id="notify_title" class="form-control" placeholder="Update Available!" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Message</label>
          <textarea name="msg" id="notify_msg" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">End Time</label>
          <input type="datetime-local" name="end_time" id="notify_end" class="form-control">
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-warning w-100 btn-rounded fw-bold" id="notify_submit">
          <i class="fa fa-paper-plane me-2"></i>Send
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Flusonic Server Modal -->
<?php if ($is_admin): ?>
<div class="modal fade" id="flModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form">
      <div class="modal-header bg-warning text-dark border-0">
        <h5 class="modal-title fw-bold"><i class="fa fa-server me-2"></i>Flusonic Server</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="save_fl_server">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id"         id="fl_id">
        <div class="mb-3">
          <label class="form-label small fw-bold">Label <small class="text-muted">(e.g. Main Server)</small></label>
          <input type="text" name="label" id="fl_label" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Server IP / Hostname</label>
          <input type="text" name="server_ip" id="fl_ip" class="form-control" placeholder="192.168.1.10 or domain.com" required>
          <div class="form-text text-muted small">The IP from which Flusonic sends auth requests.</div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Server URL</label>
          <input type="text" name="server_url" id="fl_url" class="form-control" placeholder="http://192.168.1.10:8080" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Secret Key</label>
          <div class="input-group">
            <input type="password" name="secret_key" id="fl_key" class="form-control"
              placeholder="Leave blank on edit to keep old key">
            <button class="btn btn-outline-secondary" type="button" onclick="toggleFlKey()">
              <i class="fa fa-eye" id="fl_key_icon"></i>
            </button>
            <button class="btn btn-outline-primary" type="button" onclick="genFlKey()">Generate</button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Status</label>
          <select name="status" id="fl_status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-warning w-100 btn-rounded fw-bold text-dark">
          <i class="fa fa-save me-2"></i>Save Server
        </button>
      </div>
    </form>
  </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title fw-bold">Add New Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="add_user">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-3">
          <label class="form-label small fw-bold">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Password <small class="text-muted">(min 6 characters)</small></label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">Role</label>
          <select name="role" class="form-select">
            <option value="admin">Admin</option>
            <option value="editor">Editor</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-danger w-100 btn-rounded">
          <i class="fa fa-user-plus me-2"></i>Create User
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Change Password Modal -->
<div class="modal fade" id="myPassModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-4 ajax-form">
      <div class="modal-header bg-dark text-white border-0">
        <h5 class="modal-title fw-bold">Change Password</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action"     value="save_user">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id"         id="mypass_id" value="<?= $my_id ?>">
        <div class="mb-3">
          <label class="form-label small fw-bold">Username</label>
          <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($my_name) ?>" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold">New Password <small class="text-muted">(min 6 characters)</small></label>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-dark w-100 btn-rounded">
          <i class="fa fa-save me-2"></i>Update Password
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
  const CSRF = '<?= $csrf ?>';
</script>
<script src="main.js"></script>
</body>
</html>
