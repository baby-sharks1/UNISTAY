<?php
/**
 * UniStay Owner + Tenant presentation adapter.
 * One self-contained portal stylesheet stabilizes every module page.
 * Backend role stays "student"; the public route is "/tenant/".
 */
function unistay_themed_module_header(string $title, string $role): void
{
    if (!function_exists('module_header')) {
        throw new RuntimeException('UniStay module_header() must be loaded before the portal theme.');
    }

    ob_start();
    try {
        module_header($title, $role);
        $html = (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }

    // Only URL and display names change; database/auth role stays "student".
    $html = str_replace('/student/', '/tenant/', $html);
    if ($role === 'student') {
        $html = str_replace('UniStay <span>Student</span>', 'UniStay <span>Tenant</span>', $html);
    }

    // Drop only the core CSS links. Preserve extra profile/rent styles if present.
    $coreCss = [
        '/src/styles/style.css',
        '/assets/css/unistay-modules.css',
        '/assets/css/unistay-dashboard.css',
        '/src/styles/unistay-portal.css',
    ];
    $html = preg_replace_callback(
        '~<link\b[^>]*>~i',
        static function (array $match) use ($coreCss): string {
            foreach ($coreCss as $path) {
                if (str_contains($match[0], $path)) {
                    return '';
                }
            }
            return $match[0];
        },
        $html
    ) ?? $html;

    $version = 'unistay-portal-sitewide-v3-20261115';
    $cssLinks = '<link rel="stylesheet" href="/src/styles/style.css?v=' . $version . '">' . "\n"
        . '<link rel="stylesheet" href="/src/styles/unistay-portal.css?v=' . $version . '">' . "\n";

    // Make absolutely sure the bundle loads AFTER every other CSS file.
    $html = preg_replace('~</head>~i', $cssLinks . '</head>', $html, 1) ?? $html;

    // Add the existing CSRF-protected sign-out form if the backend header lacks it.
    if (session_status() === PHP_SESSION_ACTIVE
        && stripos($html, '</nav>') !== false
        && !str_contains($html, 'action="/logout.php"')
    ) {
        if (empty($_SESSION['module_csrf'])) {
            $_SESSION['module_csrf'] = bin2hex(random_bytes(32));
        }
        $token = htmlspecialchars(
            (string) $_SESSION['module_csrf'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $logout = '<form method="post" action="/logout.php" class="us-portal-logout">'
            . '<input type="hidden" name="csrf_token" value="' . $token . '">'
            . '<button type="submit" class="us-portal-logout-button">Log out</button>'
            . '</form>';
        $html = preg_replace('~</nav>~i', $logout . '</nav>', $html, 1) ?? $html;
    }

    echo $html;
}
