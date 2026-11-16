<?php
declare(strict_types=1);

// ---------------------------------------------------------------- basics
function C(string $key)
{
    return nx_content()[$key];
}

/** Lets heredocs call functions: {$_(icon("x"))}. */
function nx_id($x)
{
    return $x;
}

function e($text): string
{
    // Same escaping as Python's html.escape(quote=True).
    return str_replace('&#039;', '&#x27;', htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
}

function qp(string $s): string
{
    return str_replace('%7E', '~', urlencode($s));
}

function asset_version(): string
{
    static $v = null;
    if ($v === null) {
        $h = '';
        foreach (['css/style.css', 'js/script.js'] as $p) {
            $h .= str_replace("\r\n", "\n", (string) @file_get_contents(NX_ROOT . '/' . $p));
        }
        $v = substr(md5($h), 0, 8);
    }
    return $v;
}

function svc_url(array $c, array $s): string
{
    return "/services/{$c['slug']}/{$s['slug']}/";
}

function cat_url(array $c): string
{
    return "/services/{$c['slug']}/";
}

function dest_url(array $d): string
{
    return "/study-abroad/{$d['slug']}/";
}

const NUMBER_WORDS = ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
    'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen', 'Twenty'];

function num_word(int $n): string
{
    return NUMBER_WORDS[$n] ?? (string) $n;
}

function join_words(array $w): string
{
    $n = count($w);
    if ($n === 0) {
        return '';
    }
    return $n === 1 ? $w[0] : implode(', ', array_slice($w, 0, -1)) . ' and ' . $w[$n - 1];
}

function long_date(string $iso): string
{
    return date('j F Y', strtotime($iso . ' 12:00:00'));
}

function highlight(string $t): string
{
    return preg_replace('/\*([^*]+)\*/', '<span class="grad">$1</span>', e($t));
}

function md_inline(string $t): string
{
    $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($t));
    return preg_replace_callback('/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]*)\)/', function ($m) {
        $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ext = str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
        return '<a href="' . e($href) . '"' . $ext . '>' . $m[1] . '</a>';
    }, $t);
}

/** Article format: blank lines between blocks, '## ' headings, '- ' bullets, '1. ' numbered lists, **bold**, [links](url). */
function render_body(string $text): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $text))) as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
        if (!$lines) {
            continue;
        }
        if (str_starts_with($lines[0], '### ')) {
            $out .= '<h3>' . md_inline(substr($lines[0], 4)) . '</h3>';
            array_shift($lines);
        } elseif (str_starts_with($lines[0], '#')) {
            $out .= '<h2>' . md_inline(trim(ltrim($lines[0], '#'))) . '</h2>';
            array_shift($lines);
        }
        if (!$lines) {
            continue;
        }
        if (count(array_filter($lines, fn ($l) => in_array(substr($l, 0, 2), ['- ', '* '], true))) === count($lines)) {
            $out .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . md_inline(substr($l, 2)) . '</li>', $lines)) . '</ul>';
        } elseif (count(array_filter($lines, fn ($l) => preg_match('/^\d+[.)] /', $l))) === count($lines)) {
            $out .= '<ol>' . implode('', array_map(fn ($l) => '<li>' . md_inline(preg_replace('/^\d+[.)] /', '', $l)) . '</li>', $lines)) . '</ol>';
        } else {
            $out .= '<p>' . implode('<br>', array_map('md_inline', $lines)) . '</p>';
        }
    }
    return $out;
}

