<?php
Auth::requireAdmin();

$user = Auth::user();

// Handle Form Submissions (Create / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();

    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId === (int)$user['id']) {
            Flash::set('error', 'Anda tidak dapat menghapus akun Anda sendiri.', 'error');
        } else {
            Db::query("DELETE FROM users WHERE id = ?", [$delId]);
            Flash::set('success', 'Pengguna berhasil dihapus.', 'success');
        }
        redirect('/master/users');
    }

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'operator']) ? $_POST['role'] : 'operator';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($username) || empty($email)) {
            Flash::set('error', 'Nama, Username, dan Email wajib diisi.', 'error');
            redirect('/master/users');
        }

        if ($id > 0) {
            // Update
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                Db::query(
                    "UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?",
                    [$name, $username, $email, $hash, $role, $is_active, $id]
                );
            } else {
                Db::query(
                    "UPDATE users SET name = ?, username = ?, email = ?, role = ?, is_active = ? WHERE id = ?",
                    [$name, $username, $email, $role, $is_active, $id]
                );
            }
            Flash::set('success', 'Data pengguna berhasil diperbarui.', 'success');
        } else {
            // Create
            if (empty($password)) {
                Flash::set('error', 'Password wajib diisi untuk pengguna baru.', 'error');
                redirect('/master/users');
            }
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            try {
                Db::query(
                    "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                    [$name, $username, $email, $hash, $role, $is_active]
                );
                Flash::set('success', 'Pengguna baru berhasil ditambahkan.', 'success');
            } catch (PDOException $e) {
                Flash::set('error', 'Gagal menambahkan: Username atau Email mungkin sudah digunakan.', 'error');
            }
        }
        redirect('/master/users');
    }
}

// Fetch all users
$users = Db::fetchAll("SELECT id, name, username, email, role, is_active, last_login, created_at FROM users ORDER BY id ASC");

$breadcrumb = [
    ['label' => 'Master Data', 'url' => '/master'],
    ['label' => 'Manajemen Pengguna']
];
$pageTitle = 'Master Pengguna';

ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Manajemen Pengguna</h1>
    <p class="page-subtitle">Kelola hak akses operator dan administrator sistem</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="openUserModal()">
      <i data-lucide="user-plus"></i> Tambah Pengguna
    </button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-lucide="users"></i> Daftar Akun Pengguna</h2>
    <span class="badge badge-gray"><?= count($users) ?> Total Akun</span>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Pengguna</th>
            <th>Username</th>
            <th>Email</th>
            <th>Peran</th>
            <th>Status</th>
            <th>Terakhir Masuk</th>
            <th style="text-align:right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:10px;">
                <div class="user-avatar" style="width:28px;height:28px;font-size:11px;">
                  <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <div>
                  <div style="font-weight:600"><?= e($u['name']) ?></div>
                  <?php if ((int)$u['id'] === (int)$user['id']): ?>
                    <span class="badge badge-primary fs-12" style="font-size:10px;padding:1px 6px;">Anda</span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><code><?= e($u['username']) ?></code></td>
            <td class="text-muted"><?= e($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-gray' ?>">
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge badge-success">Aktif</span>
              <?php else: ?>
                <span class="badge badge-danger">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="text-muted fs-12">
              <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-' ?>
            </td>
            <td style="text-align:right">
              <div class="table-actions" style="justify-content:flex-end;">
                <button 
                  class="btn btn-sm btn-secondary" 
                  title="Edit pengguna"
                  onclick='editUser(<?= json_encode($u) ?>)'
                >
                  <i data-lucide="edit"></i>
                </button>
                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                <form method="POST" action="/master/users" onsubmit="return confirm('Hapus pengguna <?= e($u['name']) ?>?');" style="display:inline;">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Hapus pengguna">
                    <i data-lucide="trash-2"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Tambah / Edit Pengguna -->
<div class="modal-backdrop" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Tambah Pengguna Baru</h3>
      <button class="modal-close" onclick="closeUserModal()">&times;</button>
    </div>
    <form method="POST" action="/master/users" id="userForm">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="userId" value="0">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="userName">Nama Lengkap <span class="req">*</span></label>
          <input type="text" id="userName" name="name" class="form-control" required placeholder="Contoh: dr. Budi Santoso">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="userLogin">Username <span class="req">*</span></label>
            <input type="text" id="userLogin" name="username" class="form-control" required placeholder="budi_s">
          </div>
          <div class="form-group">
            <label class="form-label" for="userEmail">Email <span class="req">*</span></label>
            <input type="email" id="userEmail" name="email" class="form-control" required placeholder="budi@rsud.id">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="userPassword">Password</label>
            <input type="password" id="userPassword" name="password" class="form-control" placeholder="Biarkan kosong jika tak diubah">
            <div class="form-hint" id="pwHint">Minimal 8 karakter disarankan</div>
          </div>
          <div class="form-group">
            <label class="form-label" for="userRole">Peran (Role)</label>
            <select id="userRole" name="role" class="form-control">
              <option value="operator">Operator (Lihat & Sinkronisasi)</option>
              <option value="admin">Administrator (Akses Penuh)</option>
            </select>
          </div>
        </div>

        <div class="form-group mb-0">
          <label class="checkbox-label">
            <input type="checkbox" name="is_active" id="userActive" value="1" checked>
            <span>Akun Aktif (Dapat masuk ke sistem)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Batal</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openUserModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Pengguna Baru';
    document.getElementById('userId').value = '0';
    document.getElementById('userName').value = '';
    document.getElementById('userLogin').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = true;
    document.getElementById('userRole').value = 'operator';
    document.getElementById('userActive').checked = true;
    document.getElementById('pwHint').textContent = 'Wajib diisi untuk pengguna baru';
    document.getElementById('userModal').classList.add('open');
  }

  function editUser(u) {
    document.getElementById('modalTitle').textContent = 'Edit Pengguna: ' + u.name;
    document.getElementById('userId').value = u.id;
    document.getElementById('userName').value = u.name;
    document.getElementById('userLogin').value = u.username;
    document.getElementById('userEmail').value = u.email;
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('userRole').value = u.role;
    document.getElementById('userActive').checked = u.is_active == 1;
    document.getElementById('pwHint').textContent = 'Kosongkan jika tidak ingin mengganti password';
    document.getElementById('userModal').classList.add('open');
  }

  function closeUserModal() {
    document.getElementById('userModal').classList.remove('open');
  }

  // Close on backdrop click
  document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeUserModal();
  });
</script>

<?php
$content = ob_get_clean();
view('layout/app', compact('pageTitle', 'breadcrumb', 'content'));
