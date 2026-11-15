<?php
// Shared UniStay navigation. "student" remains the database/session role; its URL is now /tenant/.
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
$usPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$usRole = session_status() === PHP_SESSION_ACTIVE ? (string) ($_SESSION['role'] ?? '') : '';
$usLoggedIn = $usRole !== '' && !empty($_SESSION['user_id']);
$usDashboard = match ($usRole) {
    'owner' => '/owner/dashboard.php',
    'student' => '/tenant/dashboard.php',
    'admin' => '/public/admin/dashboard.php',
    default => '/public/login.php',
};
$usMenu = [
    'Home' => '/public/index.php',
    'Find Boarding' => '/public/listings.php',
    'About Us' => '/public/index.php#about',
    'Contact' => '/public/index.php#contact',
];
if ($usLoggedIn) {
    $usMenu = match ($usRole) {
        'owner' => [
            'Dashboard' => '/owner/dashboard.php',
            'My Listings' => '/owner/listings.php',
            'Inquiries' => '/owner/inquiries.php',
            'Tenants' => '/owner/tenants.php',
            'Rent' => '/owner/rent.php',
        ],
        'student' => [
            'Dashboard' => '/tenant/dashboard.php',
            'Browse' => '/tenant/listings.php',
            'My Inquiries' => '/tenant/inquiries.php',
            'My Tenancy' => '/tenant/tenancy.php',
            'Payments' => '/tenant/payments.php',
        ],
        'admin' => [
            'Dashboard' => '/public/admin/dashboard.php',
            'Users' => '/public/admin/users.php',
            'Listings' => '/public/admin/listings.php',
            'Universities' => '/public/admin/universities.php',
        ],
        default => $usMenu,
    };
    if (empty($_SESSION['module_csrf'])) {
        $_SESSION['module_csrf'] = bin2hex(random_bytes(32));
    }
}
$usEscape = static fn (mixed $text): string => htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<header class="site-header">
    <div class="container navbar">
        <a href="/public/index.php" class="logo" aria-label="UniStay home">Uni<span>Stay.</span></a>
        <ul class="nav-links" id="navLinks">
            <?php foreach ($usMenu as $label => $link): ?>
                <li><a href="<?= $usEscape($link) ?>"<?= $usPath === parse_url($link, PHP_URL_PATH) ? ' class="active" aria-current="page"' : '' ?>><?= $usEscape($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="nav-actions">
            <?php if ($usLoggedIn): ?>
                <a class="btn btn-secondary btn-small" href="/profile.php">My Profile</a>
                <form class="us-header-logout" method="post" action="/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $usEscape($_SESSION['module_csrf']) ?>">
                    <button type="submit" class="btn btn-primary btn-small">Log out</button>
                </form>
            <?php else: ?>
                <a href="/public/login.php" class="btn btn-secondary btn-small">Login</a>
                <a href="/public/register.php" class="btn btn-primary btn-small">Get Started</a>
            <?php endif; ?>
        </div>
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">☰</button>
    </div>
</header>