function icon(string $name, string $cls = 'icon'): string
{
    if ($name === 'whatsapp') {
        return NX_WHATSAPP_SVG;
    }
    $d = NX_ICON_PATHS[$name] ?? NX_ICON_PATHS['check'];
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

function flag(string $code, int $w = 40, string $cls = 'flag'): string
{
    $h = (int) ($w * 0.75);
    if ($code === '' || !is_file(NX_ROOT . "/assets/img/flags/$code.webp")) {
        return '<span class="' . $cls . ' flag-none" style="width:' . $w . 'px;height:' . $h . 'px">' . icon('globe') . '</span>';
    }
    $src = "/assets/img/flags/$code" . ($w > 64 ? '-lg' : '') . '.webp';
    return '<img class="' . $cls . '" src="' . $src . '" width="' . $w . '" height="' . $h . '" alt="" loading="lazy" decoding="async">';
}

function wa_link(string $text = 'Hello Nexbridge Solutions, I would like to ask about your services.'): string
{
    $wa = C('SITE')['whatsapp'];
    return $wa === '' ? '' : "https://wa.me/$wa?text=" . qp($text);
}

// ---------------------------------------------------------------- navigation
function nav_items(): array
{
    $CAT = C('CAT_BY_SLUG');
    $edu = $CAT['education-study-abroad'];
    $lang = $CAT['language-test-preparation'];
    $biz = $CAT['business-corporate'];
    $tax = $CAT['tax-legal'];
    $cols = [];
    foreach (C('CATEGORIES') as $c) {
        $links = array_map(fn ($s) => [$s['name'], svc_url($c, $s)], array_slice($c['services'], 0, 5));
        $links[] = ['All ' . count($c['services']) . ' services', cat_url($c)];
        $cols[] = [$c['short_name'], cat_url($c), $links];
    }
    $about = [['About Nexbridge', '/about/'], ['Our Team', '/about/team/'], ['Success Stories', '/success-stories/'], ['Knowledge Center', '/insights/']];
    if (C('GALLERY')) {
        $about[] = ['Gallery', '/gallery/'];
    }
    $about[] = ['FAQs', '/faqs/'];
    $study = array_merge([['Study abroad overview', '/study-abroad/']],
        array_map(fn ($s) => [$s['name'], svc_url($edu, $s)], array_slice($edu['services'], 0, 8)),
        [['All education services', cat_url($edu)]]);
    return [
        ['label' => 'Home', 'href' => '/'],
        ['label' => 'About Us', 'href' => '/about/', 'menu' => [['', '', $about]]],
        ['label' => 'Consultancy Services', 'href' => '/services/', 'mega' => true, 'menu' => $cols],
        ['label' => 'Study Abroad', 'href' => '/study-abroad/', 'menu' => [['', '', $study]]],
        ['label' => 'Business & Tax', 'href' => '/business-tax-consultancy/', 'mega' => true, 'menu' => [
            [$biz['short_name'], cat_url($biz), array_map(fn ($s) => [$s['name'], svc_url($biz, $s)], $biz['services'])],
            [$tax['short_name'], cat_url($tax), array_map(fn ($s) => [$s['name'], svc_url($tax, $s)], $tax['services'])]]],
        ['label' => 'Language & Test Prep', 'href' => cat_url($lang), 'menu' => [['', '', array_map(fn ($s) => [$s['name'], svc_url($lang, $s)], $lang['services'])]]],
        ['label' => 'Our Destinations', 'href' => '/destinations/', 'mega' => true, 'dest' => true],
        ['label' => 'Our Partners', 'href' => '/partners/'],
        ['label' => 'Contact Us', 'href' => '/contact/'],
    ];
}

function render_nav(string $active): string
{
    $out = '';
    foreach (nav_items() as $i => $item) {
        $isActive = $active === $item['href'] || ($item['href'] !== '/' && str_starts_with($active, $item['href']));
        $hasMenu = isset($item['menu']) || !empty($item['dest']);
        $cls = 'nav-item' . ($hasMenu ? ' has-menu' : '') . (!empty($item['mega']) ? ' is-mega' : '');
        $current = $isActive ? ' aria-current="page"' : '';
        $link = '<a class="nav-link' . ($isActive ? ' active' : '') . '" href="' . $item['href'] . '"' . $current . '>' . e($item['label']) . '</a>';
        if (!$hasMenu) {
            $out .= '<li class="' . $cls . '">' . $link . '</li>';
            continue;
        }
        $toggle = '<button class="sub-toggle" type="button" aria-expanded="false" aria-controls="menu-' . $i . '" '
            . 'aria-label="Show ' . e($item['label']) . ' menu">' . icon('chevron') . '</button>';
        if (!empty($item['dest'])) {
            $cells = '';
            foreach (C('DESTINATIONS') as $d) {
                $cells .= '<a class="dest-link" href="' . dest_url($d) . '">' . flag($d['flag'], 24) . '<span>' . e($d['name']) . '</span></a>';
            }
            $panel = '<div class="dest-grid">' . $cells . '</div><a class="menu-all" href="/destinations/">All destinations ' . icon('arrow') . '</a>';
        } else {
            $panel = '';
            foreach ($item['menu'] as [$title, $href, $links]) {
                $head = $title !== '' ? '<a class="menu-head" href="' . $href . '">' . e($title) . '</a>' : '';
                $lis = '';
                foreach ($links as [$t, $h]) {
                    $lis .= '<li><a' . (str_starts_with($t, 'All ') ? ' class=all-link' : '') . ' href="' . $h . '">' . e($t) . '</a></li>';
                }
                $panel .= '<div class="menu-col">' . $head . '<ul>' . $lis . '</ul></div>';
            }
        }
        $out .= '<li class="' . $cls . '">' . $link . $toggle . '<div class="menu" id="menu-' . $i . '"><div class="menu-inner">' . $panel . '</div></div></li>';
    }
    return $out;
}

// ---------------------------------------------------------------- page shell
function ld_json($x): string
{
    return json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
}

function page_head(string $title, string $desc, string $path, array $ld = [], string $og = '/assets/img/og.png'): string
{
    $SITE = C('SITE');
    $_ = 'nx_id';
    $full = $title === $SITE['name'] ? $title : "$title | {$SITE['name']}";
    $url = $SITE['url'] . $path;
    $ldHtml = implode('', array_map(fn ($x) => '<script type="application/ld+json">' . ld_json($x) . '</script>', $ld));
    $ga = '';
    if ($SITE['ga4_id'] !== '') {
        $g = $SITE['ga4_id'];
        $ga = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $g . '"></script>'
            . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . 'gtag("js",new Date());gtag("config","' . $g . '");</script>';
    }
    $v = asset_version();
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$_(e($full))}</title>
<meta name="description" content="{$_(e($desc))}">
<link rel="canonical" href="{$url}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{$_(e($SITE['name']))}">
<meta property="og:title" content="{$_(e($full))}">
<meta property="og:description" content="{$_(e($desc))}">
<meta property="og:url" content="{$url}">
<meta property="og:image" content="{$SITE['url']}{$og}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0d2b5e">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/css/style.css?v={$v}">
{$ldHtml}{$ga}
</head>

HTML;
}

