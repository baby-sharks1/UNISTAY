<?php
declare(strict_types=1);

// The public route /public/admin/dashboard.php includes this internal template.
$pageTitle = 'Dashboard Overview | UniStay Admin';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../backend/auth.php';

// Keep the project's existing admin-only access control.
require_admin();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

function dashboardEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// The existing /logout.php handler validates this specific session token.
if (empty($_SESSION['module_csrf'])) {
    $_SESSION['module_csrf'] = bin2hex(random_bytes(32));
}
$logoutToken = (string) $_SESSION['module_csrf'];
$adminName = trim((string) ($_SESSION['full_name'] ?? 'Administrator')) ?: 'Administrator';

$userCount = 0;
$activeUsers = 0;
$suspendedUsers = 0;
$listingCount = 0;
$availableListings = 0;
$uniCount = 0;
$recentListings = [];
$recentUsers = [];
$dashboardError = false;

try {
    $pdo = db();

    // Verify the account is still an active administrator in TiDB.
    $adminId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$adminId || $adminId < 1) {
        http_response_code(403);
        exit('Administrator session is invalid. Please sign in again.');
    }
    $adminQuery = $pdo->prepare('SELECT role, status FROM `User` WHERE user_id = ? LIMIT 1');
    $adminQuery->execute([(int) $adminId]);
    $adminAccount = $adminQuery->fetch(PDO::FETCH_ASSOC);
    if (!$adminAccount || $adminAccount['role'] !== 'admin' || $adminAccount['status'] !== 'active') {
        http_response_code(403);
        exit('Administrator access is no longer available.');
    }

    // Exact table names and columns from the UniStay TiDB schema.
    $userStats = $pdo->query(<<<'SQL'
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_count,
               COALESCE(SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END), 0) AS suspended_count
        FROM `User`
        SQL)->fetch(PDO::FETCH_ASSOC);

    $listingStats = $pdo->query(<<<'SQL'
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CASE WHEN availability = 'available' THEN 1 ELSE 0 END), 0) AS available_count
        FROM `BoardingListing`
        SQL)->fetch(PDO::FETCH_ASSOC);

    $userCount = (int) ($userStats['total'] ?? 0);
    $activeUsers = (int) ($userStats['active_count'] ?? 0);
    $suspendedUsers = (int) ($userStats['suspended_count'] ?? 0);
    $listingCount = (int) ($listingStats['total'] ?? 0);
    $availableListings = (int) ($listingStats['available_count'] ?? 0);
    $uniCount = (int) $pdo->query('SELECT COUNT(*) FROM `University`')->fetchColumn();

    // These tables do not require created_at columns. Higher IDs appear first.
    $recentListings = $pdo->query(<<<'SQL'
        SELECT l.listing_id, l.title, l.availability, l.rent_amount,
               u.full_name AS owner_name
        FROM `BoardingListing` AS l
        LEFT JOIN `User` AS u ON u.user_id = l.owner_id
        ORDER BY l.listing_id DESC
        LIMIT 5
        SQL)->fetchAll(PDO::FETCH_ASSOC);

    $recentUsers = $pdo->query(<<<'SQL'
        SELECT user_id, full_name, email, role, status
        FROM `User`
        ORDER BY user_id DESC
        LIMIT 5
        SQL)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('UniStay admin dashboard: ' . $e->getMessage());
    $dashboardError = true;
    http_response_code(503);
}

