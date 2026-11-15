<?php 
declare(strict_types=1);

require_once __DIR__ . '/../../../../backend/includes/database.php';
require_once __DIR__ . '/../../../../backend/auth.php';

require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['module_csrf'])) {
    $_SESSION['module_csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['module_csrf'];
$pdo = db();
$errorMessage = '';

function usAdminCsrf(): void
{
    $posted = $_POST['csrf_token'] ?? '';

    if (
        !is_string($posted) ||
        !hash_equals((string) $_SESSION['module_csrf'], $posted)
    ) {
        throw new RuntimeException('The form expired. Refresh and try again.');
    }
}

$pageTitle = 'Manage Listings | UniStay Admin';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'deactivate'
) {
    try {
        usAdminCsrf();

        $id = filter_var(
            $_POST['listing_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$id) {
            throw new InvalidArgumentException('Invalid listing ID.');
        }

        $stmt = $pdo->prepare(
            "UPDATE `BoardingListing`
             SET availability = 'unavailable'
             WHERE listing_id = ?"
        );

        $stmt->execute([(int) $id]);

        header('Location: /public/admin/listings.php?updated=1', true, 303);
        exit;

    } catch (Throwable $e) {
        $errorMessage = $e instanceof PDOException
            ? 'Unable to update the listing right now.'
            : $e->getMessage();

        error_log('UniStay admin listing update: ' . $e->getMessage());
    }
}

$listings = $pdo->query(<<<'SQL'
    SELECT
        l.listing_id,
        l.title,
        l.rent_amount,
        l.availability,
        u.full_name AS owner_name
    FROM `BoardingListing` l
    LEFT JOIN `User` u ON l.owner_id = u.user_id
    ORDER BY l.listing_id DESC
    LIMIT 500
SQL)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="/src/styles/style.css">
    <link rel="stylesheet" href="/assets/css/listings-admin.css">
    <link rel="stylesheet" href="/src/styles/unistay-theme.css">
</head>

<body>

    <?php include __DIR__ . '/../../components/header.php'; ?>

    <main class="admin-page-layout">

        <?php if ($errorMessage !== ''): ?>
            <p class="us-admin-notice us-admin-error" role="alert">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </p>

        <?php elseif (isset($_GET['updated']) || isset($_GET['added'])): ?>
            <p class="us-admin-notice us-admin-success" role="status">
                Changes saved successfully.
            </p>
        <?php endif; ?>

        <div class="container">

            <!-- Page Heading -->
            <section class="dashboard-heading">
                <div>
                    <p class="dashboard-subtitle">UNISTAY MANAGEMENT</p>
                    <h1>Manage Listings</h1>

                    <p class="dashboard-welcome">
                        Monitor and moderate all property listings across the platform.
                    </p>
                </div>
            </section>

            <!-- Listings Table Panel -->
            <section class="admin-panel">
                <div class="admin-table-wrapper">

                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Owner Name</th>
                                <th>Rent Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($listings as $listing): ?>
                                <?php
                                    $status = $listing['availability'] ?? 'unknown';

                                    $statusClass = $status === 'available'
                                        ? 'status-available'
                                        : 'status-unavailable';
                                ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $listing['title'] ?? 'Untitled Listing',
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $listing['owner_name'] ?? 'Unknown owner',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        Rs. <?= number_format(
                                            (float) ($listing['rent_amount'] ?? 0),
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status-tag <?= $statusClass ?>">
                                            <?= htmlspecialchars(
                                                $status,
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form
                                            method="POST"
                                            style="display: inline;"
                                            onsubmit="return confirm('Mark this property unavailable?')"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="listing_id"
                                                value="<?= (int) $listing['listing_id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="deactivate"
                                                class="btn btn-outline"
                                                style="color: #d93025; border-color: #d93025;"
                                                <?= $status === 'unavailable' ? 'disabled' : '' ?>
                                            >
                                                Force Unavailable
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (empty($listings)): ?>
                                <tr>
                                    <td colspan="5">No listings found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div>
            </section>

        </div>
    </main>

    <?php include __DIR__ . '/../../components/footer.php'; ?>

</body>
</html>