function page_header(string $active): string
{
    $SITE = C('SITE');
    $_ = 'nx_id';
    $bits = [];
    if ($SITE['phone'] !== '') {
        $bits[] = '<a href="tel:' . e(str_replace(' ', '', $SITE['phone'])) . '">' . icon('phone') . '<span>' . e($SITE['phone']) . '</span></a>';
    }
    if ($SITE['email'] !== '') {
        $bits[] = '<a href="mailto:' . e($SITE['email']) . '">' . icon('mail') . '<span>' . e($SITE['email']) . '</span></a>';
    }
    $contact = $bits ? '<div class="brandbar-contact">' . implode('', $bits) . '</div>' : '';
    return <<<HTML
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="brandbar">
  <div class="container brandbar-inner">
    <a class="brand" href="/" aria-label="{$_(e($SITE['name']))} home"><img src="/assets/img/logo.png" alt="{$_(e($SITE['name']))}" width="188" height="56"></a>
    {$contact}
    <div class="brandbar-actions">
      <a class="btn btn-ghost btn-sm" href="/book-consultation/?interest=apply">Apply Now</a>
      <a class="btn btn-primary btn-sm" href="/book-consultation/">{$_(icon('calendar'))}Book a Consultation</a>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">{$_(icon('menu', 'icon icon-open'))}{$_(icon('close', 'icon icon-close'))}</button>
  </div>
</div>
<nav class="navbar" id="site-nav" aria-label="Main">
  <div class="container">
    <ul class="nav-list">{$_(render_nav($active))}</ul>
    <div class="nav-drawer-cta">
      <a class="btn btn-primary" href="/book-consultation/">Book a Consultation</a>
      <a class="btn btn-ghost" href="/book-consultation/?interest=apply">Apply Now</a>
    </div>
  </div>
</nav>
<main id="main">

HTML;
}