$availabilityPercent = $listingCount > 0 ? round(100 * $availableListings / $listingCount) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= dashboardEscape($pageTitle) ?></title>
    <link rel="stylesheet" href="/src/styles/style.css">
    <!-- Self-contained Admin styles so no relative dashboard.css path can break the layout. -->
    <style>
        :root { --ad-bg:#f5f7fb; --ad-card:#fff; --ad-text:#18263b; --ad-muted:#64748b; --ad-border:#e4e9f1; --ad-primary:#2563eb; --ad-soft:#edf4ff; }
        *, *::before, *::after { box-sizing:border-box; }
        body.ad-body { margin:0; background:var(--ad-bg); color:var(--ad-text); font:14px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif; }
        .ad-body a { text-decoration:none; }
        .ad-body a:focus-visible,.ad-body button:focus-visible { outline:3px solid #93c5fd; outline-offset:3px; }
        .ad-layout { min-height:100vh; display:grid; grid-template-columns:246px minmax(0,1fr); }
        .ad-sidebar { background:var(--ad-card); border-right:1px solid var(--ad-border); padding:26px 16px; display:flex; flex-direction:column; gap:20px; }
        .ad-brand { color:var(--ad-text); font-size:23px; font-weight:800; letter-spacing:-.7px; padding:0 10px 8px; }
        .ad-brand small { display:block; color:var(--ad-muted); font-weight:750; font-size:10px; letter-spacing:2px; margin-top:1px; }
        .ad-nav { display:grid; gap:5px; }
        .ad-nav a,.ad-logout { display:flex; align-items:center; gap:11px; border-radius:10px; padding:11px 13px; color:#475569; font-weight:650; background:none; border:0; text-align:left; width:100%; font:inherit; cursor:pointer; }
        .ad-nav a:hover,.ad-logout:hover { background:#f1f5f9; color:var(--ad-text); }
        .ad-nav a[aria-current="page"] { background:var(--ad-soft); color:#1d4ed8; font-weight:750; }
        .ad-nav .ad-icon { font-size:18px; width:22px; text-align:center; line-height:1; }
        .ad-side-bottom { margin-top:auto; padding-top:15px; border-top:1px solid var(--ad-border); }
        .ad-logout { color:#b42318; }
        .ad-logout:hover { background:#fff1f0; color:#912018; }
        .ad-account { color:var(--ad-muted); margin:13px 12px 0; font-size:12px; overflow-wrap:anywhere; }
        .ad-main { min-width:0; }
        .ad-topbar { display:flex; justify-content:space-between; align-items:center; min-height:69px; background:var(--ad-card); border-bottom:1px solid var(--ad-border); padding:12px clamp(18px,4vw,44px); gap:12px; }
        .ad-topbar span { color:var(--ad-muted); }
        .ad-topbar strong { color:var(--ad-text); }
        .ad-admin-tag { background:#ecfdf3; color:#147d48; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; white-space:nowrap; }
        .ad-content { max-width:1440px; margin:0 auto; padding:clamp(20px,3vw,40px); }
        .ad-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:27px; }
        .ad-heading .ad-eyebrow { color:var(--ad-primary); letter-spacing:1.5px; text-transform:uppercase; font-size:11px; font-weight:800; margin:0 0 4px; }
        .ad-heading h1 { font-size:clamp(26px,2.7vw,34px); letter-spacing:-1px; margin:0; line-height:1.2; }
        .ad-heading p { color:var(--ad-muted); margin:9px 0 0; }
        .ad-button { color:#fff!important; background:var(--ad-primary); padding:10px 16px; border-radius:9px; font-weight:750; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; border:0; }
        .ad-button:hover { background:#1d4ed8; }
        .ad-alert { background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; padding:13px 16px; border-radius:10px; margin:0 0 22px; }
        .ad-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:24px; }
        .ad-stat { min-width:0; padding:21px; background:var(--ad-card); border:1px solid var(--ad-border); border-radius:14px; box-shadow:0 5px 18px rgba(22,40,70,.025); }
        .ad-stat-head { display:flex; align-items:center; justify-content:space-between; gap:6px; }
        .ad-stat .ad-stat-label { color:var(--ad-muted); margin:0; font-weight:650; }
        .ad-stat .ad-stat-icon { background:var(--ad-soft); border-radius:11px; padding:10px; line-height:1; font-size:21px; }
        .ad-stat h2 { font-size:clamp(27px,2.5vw,35px); letter-spacing:-1px; margin:14px 0 4px; color:var(--ad-text); }
        .ad-stat .ad-stat-caption { margin:0; color:var(--ad-muted); font-size:12px; }
        .ad-stat .ad-stat-caption strong { color:var(--ad-text); }
        .ad-danger { color:#bb3131!important; }
        .ad-progress { height:7px; background:#e7edf7; border-radius:100px; margin-top:14px; overflow:hidden; }
        .ad-progress > span { display:block; height:100%; background:#22a46a; border-radius:100px; }
        .ad-tables { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:20px; align-items:start; }
        .ad-panel { min-width:0; background:var(--ad-card); border:1px solid var(--ad-border); border-radius:14px; overflow:hidden; }
        .ad-panel-head { padding:20px 22px; display:flex; align-items:center; justify-content:space-between; gap:12px; border-bottom:1px solid var(--ad-border); }
        .ad-panel-head h2 { font-size:17px; font-weight:750; letter-spacing:-.3px; margin:0; }
        .ad-panel-head p { margin:3px 0 0; color:var(--ad-muted); font-size:12px; }
        .ad-view-all { color:var(--ad-primary)!important; font-weight:750; font-size:13px; white-space:nowrap; }
        .ad-table-wrap { width:100%; overflow-x:auto; }
        .ad-table { border-collapse:collapse; width:100%; font-size:13px; min-width:390px; }
        .ad-table th,.ad-table td { padding:14px 19px; text-align:left; vertical-align:middle; border-bottom:1px solid #edf1f7; }
        .ad-table th { color:var(--ad-muted); font-weight:700; font-size:11px; letter-spacing:.3px; background:#fbfcfe; }
        .ad-table tbody tr:last-child td { border-bottom:0; }
        .ad-table tbody tr:hover td { background:#fbfdff; }
        .ad-table strong { color:var(--ad-text); font-weight:750; display:block; overflow-wrap:anywhere; }
        .ad-table small { color:var(--ad-muted); display:block; margin-top:3px; }
        .ad-badge { display:inline-block; border-radius:999px; padding:5px 9px; background:#eff2f7; color:#526175; font-weight:750; font-size:11px; white-space:nowrap; text-transform:capitalize; }
        .ad-badge-available,.ad-badge-active { background:#e7f8ef; color:#16834b; }
        .ad-badge-soon { background:#fff3d7; color:#996300; }
        .ad-badge-unavailable,.ad-badge-suspended { background:#fdecec; color:#b42318; }
        .ad-badge-admin { background:#eeeafa; color:#6941c6; }
        .ad-badge-owner { background:#e6f2ff; color:#175cd3; }
        .ad-empty { text-align:center!important; padding:40px 15px!important; color:var(--ad-muted); }
        .ad-footer { padding:20px 40px; color:var(--ad-muted); text-align:center; font-size:12px; }
        .ad-mobile-nav { display:none; }
        @media (max-width:1150px) { .ad-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .ad-tables { grid-template-columns:1fr; } }
        @media (max-width:740px) { .ad-layout { display:block; } .ad-sidebar { display:none; } .ad-topbar { padding:12px 18px; } .ad-content { padding:20px 16px; } .ad-mobile-nav { display:block; background:var(--ad-card); padding:10px 16px; border-bottom:1px solid var(--ad-border); } .ad-mobile-nav summary { cursor:pointer; font-weight:700; } .ad-mobile-nav .ad-nav { margin-top:12px; } .ad-mobile-nav form { margin:0; } .ad-heading { align-items:flex-start; flex-direction:column; } }
        @media (max-width:470px) { .ad-stats { grid-template-columns:1fr; } .ad-panel-head { padding:16px; } .ad-admin-tag { display:none; } }
    </style>
    <link rel="stylesheet" href="/src/styles/unistay-theme.css">
</head>
<body class="ad-body">
<div class="ad-layout">
    <aside class="ad-sidebar" aria-label="Admin navigation">
        <a href="/public/admin/dashboard.php" class="ad-brand">UniStay.<small>ADMIN CONSOLE</small></a>
        <nav class="ad-nav" aria-label="Administration">
            <a href="/public/admin/dashboard.php" aria-current="page"><span class="ad-icon">▦</span> Overview</a>
            <a href="/public/admin/users.php"><span class="ad-icon">♙</span> Manage Users</a>
            <a href="/public/admin/listings.php"><span class="ad-icon">⌂</span> Manage Listings</a>
            <a href="/public/admin/universities.php"><span class="ad-icon">▤</span> Universities</a>
        </nav>
        <div class="ad-side-bottom">
            <nav class="ad-nav" aria-label="Other links">
                <a href="/public/index.php"><span class="ad-icon">↗</span> Public Website</a>
                <form method="POST" action="/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($logoutToken) ?>">
                    <button class="ad-logout" type="submit"><span class="ad-icon">⇥</span> Log out</button>
                </form>
            </nav>
            <div class="ad-account">Signed in as <strong><?= dashboardEscape($adminName) ?></strong></div>
        </div>
    </aside>

    <div class="ad-main">
        <header class="ad-topbar">
            <div><span>Workspace / </span><strong>Dashboard</strong></div>
            <span class="ad-admin-tag">● Administrator</span>
        </header>
        <details class="ad-mobile-nav">
            <summary>☰ Admin navigation</summary>
            <nav class="ad-nav" aria-label="Mobile administration">
                <a href="/public/admin/dashboard.php" aria-current="page">Dashboard</a>
                <a href="/public/admin/users.php">Manage Users</a>
                <a href="/public/admin/listings.php">Manage Listings</a>
                <a href="/public/admin/universities.php">Universities</a>
                <a href="/public/index.php">Public Website</a>
                <form method="POST" action="/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($logoutToken) ?>">
                    <button class="ad-logout" type="submit">Log out</button>
                </form>
            </nav>
        </details>

        <main class="ad-content">
            <section class="ad-heading">
                <div>
                    <p class="ad-eyebrow">UniStay / Management</p>
                    <h1>Platform Overview</h1>
                    <p>A snapshot of UniStay's users, boarding listings, and university directory.</p>
                </div>
                <a class="ad-button" href="/public/admin/users.php">Manage Users</a>
            </section>

            <?php if ($dashboardError): ?>
                <p class="ad-alert" role="alert">Dashboard data couldn't be loaded. Please check the server log and database connection.</p>
            <?php endif; ?>

            <section class="ad-stats" aria-label="Platform statistics">
                <div class="ad-stat">
                    <div class="ad-stat-head"><p class="ad-stat-label">Platform Users</p><span class="ad-stat-icon" aria-hidden="true">👥</span></div>
                    <h2><?= number_format($userCount) ?></h2>
                    <p class="ad-stat-caption"><strong><?= number_format($activeUsers) ?></strong> active · <strong class="ad-danger"><?= number_format($suspendedUsers) ?></strong> suspended</p>
                </div>
                <div class="ad-stat">
                    <div class="ad-stat-head"><p class="ad-stat-label">Boarding Listings</p><span class="ad-stat-icon" aria-hidden="true">🏠</span></div>
                    <h2><?= number_format($listingCount) ?></h2>
                    <p class="ad-stat-caption"><strong><?= number_format($availableListings) ?></strong> currently available (<?= $availabilityPercent ?>%)</p>
                    <div class="ad-progress" role="progressbar" aria-label="Available listings" aria-valuenow="<?= $availabilityPercent ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $availabilityPercent ?>%"></span></div>
                </div>
                <div class="ad-stat">
                    <div class="ad-stat-head"><p class="ad-stat-label">Account Suspensions</p><span class="ad-stat-icon" aria-hidden="true">🛡️</span></div>
                    <h2><?= number_format($suspendedUsers) ?></h2>
                    <p class="ad-stat-caption">Review restricted accounts</p>
                </div>
                <div class="ad-stat">
                    <div class="ad-stat-head"><p class="ad-stat-label">Partner Universities</p><span class="ad-stat-icon" aria-hidden="true">🎓</span></div>
                    <h2><?= number_format($uniCount) ?></h2>
                    <p class="ad-stat-caption">In the university directory</p>
                </div>
            </section>

            <section class="ad-tables" aria-label="Recent platform activity">
                <article class="ad-panel">
                    <div class="ad-panel-head">
                        <div><h2>Recently Added Listings</h2><p>Latest properties by listing ID</p></div>
                        <a class="ad-view-all" href="/public/admin/listings.php">View all →</a>
                    </div>
                    <div class="ad-table-wrap">
                        <table class="ad-table">
                            <thead><tr><th>Property</th><th>Owner</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if (!$recentListings): ?>
                                <tr><td colspan="3" class="ad-empty">No listings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentListings as $listing): ?>
                                    <?php
                                    $listingStatus = (string) ($listing['availability'] ?? 'unknown');
                                    $listingBadgeClass = in_array($listingStatus, ['available', 'soon', 'unavailable'], true) ? ' ad-badge-' . $listingStatus : '';
                                    ?>
                                    <tr>
                                        <td><strong><?= dashboardEscape($listing['title'] ?: 'Boarding #' . $listing['listing_id']) ?></strong><small>Rs. <?= number_format((float) ($listing['rent_amount'] ?? 0)) ?>/month</small></td>
                                        <td><?= dashboardEscape($listing['owner_name'] ?: 'Unknown owner') ?></td>
                                        <td><span class="ad-badge<?= $listingBadgeClass ?>"><?= dashboardEscape($listingStatus) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="ad-panel">
                    <div class="ad-panel-head">
                        <div><h2>Newest Registered Users</h2><p>Latest accounts by user ID</p></div>
                        <a class="ad-view-all" href="/public/admin/users.php">View all →</a>
                    </div>
                    <div class="ad-table-wrap">
                        <table class="ad-table">
                            <thead><tr><th>User</th><th>Role</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if (!$recentUsers): ?>
                                <tr><td colspan="3" class="ad-empty">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                    <?php
                                    $userRole = (string) ($user['role'] ?? 'unknown');
                                    $userStatus = (string) ($user['status'] ?? 'unknown');
                                    $roleBadge = in_array($userRole, ['owner', 'admin'], true) ? ' ad-badge-' . $userRole : '';
                                    $statusBadge = in_array($userStatus, ['active', 'suspended'], true) ? ' ad-badge-' . $userStatus : '';
                                    ?>
                                    <tr>
                                        <td><strong><?= dashboardEscape($user['full_name'] ?: 'Unnamed user') ?></strong><small><?= dashboardEscape($user['email'] ?? '') ?></small></td>
                                        <td><span class="ad-badge<?= $roleBadge ?>"><?= dashboardEscape($userRole) ?></span></td>
                                        <td><span class="ad-badge<?= $statusBadge ?>"><?= dashboardEscape($userStatus) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
        <footer class="ad-footer">© <?= date('Y') ?> UniStay · Admin Console</footer>
    </div>
</div>
</body>
</html>
