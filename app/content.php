<?php
declare(strict_types=1);

/*
 * Loads the site content. The live copy is in api/data/content/ (edited in /admin/);
 * the JSON files in /content/ are the starting point used until the first admin save.
 */

final class ContentError extends RuntimeException
{
}

const NX_SLUG_RE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
const NX_ASSET_RE = '#^/(assets|uploads)/[A-Za-z0-9/_.\-]+$#';

function nx_content_file(string $name): string
{
    $live = NX_ROOT . '/api/data/content/' . $name;
    return is_file($live) ? $live : NX_ROOT . '/content/' . $name;
}

function nx_load(string $name)
{
    $raw = @file_get_contents(nx_content_file($name));
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        throw new ContentError("content/$name could not be read");
    }
    return $data;
}

function c_text($v): string
{
    return is_string($v) ? trim($v) : '';
}

function c_strings($v): array
{
    if (!is_array($v)) {
        return [];
    }
    return array_values(array_filter(array_map(fn ($s) => is_string($s) ? trim($s) : '', $v), fn ($s) => $s !== ''));
}

function c_items($v): array
{
    return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
}

function c_pairs($v, string $a = 'title', string $b = 'desc'): array
{
    $out = [];
    foreach (c_items($v) as $x) {
        if (c_text($x[$a] ?? '') !== '') {
            $out[] = [c_text($x[$a] ?? ''), c_text($x[$b] ?? '')];
        }
    }
    return $out;
}

function c_slug($v, string $where): string
{
    $s = c_text($v);
    if (!preg_match(NX_SLUG_RE, $s)) {
        throw new ContentError("$where: invalid web address '$s'");
    }
    return $s;
}

function c_asset($v): string
{
    $s = c_text($v);
    return ($s !== '' && preg_match(NX_ASSET_RE, $s) && !str_contains($s, '..')) ? $s : '';
}

function c_url($v): string
{
    $s = c_text($v);
    return preg_match('#^https?://[^\s"\'<>]+$#', $s) ? $s : '';
}

function c_unique_by_slug(array $items): array
{
    $seen = [];
    $out = [];
    foreach ($items as $it) {
        if (isset($seen[$it['slug']])) {
            continue;
        }
        $seen[$it['slug']] = true;
        $out[] = $it;
    }
    return $out;
}