function page_footer(): string
{
    $SITE = C('SITE');
    $CAT = C('CAT_BY_SLUG');
    $_ = 'nx_id';
    $servicesLinks = [['Study Abroad', '/study-abroad/'], ['Business Advisory', cat_url($CAT['business-corporate'])],
        ['Tax Consultancy', cat_url($CAT['tax-legal'])], ['Visa Consultancy', cat_url($CAT['immigration-visa'])],
        ['Travel & Tourism', cat_url($CAT['travel-tourism'])], ['Language & Test Preparation', cat_url($CAT['language-test-preparation'])]];
    $destLinks = '';
    foreach (C('DESTINATIONS') as $d) {
        if (in_array($d['slug'], ['turkiye', 'uk', 'germany', 'italy', 'spain'], true)) {
            $destLinks .= '<li><a href="' . dest_url($d) . '">' . flag($d['flag'], 18) . e($d['name']) . '</a></li>';
        }
    }
    $items = [];
    if ($SITE['address'] !== '') {
        $items[] = '<li>' . icon('pin') . '<span>' . e($SITE['address']) . '</span></li>';
    }
    if ($SITE['phone'] !== '') {
        $items[] = '<li>' . icon('phone') . '<a href="tel:' . e(str_replace(' ', '', $SITE['phone'])) . '">' . e($SITE['phone']) . '</a></li>';
    }
    if ($SITE['whatsapp'] !== '') {
        $items[] = '<li>' . icon('whatsapp') . '<a href="' . wa_link() . '" target="_blank" rel="noopener">WhatsApp us</a></li>';
    }
    if ($SITE['email'] !== '') {
        $items[] = '<li>' . icon('mail') . '<a href="mailto:' . e($SITE['email']) . '">' . e($SITE['email']) . '</a></li>';
    }
    if (!$items) {
        $items[] = '<li>' . icon('send') . '<a href="/contact/">Send us a message</a></li>';
        $items[] = '<li>' . icon('calendar') . '<a href="/book-consultation/">Book a consultation</a></li>';
    }
    $socials = '';
    foreach ($SITE['socials'] as $n => $u) {
        if ($u !== '') {
            $socials .= '<a href="' . e($u) . '" target="_blank" rel="noopener">' . e($n === 'tiktok' ? 'TikTok' : ucfirst($n)) . '</a>';
        }
    }
    $socialsHtml = $socials !== '' ? '<div class="footer-social">' . $socials . '</div>' : '';
    $waFloat = $SITE['whatsapp'] !== '' ? '<a class="wa-float" href="' . wa_link() . '" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">' . icon('whatsapp') . '</a>' : '';
    $gallery = C('GALLERY') ? '<li><a href="/gallery/">Gallery</a></li>' : '';
    $svcLis = implode('', array_map(fn ($x) => '<li><a href="' . $x[1] . '">' . e($x[0]) . '</a></li>', $servicesLinks));
    $year = date('Y');
    $v = asset_version();
    $contactItems = implode('', $items);
    return <<<HTML
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <img class="footer-logo" src="/assets/img/logo-white.png" alt="{$_(e($SITE['name']))}" width="188" height="56" loading="lazy">
        <p>Clear pathways between ambition and opportunity: professional guidance for international education, visas, business, tax and travel.</p>
        <p class="footer-tagline">{$_(e($SITE['tagline']))}</p>
      </div>
      <div>
        <h2 class="footer-head">Quick Links</h2>
        <ul><li><a href="/">Home</a></li><li><a href="/about/">About Us</a></li><li><a href="/about/team/">Our Team</a></li>
        <li><a href="/success-stories/">Success Stories</a></li><li><a href="/insights/">Knowledge Center</a></li>{$gallery}<li><a href="/faqs/">FAQs</a></li><li><a href="/contact/">Contact Us</a></li></ul>
      </div>
      <div>
        <h2 class="footer-head">Consultancy Services</h2>
        <ul>{$svcLis}</ul>
      </div>
      <div>
        <h2 class="footer-head">Study Destinations</h2>
        <ul class="footer-dests">{$destLinks}<li><a href="/destinations/">Other destinations</a></li></ul>
      </div>
    </div>
    <div class="footer-bottom">
      <ul class="footer-contact">{$contactItems}</ul>
      {$socialsHtml}
      <div class="footer-legal">
        <span>&copy; {$year} {$_(e($SITE['name']))}. All rights reserved.</span>
        <a href="/privacy-policy/">Privacy Policy</a>
        <a href="/terms/">Terms &amp; Conditions</a>
      </div>
    </div>
  </div>
</footer>
{$waFloat}
<script src="/js/script.js?v={$v}" defer></script>
</body>
</html>

HTML;
}

