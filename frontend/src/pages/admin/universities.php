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

$pageTitle = 'Manage Universities | UniStay Admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_university'])) {
    try {
        usAdminCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        if ($name === '' || $city === '' || strlen($name) > 160 || strlen($city) > 120) {
            throw new InvalidArgumentException('Enter a valid university name and city.');
        }
        $exists = $pdo->prepare('SELECT COUNT(*) FROM `University` WHERE name = ?');
        $exists->execute([$name]);
        if ((int)$exists->fetchColumn() > 0) {
            throw new InvalidArgumentException('This university already exists.');
        }
        // The current schema uses university_id as an explicit primary key.
        // If a university sequence exists, use it; otherwise generate an ID for local demo use.
        try {
            $id = (int) $pdo->query('SELECT NEXTVAL(unistay_university_seq)')->fetchColumn();
        } catch (PDOException $e) {
            $id = (int) $pdo->query('SELECT COALESCE(MAX(university_id),0)+1 FROM `University`')->fetchColumn();
        }
        $stmt = $pdo->prepare('INSERT INTO `University` (university_id, name, city) VALUES (?, ?, ?)');
        $stmt->execute([$id, $name, $city]);
        header('Location: /public/admin/universities.php?added=1', true, 303);
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof PDOException ? 'Unable to save the university. Please retry.' : $e->getMessage();
        error_log('UniStay admin university update: ' . $e->getMessage());
    }
}
$universities = $pdo->query('SELECT university_id, name, city FROM `University` ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="/src/styles/style.css">
    <link rel="stylesheet" href="/assets/css/universities.css">

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
                <h1>Manage Universities</h1>

                <p class="dashboard-welcome">
                    Keep the supported university directory up to date.
                </p>
            </div>

        </section>


        <!-- Add University Form -->
        <section class="admin-panel" style="margin-bottom: 30px; background-color: #fafcff;">

            <div class="admin-panel-header">
                <h2>Add New University</h2>
            </div>

            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="name">University Name</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. University of Moratuwa" required>
                </div>

                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" class="form-control" placeholder="e.g. Moratuwa" required>
                </div>

                <div class="form-group">
                    <button type="submit" name="add_university" class="btn btn-primary" style="padding: 11px 20px;">
                        Add to Directory
                    </button>
                </div>

            </form>

        </section>


        <!-- University Directory Table -->
        <section class="admin-panel">

            <div class="admin-table-wrapper">

                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>University Name</th>
                            <th>City</th>
                            
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($universities as $uni): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($uni['name']); ?></strong>
                                </td>
                                
                                <td>
                                    <?php echo htmlspecialchars($uni['city']); ?>
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