/** Builds the content model. Invalid entries are skipped so one bad item never takes the site down. */
function nx_content(): array
{
    static $C = null;
    if ($C !== null) {
        return $C;
    }

    $site = nx_load('site.json');
    $SITE = [];
    foreach (['name', 'url', 'tagline', 'description', 'phone', 'email', 'address', 'hours', 'map_query', 'ga4_id'] as $k) {
        $SITE[$k] = c_text($site[$k] ?? '');
    }
    $SITE['name'] = $SITE['name'] ?: 'Nexbridge Solutions';
    $SITE['url'] = rtrim($SITE['url'], '/') ?: 'https://nexbridge-solutions.com';
    $SITE['whatsapp'] = preg_replace('/\D/', '', c_text($site['whatsapp'] ?? ''));
    $SITE['socials'] = [];
    foreach ((is_array($site['socials'] ?? null) ? $site['socials'] : []) as $k => $v) {
        $SITE['socials'][$k] = c_url($v);
    }
    if (!preg_match('/^(G-[A-Z0-9]+)?$/', $SITE['ga4_id'])) {
        $SITE['ga4_id'] = '';
    }

    $home = nx_load('home.json');
    $HOME = [
        'hero_eyebrow' => c_text($home['hero_eyebrow'] ?? ''),
        'hero_title' => c_text($home['hero_title'] ?? '') ?: $SITE['tagline'],
        'hero_lead' => c_text($home['hero_lead'] ?? ''),
        'hero_image' => c_asset($home['hero_image'] ?? ''),
        'about_image' => c_asset($home['about_image'] ?? '') ?: '/assets/img/office.webp',
        'about_points' => c_strings($home['about_points'] ?? []),
    ];

    $about = nx_load('about.json');
    $ABOUT = [
        'intro' => c_strings($about['intro'] ?? []),
        'vision' => c_text($about['vision'] ?? ''),
        'mission' => c_text($about['mission'] ?? ''),
        'commitment' => c_text($about['commitment'] ?? ''),
        'values' => c_pairs($about['values'] ?? []),
    ];
    $WHY_US = [];
    foreach (c_items($about['why_us'] ?? []) as $x) {
        if (c_text($x['title'] ?? '') !== '') {
            $ic = c_text($x['icon'] ?? '');
            $WHY_US[] = [c_text($x['title']), c_text($x['desc'] ?? ''), isset(NX_ICON_PATHS[$ic]) ? $ic : 'check'];
        }
    }
    $PROCESS = c_pairs($about['process'] ?? []);

    $TEAM = [];
    foreach (c_items(nx_load('team.json')) as $i => $m) {
        try {
            $TEAM[] = ['slug' => c_slug($m['slug'] ?? '', 'team'), 'name' => c_text($m['name'] ?? ''), 'role' => c_text($m['role'] ?? ''),
                'photo' => c_asset($m['photo'] ?? ''), 'summary' => c_text($m['summary'] ?? ''), 'bio' => c_strings($m['bio'] ?? [])];
        } catch (ContentError $e) {
            continue;
        }
    }
    $TEAM = c_unique_by_slug($TEAM);

    $articles = nx_load('articles.json');
    $INSIGHT_CATEGORIES = c_strings($articles['categories'] ?? []);
    $ARTICLES = [];
    foreach (c_items($articles['articles'] ?? []) as $a) {
        $d = c_text($a['date'] ?? '');
        if (!empty($a['draft']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > gmdate('Y-m-d', time() + 14 * 3600)) {
            continue; // drafts and future-dated posts stay hidden
        }
        try {
            $ARTICLES[] = ['slug' => c_slug($a['slug'] ?? '', 'article'), 'title' => c_text($a['title'] ?? ''), 'category' => c_text($a['category'] ?? ''),
                'date' => $d, 'excerpt' => c_text($a['excerpt'] ?? ''), 'image' => c_asset($a['image'] ?? ''), 'body' => c_text($a['body'] ?? '')];
        } catch (ContentError $e) {
            continue;
        }
    }
    $ARTICLES = c_unique_by_slug($ARTICLES);
    usort($ARTICLES, fn ($x, $y) => strcmp($y['date'], $x['date']));

    $FAQ_GROUPS = [];
    foreach (c_items(nx_load('faqs.json')) as $g) {
        if (c_text($g['group'] ?? '') !== '') {
            $FAQ_GROUPS[] = [c_text($g['group']), c_pairs($g['faqs'] ?? [], 'q', 'a')];
        }
    }

    $CATEGORIES = [];
    foreach (c_items(nx_load('services.json')) as $c) {
        try {
            $services = [];
            foreach (c_items($c['services'] ?? []) as $s) {
                try {
                    $svc = ['slug' => c_slug($s['slug'] ?? '', 'service'), 'name' => c_text($s['name'] ?? ''), 'short' => c_text($s['short'] ?? ''),
                        'intro' => c_text($s['intro'] ?? ''), 'offers' => c_strings($s['offers'] ?? []), 'faqs' => c_pairs($s['faqs'] ?? [], 'q', 'a')];
                    foreach (['requirements', 'documents', 'related'] as $k) {
                        $svc[$k] = c_strings($s[$k] ?? []);
                    }
                    $services[] = $svc;
                } catch (ContentError $e) {
                    continue;
                }
            }
            $ic = c_text($c['icon'] ?? '');
            $CATEGORIES[] = ['slug' => c_slug($c['slug'] ?? '', 'category'), 'name' => c_text($c['name'] ?? ''),
                'short_name' => c_text($c['short_name'] ?? '') ?: c_text($c['name'] ?? ''), 'icon' => isset(NX_ICON_PATHS[$ic]) ? $ic : 'layers',
                'summary' => c_text($c['summary'] ?? ''), 'intro' => c_text($c['intro'] ?? ''), 'benefits' => c_pairs($c['benefits'] ?? []),
                'process' => c_pairs($c['process'] ?? []), 'services' => c_unique_by_slug($services)];
        } catch (ContentError $e) {
            continue;
        }
    }
    $CATEGORIES = c_unique_by_slug($CATEGORIES);
    $CAT_BY_SLUG = array_column($CATEGORIES, null, 'slug');
    foreach (['education-study-abroad', 'business-corporate', 'tax-legal', 'immigration-visa', 'travel-tourism', 'language-test-preparation'] as $req) {
        if (empty($CAT_BY_SLUG[$req]['services'])) {
            throw new ContentError("The service category '$req' must exist and contain at least one service");
        }
    }

    $dest = nx_load('destinations.json');
    $DESTINATIONS = [];
    foreach (c_items($dest['destinations'] ?? []) as $d) {
        try {
            $flag = strtolower(c_text($d['flag'] ?? ''));
            $DESTINATIONS[] = ['slug' => c_slug($d['slug'] ?? '', 'destination'), 'name' => c_text($d['name'] ?? ''),
                'flag' => preg_match('/^[a-z]{2}$/', $flag) ? $flag : '', 'image' => c_asset($d['image'] ?? ''),
                'tagline' => c_text($d['tagline'] ?? ''), 'card_points' => c_strings($d['card_points'] ?? []), 'intro' => c_text($d['intro'] ?? ''),
                'system' => c_text($d['system'] ?? ''), 'programs' => c_strings($d['programs'] ?? []), 'requirements' => c_strings($d['requirements'] ?? []),
                'tuition' => c_text($d['tuition'] ?? ''), 'scholarships' => c_pairs($d['scholarships'] ?? []), 'process' => c_strings($d['process'] ?? []),
                'visa' => c_text($d['visa'] ?? ''), 'faqs' => c_pairs($d['faqs'] ?? [], 'q', 'a')];
        } catch (ContentError $e) {
            continue;
        }
    }
    $DESTINATIONS = c_unique_by_slug($DESTINATIONS);
    $OTHER_DESTINATIONS = c_strings($dest['other'] ?? []);

    $STORY_TYPES = [['visa', 'passport', 'Visa success stories', 'Study, visit and business visa approvals.'],
        ['admissions', 'cap', 'Admission success stories', 'Offers and enrolments at universities abroad.'],
        ['testimonials', 'quote', 'Client testimonials', 'Feedback from students, families and business clients.'],
        ['achievements', 'trophy', 'Achievements & case studies', 'Scholarships won, companies registered and milestones reached.']];
    $STORIES = [];
    foreach (c_items(nx_load('stories.json')) as $s) {
        if (c_text($s['name'] ?? '') === '' || c_text($s['text'] ?? '') === '' || empty($s['consent'])) {
            continue;
        }
        $kind = c_text($s['type'] ?? '');
        $STORIES[] = ['type' => in_array($kind, array_column($STORY_TYPES, 0), true) ? $kind : 'testimonials', 'name' => c_text($s['name']),
            'headline' => c_text($s['headline'] ?? ''), 'detail' => c_text($s['detail'] ?? ''), 'text' => c_text($s['text']),
            'photo' => c_asset($s['photo'] ?? ''), 'featured' => !empty($s['featured'])];
    }

    $PARTNER_TYPES = [['universities', 'cap', 'Universities', 'Universities abroad that welcome our students and work with us on admissions, scholarships and student support.'],
        ['institutions', 'book', 'Educational institutions', 'Colleges, language schools and pathway providers that help students prepare for and progress in their studies.'],
        ['corporate', 'building', 'Corporate partners', 'Businesses and service providers we work with to deliver travel, accommodation, insurance and corporate services.'],
        ['international', 'globe', 'International organizations', 'Organizations that share our commitment to education, mobility and opportunity.']];
    $PARTNERS = [];
    foreach (c_items(nx_load('partners.json')) as $p) {
        if (c_text($p['name'] ?? '') === '') {
            continue;
        }
        $kind = c_text($p['type'] ?? '');
        $PARTNERS[] = ['name' => c_text($p['name']), 'type' => in_array($kind, array_column($PARTNER_TYPES, 0), true) ? $kind : 'universities',
            'country' => c_text($p['country'] ?? ''), 'logo' => c_asset($p['logo'] ?? ''), 'url' => c_url($p['url'] ?? '')];
    }

    $GALLERY = [];
    foreach (c_items(nx_load('gallery.json')) as $g) {
        $img = c_asset($g['image'] ?? '');
        if ($img !== '') {
            $GALLERY[] = ['image' => $img, 'caption' => c_text($g['caption'] ?? ''), 'album' => c_text($g['album'] ?? '') ?: 'Nexbridge'];
        }
    }

    $C = compact('SITE', 'HOME', 'ABOUT', 'WHY_US', 'PROCESS', 'TEAM', 'ARTICLES', 'INSIGHT_CATEGORIES', 'FAQ_GROUPS',
        'CATEGORIES', 'CAT_BY_SLUG', 'DESTINATIONS', 'OTHER_DESTINATIONS', 'STORY_TYPES', 'STORIES', 'PARTNER_TYPES', 'PARTNERS', 'GALLERY');
    $C['DEST_BY_SLUG'] = array_column($DESTINATIONS, null, 'slug');
    $C['LEGAL_UPDATED'] = '2026-11-14'; // change when the Privacy Policy or Terms text changes
    $C['N_SERVICES'] = array_sum(array_map(fn ($c) => count($c['services']), $CATEGORIES));
    return $C;
}