/** A rendered page: [title, description, body html, json-ld list]. */
function page(string $title, string $desc, string $body, array $ld = []): array
{
    return compact('title', 'desc', 'body', 'ld');
}

function render_page(string $path, array $p): string
{
    return page_head($p['title'], $p['desc'], $path, $p['ld']) . page_header($path) . $p['body'] . page_footer();
}

// ---------------------------------------------------------------- shared blocks
function crumbs(array $trail): array
{
    $SITE = C('SITE');
    $items = array_merge([['Home', '/']], $trail);
    $parts = '';
    $ldItems = [];
    foreach ($items as $i => [$name, $href]) {
        $parts .= $i === count($items) - 1 ? '<li aria-current="page">' . e($name) . '</li>' : '<li><a href="' . $href . '">' . e($name) . '</a></li>';
        $ldItems[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $SITE['url'] . $href];
    }
    return ['<nav class="crumbs" aria-label="Breadcrumb"><ol>' . $parts . '</ol></nav>',
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ldItems]];
}

function page_hero(string $title, string $lead, array $trail, string $eyebrow = '', string $actions = '', string $aside = '', string $cls = ''): array
{
    [$crumbHtml, $ld] = crumbs($trail);
    $_ = 'nx_id';
    $eb = $eyebrow !== '' ? '<span class="eyebrow">' . e($eyebrow) . '</span>' : '';
    $acts = $actions !== '' ? '<div class="hero-actions">' . $actions . '</div>' : '';
    $asideHtml = $aside !== '' ? '<div class="page-hero-aside">' . $aside . '</div>' : '';
    $has = $aside !== '' ? ' has-aside' : '';
    return [<<<HTML
<section class="page-hero {$cls}">
  <div class="container page-hero-inner{$has}">
    <div class="page-hero-copy">{$crumbHtml}{$eb}<h1>{$_(e($title))}</h1><p class="lead">{$_(e($lead))}</p>{$acts}</div>
    {$asideHtml}
  </div>
</section>

HTML, $ld];
}

function section_head(string $eyebrow, string $title, string $lead = '', bool $center = true): string
{
    $leadHtml = $lead !== '' ? '<p>' . e($lead) . '</p>' : '';
    return '<div class="section-head' . ($center ? ' center' : '') . '"><span class="eyebrow">' . e($eyebrow) . '</span><h2>' . e($title) . '</h2>' . $leadHtml . '</div>';
}

function btn_primary(string $text, string $href, string $ico = ''): string
{
    return '<a class="btn btn-primary" href="' . $href . '">' . ($ico ? icon($ico) : '') . e($text) . '</a>';
}

function btn_light(string $text, string $href, string $ico = ''): string
{
    return '<a class="btn btn-light" href="' . $href . '">' . ($ico ? icon($ico) : '') . e($text) . '</a>';
}

function btn_wa(string $text = 'Chat on WhatsApp', ?string $msg = null): string
{
    $link = $msg !== null ? wa_link($msg) : wa_link();
    return $link === '' ? '' : '<a class="btn btn-wa" href="' . $link . '" target="_blank" rel="noopener">' . icon('whatsapp') . e($text) . '</a>';
}

function service_options(string $selected = ''): string
{
    $groups = '';
    foreach (C('CATEGORIES') as $c) {
        $opts = '';
        foreach ($c['services'] as $s) {
            $opts .= '<option value="' . e($s['name']) . '"' . ($s['name'] === $selected ? ' selected' : '') . '>' . e($s['name']) . '</option>';
        }
        $groups .= '<optgroup label="' . e($c['short_name']) . '">' . $opts . '</optgroup>';
    }
    $dest = '';
    foreach (C('DESTINATIONS') as $d) {
        $v = 'Study in ' . $d['name'];
        $dest .= '<option value="Study in ' . e($d['name']) . '"' . ($v === $selected ? ' selected' : '') . '>Study in ' . e($d['name']) . '</option>';
    }
    $groups .= '<optgroup label="Study destinations">' . $dest . '<option value="Other study destination">Other study destination</option></optgroup>';
    return '<option value="">Select a service</option><option value="Not sure yet">Not sure yet, I need advice</option>' . $groups;
}

