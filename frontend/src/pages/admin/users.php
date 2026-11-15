<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../../backend/includes/database.php';
require_once __DIR__ . '/../../../../backend/auth.php';
require_admin();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['module_csrf'])) { $_SESSION['module_csrf'] = bin2hex(random_bytes(32)); }
$csrf = (string) $_SESSION['module_csrf'];
$pdo = db();
$errorMessage = '';
function usAdminCsrf(): void {
    $posted = $_POST['csrf_token'] ?? '';
    if (!is_string($posted) || !hash_equals((string) $_SESSION['module_csrf'], $posted)) {
        throw new RuntimeException('The form expired. Refresh and try again.');
    }
}

$pageTitle = 'Manage Users | UniStay Admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        usAdminCsrf();
        $status = $_POST['status'] ?? '';
        $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!is_string($status) || !in_array($status, ['active','suspended'], true) || !$id) {
            throw new InvalidArgumentException('Choose a valid account and status.');
        }
        if ((int) $id === (int) ($_SESSION['user_id'] ?? 0)) {
            throw new InvalidArgumentException('You cannot suspend your own admin account.');
        }
        // Never modify admin accounts from the public admin table controls.
        $stmt = $pdo->prepare('UPDATE `User` SET status = ? WHERE user_id = ? AND role <> ?');
        $stmt->execute([$status, (int)$id, 'admin']);
        header('Location: /public/admin/users.php?updated=1', true, 303);
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof PDOException ? 'Unable to update the account right now.' : $e->getMessage();
        error_log('UniStay admin user update: ' . $e->getMessage());
    }
}
$users = $pdo->query('SELECT user_id, full_name, email, role, status FROM `User` ORDER BY user_id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="/src/styles/style.css">
    <link rel="stylesheet" href="/assets/css/users.css">

    <link rel="stylesheet" href="/src/styles/unistay-theme.css">
</head>

<body>

<?php include __DIR__ . '/../../components/header.php'; ?>


<main class="admin-page-layout">
    <?php if ($errorMessage !== ''): ?>
      <p class="us-admin-notice us-admin-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php elseif (isset($_GET['updated']) || isset($_GET['added'])): ?>
      <p class="us-admin-notice us-admin-success" role="status">Changes saved successfully.</p>
    <?php endif; ?>

    <div class="container">

        <!-- Page Heading -->
        <section class="dashboard-heading">

            <div>
                <p class="dashboard-subtitle">UNISTAY MANAGEMENT</p>
                <h1>Manage Users</h1>

                <p class="dashboard-welcome">
                    Monitor accounts and manage access statuses.
                </p>
            </div>

        </section>


        <!-- Users Table Panel -->
        <section class="admin-panel">

            <div class="admin-table-wrapper">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Account Role</th>
                            <th>Current Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                
                                <td>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                
                                <td>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php 
                                        $statusClass = ($user['status'] === 'active') ? 'status-active' : 'status-suspended';
                                    ?>
                                    <span class="status-tag <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Update this account status?')">
                                        
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        
                                        <select name="status" <?= $user['role'] === 'admin' ? 'disabled' : '' ?> class="form-control" style="padding: 6px 10px;">
                                            <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>>
                                                Active
                                            </option>
                                            <option value="suspended" <?php echo ($user['status'] == 'suspended') ? 'selected' : ''; ?>>
                                                Suspended
                                            </option>
                                        </select>

                                        <button type="submit" name="update_status" <?= $user['role'] === 'admin' ? 'disabled' : '' ?> class="btn btn-outline" style="padding: 6px 12px;">
                                            Update
                                        </button>

                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

        </section>

    </div>

</main>


<?php include __DIR__ . '/../../components/footer.php'; ?>

</body>
</html>
