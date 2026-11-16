<?php
declare(strict_types=1);

const NX_LEGACY = [
    '/about.html' => '/about/',
    '/services.html' => '/services/',
    '/team.html' => '/about/team/',
    '/staff-uraiz.html' => '/about/team/syed-ali-uraiz/',
    '/staff-aqsa.html' => '/about/team/aqsa-ejaz/',
    '/staff-ashir.html' => '/about/team/ashir-raza/',
    '/staff-noman.html' => '/about/team/noman-malik/',
    '/index.html' => '/',
];

/** Every public page: path => [priority, builder]. Used for routing and the sitemap. */
function nx_routes(): array
{
    $r = [
        '/' => ['1.0', 'pg_home'],
        '/about/' => ['0.8', 'pg_about'],
        '/about/team/' => ['0.7', 'pg_team'],
        '/services/' => ['0.9', 'pg_services'],
        '/study-abroad/' => ['0.9', 'pg_study_abroad'],
        '/destinations/' => ['0.8', 'pg_destinations'],
        '/business-tax-consultancy/' => ['0.9', 'pg_business_tax'],
        '/partners/' => ['0.6', 'pg_partners'],
        '/success-stories/' => ['0.6', 'pg_success'],
        '/insights/' => ['0.7', 'pg_insights'],
        '/faqs/' => ['0.6', 'pg_faqs'],
        '/contact/' => ['0.8', 'pg_contact'],
        '/book-consultation/' => ['0.9', 'pg_booking'],
        '/privacy-policy/' => ['0.3', 'pg_privacy'],
        '/terms/' => ['0.3', 'pg_terms'],
    ];
    foreach (C('TEAM') as $m) {
        $r["/about/team/{$m['slug']}/"] = ['0.5', fn () => pg_member($m)];
    }
    foreach (C('CATEGORIES') as $c) {
        $r[cat_url($c)] = ['0.8', fn () => pg_category($c)];
        foreach ($c['services'] as $s) {
            $r[svc_url($c, $s)] = ['0.7', fn () => pg_service($c, $s)];
        }
    }
    foreach (C('DESTINATIONS') as $d) {
        $r[dest_url($d)] = ['0.8', fn () => pg_destination($d)];
    }
    foreach (C('ARTICLES') as $a) {
        $r["/insights/{$a['slug']}/"] = ['0.6', fn () => pg_article($a)];
    }
    if (C('GALLERY')) {
        $r['/gallery/'] = ['0.5', 'pg_gallery'];
    }
    return $r;
}

function nx_send(int $status, string $type, string $body): void
{
    http_response_code($status);
    header("Content-Type: $type; charset=utf-8");
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    echo $body;
}

function nx_dispatch(string $uri): void
{
    $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
    $query = parse_url($uri, PHP_URL_QUERY);

    if (isset(NX_LEGACY[$path])) {
        header('Location: ' . NX_LEGACY[$path], true, 301);
        return;
    }
    if ($path === '/sitemap.xml') {
        $SITE = C('SITE');
        $today = gmdate('Y-m-d');
        $urls = '';
        foreach (nx_routes() as $p => [$prio]) {
            $urls .= "<url><loc>{$SITE['url']}{$p}</loc><lastmod>{$today}</lastmod><priority>{$prio}</priority></url>";
        }
        nx_send(200, 'application/xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">{$urls}</urlset>\n");
        return;
    }
    if ($path === '/robots.txt') {
        nx_send(200, 'text/plain', "User-agent: *\nDisallow: /api/\nDisallow: /admin/\n\nSitemap: " . C('SITE')['url'] . "/sitemap.xml\n");
        return;
    }
    // Canonical addresses end with a slash: /about -> /about/
    if ($path !== '/' && !str_ends_with($path, '/') && !str_contains(basename($path), '.')) {
        header('Location: ' . $path . '/' . ($query ? '?' . $query : ''), true, 301);
        return;
    }

    if ($path === '/thank-you/') {
        header('X-Robots-Tag: noindex');
        nx_send(200, 'text/html', render_page($path, pg_thanks()));
        return;
    }
    $routes = nx_routes();
    if (isset($routes[$path])) {
        nx_send(200, 'text/html', render_page($path, ($routes[$path][1])()));
        return;
    }
    nx_send(404, 'text/html', render_page('/404/', pg_404()));
}