const FORM_STATUS = '<div class="form-status" role="status" hidden data-form-error>'
    . 'Please check the highlighted fields. Name, phone and a valid email are needed so we can reply.</div>';

function inquiry_form(string $selected = '', string $type = 'inquiry', string $heading = 'Send us an inquiry', string $submit = 'Send inquiry', string $msgLabel = 'How can we help?'): string
{
    $_ = 'nx_id';
    $fs = FORM_STATUS;
    $t = e($type);
    return <<<HTML
<form class="form-card" method="post" action="/api/inquiry.php" data-validate>
  <h3>{$_(e($heading))}</h3>
  {$fs}
  <input type="hidden" name="form_type" value="{$t}">
  <input type="hidden" name="page" value="" data-page-field>
  <div class="hp" aria-hidden="true"><label for="website-{$type}">Website</label><input id="website-{$type}" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="form-grid">
    <div class="field"><label for="{$type}-name">Full name</label><input id="{$type}-name" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label for="{$type}-phone">Phone / WhatsApp</label><input id="{$type}-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+92 3xx xxxxxxx"></div>
    <div class="field"><label for="{$type}-email">Email</label><input id="{$type}-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
    <div class="field"><label for="{$type}-service">Service</label><select id="{$type}-service" name="service" data-interest>{$_(service_options($selected))}</select></div>
    <div class="field full"><label for="{$type}-message">{$_(e($msgLabel))}</label><textarea id="{$type}-message" name="message" rows="4" maxlength="2000"></textarea></div>
  </div>
  <label class="consent"><input type="checkbox" name="consent" value="yes" required> I agree to be contacted about my inquiry and accept the <a href="/privacy-policy/">Privacy Policy</a>.</label>
  <button class="btn btn-primary btn-block" type="submit">{$_(icon('send'))}{$_(e($submit))}</button>
</form>
HTML;
}

function contact_panel(): string
{
    $SITE = C('SITE');
    $_ = 'nx_id';
    $rows = '';
    if ($SITE['address'] !== '') {
        $rows .= '<li>' . icon('pin') . '<div><strong>Office</strong><span>' . e($SITE['address']) . '</span></div></li>';
    }
    if ($SITE['phone'] !== '') {
        $rows .= '<li>' . icon('phone') . '<div><strong>Phone</strong><a href="tel:' . e(str_replace(' ', '', $SITE['phone'])) . '">' . e($SITE['phone']) . '</a></div></li>';
    }
    if ($SITE['email'] !== '') {
        $rows .= '<li>' . icon('mail') . '<div><strong>Email</strong><a href="mailto:' . e($SITE['email']) . '">' . e($SITE['email']) . '</a></div></li>';
    }
    if ($SITE['hours'] !== '') {
        $rows .= '<li>' . icon('clock') . '<div><strong>Office hours</strong><span>' . e($SITE['hours']) . '</span></div></li>';
    }
    $rows .= '<li>' . icon('calendar') . '<div><strong>Appointments</strong><span>In office, by phone, WhatsApp call or online.</span></div></li>';
    $map = '';
    if ($SITE['map_query'] !== '') {
        $map = '<iframe class="map" title="Map of our office" loading="lazy" referrerpolicy="no-referrer-when-downgrade" '
            . 'src="https://www.google.com/maps?q=' . qp($SITE['map_query']) . '&output=embed"></iframe>';
    }
    return <<<HTML
<div class="contact-panel">
  <h3>Talk to our team</h3>
  <p>Tell us about your plans and an adviser will get back to you. Prefer a set time? Book an appointment.</p>
  <ul class="contact-list">{$rows}</ul>
  <div class="contact-actions">{$_(btn_primary('Book an appointment', '/book-consultation/', 'calendar'))}{$_(btn_wa())}</div>
  {$map}
</div>
HTML;
}

function consult_section(string $selected = '', string $title = 'Ready to take the next step?', string $lead = 'Send us your question or request a consultation. An adviser will reply with clear next steps.'): string
{
    $_ = 'nx_id';
    return <<<HTML
<section class="section consult" id="inquiry">
  <div class="container consult-grid">
    <div class="consult-copy">
      <span class="eyebrow on-dark">Consultation &amp; inquiry</span>
      <h2>{$_(e($title))}</h2>
      <p>{$_(e($lead))}</p>
      <ul class="consult-points">
        <li>{$_(icon('check'))}Free initial guidance on your options</li>
        <li>{$_(icon('check'))}Honest advice on eligibility and timelines</li>
        <li>{$_(icon('check'))}One adviser who follows your case</li>
      </ul>
      <div class="hero-actions">{$_(btn_light('Book an appointment', '/book-consultation/', 'calendar'))}{$_(btn_wa())}</div>
    </div>
    {$_(inquiry_form($selected))}
  </div>
</section>

HTML;
}

function faq_list(array $faqs): string
{
    $items = '';
    foreach ($faqs as [$q, $a]) {
        $items .= '<details class="faq"><summary>' . e($q) . icon('chevron') . '</summary><div class="faq-body"><p>' . e($a) . '</p></div></details>';
    }
    return '<div class="faq-list">' . $items . '</div>';
}

function faq_ld(array $faqs): array
{
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
        fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)];
}

function steps(array $items): string
{
    $lis = '';
    foreach ($items as $i => [$t, $d]) {
        $lis .= '<li class="step"><span class="step-num">' . ($i + 1) . '</span><div><h3>' . e($t) . '</h3><p>' . e($d) . '</p></div></li>';
    }
    return '<ol class="steps">' . $lis . '</ol>';
}

function checklist(array $items, string $cls = 'checklist'): string
{
    return '<ul class="' . $cls . '">' . implode('', array_map(fn ($x) => '<li>' . icon('check') . '<span>' . e($x) . '</span></li>', $items)) . '</ul>';
}

// ---------------------------------------------------------------- cards
function category_card(array $c): string
{
    $_ = 'nx_id';
    $lis = implode('', array_map(fn ($s) => '<li><a href="' . svc_url($c, $s) . '">' . e($s['name']) . '</a></li>', array_slice($c['services'], 0, 4)));
    $n = count($c['services']);
    return <<<HTML
<article class="cat-card">
  <span class="icon-badge">{$_(icon($c['icon']))}</span>
  <h3><a href="{$_(cat_url($c))}">{$_(e($c['short_name']))}</a></h3>
  <p>{$_(e($c['summary']))}</p>
  <ul class="cat-links">{$lis}</ul>
  <a class="text-link" href="{$_(cat_url($c))}">All {$n} services {$_(icon('arrow'))}</a>
</article>
HTML;
}

function service_card(array $c, array $s): string
{
    $_ = 'nx_id';
    return <<<HTML
<a class="svc-card" href="{$_(svc_url($c, $s))}">
  <span class="svc-cat">{$_(e($c['short_name']))}</span>
  <h3>{$_(e($s['name']))}</h3>
  <p>{$_(e($s['short']))}</p>
  <span class="text-link">Learn more {$_(icon('arrow'))}</span>
</a>
HTML;
}

function dest_card(array $d): string
{
    $_ = 'nx_id';
    $pts = implode('', array_map(fn ($p) => '<li>' . e($p) . '</li>', $d['card_points']));
    return <<<HTML
<a class="dest-card" href="{$_(dest_url($d))}">
  <div class="dest-flag">{$_(flag($d['flag'], 64, 'flag flag-lg'))}</div>
  <div class="dest-body">
    <h3>Study in {$_(e($d['name']))}</h3>
    <ul>{$pts}</ul>
    <span class="text-link">Explore {$_(icon('arrow'))}</span>
  </div>
</a>
HTML;
}

function team_card(array $m): string
{
    $_ = 'nx_id';
    $photo = $m['photo'] ?: '/assets/img/logo.png';
    return <<<HTML
<a class="team-card" href="/about/team/{$m['slug']}/">
  <div class="team-photo"><img src="{$photo}" alt="{$_(e($m['name']))}" width="480" height="600" loading="lazy" decoding="async"></div>
  <div class="team-info"><h3>{$_(e($m['name']))}</h3><span class="role">{$_(e($m['role']))}</span><p>{$_(e($m['summary']))}</p></div>
</a>
HTML;
}

function article_card(array $a): string
{
    $_ = 'nx_id';
    $cover = $a['image'] ? '<img class="post-img" src="' . $a['image'] . '" alt="" width="480" height="270" loading="lazy" decoding="async">' : '';
    $has = $cover ? ' has-img' : '';
    return <<<HTML
<a class="post-card{$has}" href="/insights/{$a['slug']}/" data-category="{$_(e($a['category']))}">
  {$cover}<span class="post-cat">{$_(e($a['category']))}</span>
  <h3>{$_(e($a['title']))}</h3>
  <p>{$_(e($a['excerpt']))}</p>
  <time datetime="{$a['date']}">{$_(long_date($a['date']))}</time>
</a>
HTML;
}

function story_card(array $s): string
{
    $_ = 'nx_id';
    $photo = $s['photo'] ? '<img src="' . $s['photo'] . '" alt="" width="56" height="56" loading="lazy" decoding="async">'
        : '<span class="story-initial" aria-hidden="true">' . e(mb_strtoupper(mb_substr($s['name'], 0, 1))) . '</span>';
    $head = $s['headline'] !== '' ? '<h3>' . e($s['headline']) . '</h3>' : '';
    $detail = $s['detail'] !== '' ? '<span>' . e($s['detail']) . '</span>' : '';
    return <<<HTML
<figure class="story-card">{$_(icon('quote', 'icon story-quote'))}{$head}
  <blockquote><p>{$_(e($s['text']))}</p></blockquote>
  <figcaption>{$photo}<div><strong>{$_(e($s['name']))}</strong>{$detail}</div></figcaption>
</figure>
HTML;
}

function partner_logo(array $p): string
{
    $inner = $p['logo'] ? '<img src="' . $p['logo'] . '" alt="' . e($p['name']) . '" loading="lazy" decoding="async">' : '<span class="logo-name">' . e($p['name']) . '</span>';
    $title = e($p['name'] . ($p['country'] !== '' ? ', ' . $p['country'] : ''));
    if ($p['url'] !== '') {
        return '<a class="partner-logo" href="' . e($p['url']) . '" target="_blank" rel="noopener" title="' . $title . '">' . $inner . '</a>';
    }
    return '<div class="partner-logo" title="' . $title . '">' . $inner . '</div>';
}

function why_grid(): string
{
    return '<div class="why-grid">' . implode('', array_map(fn ($w) => '<div class="why-item"><span class="icon-badge soft">' . icon($w[2]) . '</span><h3>' . e($w[0]) . '</h3><p>' . e($w[1]) . '</p></div>', C('WHY_US'))) . '</div>';
}

function process_band(): string
{
    $lis = '';
    foreach (C('PROCESS') as $i => [$t, $d]) {
        $lis .= '<li><span class="pstep-num">' . ($i + 1) . '</span><h3>' . e($t) . '</h3><p>' . e($d) . '</p></li>';
    }
    return '<ol class="process">' . $lis . '</ol>';
}

function cta_band(string $title, string $text, array $primary = ['Book a Consultation', '/book-consultation/'], ?array $secondary = null): string
{
    $_ = 'nx_id';
    $sec = $secondary ? btn_light($secondary[0], $secondary[1]) : btn_wa();
    return <<<HTML
<section class="cta-band"><div class="container cta-inner">
  <div><h2>{$_(e($title))}</h2><p>{$_(e($text))}</p></div>
  <div class="hero-actions">{$_(btn_light($primary[0], $primary[1], 'calendar'))}{$sec}</div>
</div></section>
HTML;
}

function benefit_grid(array $c): string
{
    return '<div class="benefits">' . implode('', array_map(fn ($b) => '<div class="benefit">' . icon('check') . '<div><h3>' . e($b[0]) . '</h3><p>' . e($b[1]) . '</p></div></div>', $c['benefits'])) . '</div>';
}
