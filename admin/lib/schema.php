<?php
declare(strict_types=1);

if (!defined('NX_ADMIN')) {
    http_response_code(404);
    exit;
}

/*
 * Describes every editable content file. The admin screen builds its forms
 * from this, and the server validates saves against it.
 *
 * Field types: text, textarea, markdown, email, tel, url, date, select, icon,
 * flag, image, slug, checkbox, strings (one item per line), paragraphs
 * (paragraphs separated by a blank line), list (repeatable group), object.
 */

const NX_ICONS = ['cap', 'briefcase', 'document', 'passport', 'plane', 'language', 'check', 'phone', 'mail', 'pin', 'clock',
    'user', 'users', 'award', 'eye', 'layers', 'shield', 'globe', 'calendar', 'trophy', 'building', 'quote', 'handshake', 'book', 'send'];

function nx_f(string $key, string $label, string $type = 'text', array $extra = []): array
{
    return ['key' => $key, 'label' => $label, 'type' => $type] + $extra;
}

function nx_pair_fields(string $a = 'Title', string $b = 'Description'): array
{
    return [nx_f('title', $a, 'text', ['required' => true]), nx_f('desc', $b, 'textarea', ['rows' => 2])];
}

function nx_qa_fields(): array
{
    return [nx_f('q', 'Question', 'text', ['required' => true]), nx_f('a', 'Answer', 'textarea', ['rows' => 3])];
}

function nx_collections(): array
{
    $storyTypes = ['visa' => 'Visa success', 'admissions' => 'Admission success', 'testimonials' => 'Client testimonial', 'achievements' => 'Achievement / case study'];
    $partnerTypes = ['universities' => 'University', 'institutions' => 'Educational institution', 'corporate' => 'Corporate partner', 'international' => 'International organization'];

    return [
        'settings' => [
            'view' => '/contact/', 'file' => 'site.json', 'label' => 'Contact & settings', 'group' => 'Website',
            'intro' => 'Contact details, social media and analytics. Empty fields are simply hidden on the website.',
            'type' => 'object',
            'fields' => [
                nx_f('name', 'Business name', 'text', ['required' => true]),
                nx_f('tagline', 'Tagline', 'text'),
                nx_f('description', 'Short description for Google', 'textarea', ['rows' => 2, 'help' => 'Shown in Google results and when the site is shared. Around 150 characters works best.']),
                nx_f('phone', 'Phone number', 'tel', ['placeholder' => '+92 300 1234567']),
                nx_f('whatsapp', 'WhatsApp number', 'tel', ['placeholder' => '923001234567', 'help' => 'Country code and number, digits only. Turns on the WhatsApp buttons across the site.']),
                nx_f('email', 'Email address', 'email'),
                nx_f('address', 'Office address', 'textarea', ['rows' => 2]),
                nx_f('hours', 'Office hours', 'text', ['placeholder' => 'Mon to Sat, 10am to 6pm']),
                nx_f('map_query', 'Google Maps location', 'text', ['placeholder' => 'Nexbridge Solutions, Lahore', 'help' => 'The name or address to show on the map on the Contact page.']),
                nx_f('socials', 'Social media links', 'object', ['fields' => [
                    nx_f('facebook', 'Facebook', 'url'), nx_f('instagram', 'Instagram', 'url'), nx_f('linkedin', 'LinkedIn', 'url'),
                    nx_f('tiktok', 'TikTok', 'url'), nx_f('youtube', 'YouTube', 'url'),
                ]]),
                nx_f('ga4_id', 'Google Analytics ID', 'text', ['placeholder' => 'G-XXXXXXXXXX', 'pattern' => '^(G-[A-Z0-9]+)?$']),
            ],
        ],
        'home' => [
            'view' => '/', 'file' => 'home.json', 'label' => 'Homepage', 'group' => 'Website',
            'intro' => 'The large banner at the top of the homepage and the About section beneath it.',
            'type' => 'object',
            'fields' => [
                nx_f('hero_eyebrow', 'Small text above the headline', 'text'),
                nx_f('hero_title', 'Headline', 'text', ['required' => true, 'help' => 'Wrap words in *asterisks* to show them in the brand gradient.']),
                nx_f('hero_lead', 'Introduction', 'textarea', ['rows' => 4]),
                nx_f('hero_image', 'Banner photo', 'image', ['help' => 'A person cut out on a transparent background looks best.']),
                nx_f('about_image', 'About section photo', 'image'),
                nx_f('about_points', 'About section checklist', 'strings', ['help' => 'One point per line.']),
            ],
        ],
        'about' => [
            'view' => '/about/', 'file' => 'about.json', 'label' => 'About us', 'group' => 'Website',
            'intro' => 'The About Us page: introduction, vision, mission, values, reasons to choose you and your process.',
            'type' => 'object',
            'fields' => [
                nx_f('intro', 'Company introduction', 'paragraphs', ['rows' => 8]),
                nx_f('vision', 'Vision', 'textarea'),
                nx_f('mission', 'Mission', 'textarea'),
                nx_f('commitment', 'Commitment to clients', 'textarea'),
                nx_f('values', 'Core values', 'list', ['item_label' => 'title', 'fields' => nx_pair_fields('Value')]),
                nx_f('why_us', 'Why choose us', 'list', ['item_label' => 'title', 'fields' => array_merge(nx_pair_fields('Reason'), [nx_f('icon', 'Icon', 'icon')])]),
                nx_f('process', 'Our process (steps)', 'list', ['item_label' => 'title', 'fields' => nx_pair_fields('Step')]),
            ],
        ],
        'team' => [
            'view' => '/about/team/', 'file' => 'team.json', 'label' => 'Team', 'group' => 'Website',
            'intro' => 'Each member gets a profile page. The order here is the order on the website.',
            'type' => 'list', 'item_label' => 'name', 'add_label' => 'Add team member',
            'fields' => [
                nx_f('name', 'Full name', 'text', ['required' => true]),
                nx_f('slug', 'Web address', 'slug', ['from' => 'name', 'prefix' => '/about/team/']),
                nx_f('role', 'Job title', 'text', ['required' => true]),
                nx_f('photo', 'Photo', 'image', ['help' => 'Portrait photo, ideally 4:5 (for example 800 × 1000).']),
                nx_f('summary', 'One-line summary', 'textarea', ['rows' => 2]),
                nx_f('bio', 'Biography', 'paragraphs', ['rows' => 10]),
            ],
        ],
        'articles' => [
            'view' => '/insights/', 'file' => 'articles.json', 'label' => 'Knowledge Center', 'group' => 'Content',
            'intro' => 'Blog articles and guides. New articles appear on the homepage and in the Knowledge Center.',
            'type' => 'object',
            'fields' => [
                nx_f('articles', 'Articles', 'list', ['item_label' => 'title', 'item_sub' => 'date', 'add_label' => 'Write a new article', 'add_first' => true, 'fields' => [
                    nx_f('title', 'Title', 'text', ['required' => true]),
                    nx_f('slug', 'Web address', 'slug', ['from' => 'title', 'prefix' => '/insights/']),
                    nx_f('category', 'Category', 'select', ['options_from' => 'categories', 'required' => true]),
                    nx_f('date', 'Publish date', 'date', ['required' => true, 'default' => 'today']),
                    nx_f('excerpt', 'Summary', 'textarea', ['rows' => 2, 'help' => 'Shown on article cards and in Google results.']),
                    nx_f('image', 'Cover image (optional)', 'image'),
                    nx_f('body', 'Article', 'markdown', ['rows' => 18]),
                    nx_f('draft', 'Draft: hide from the website', 'checkbox'),
                ]]),
                nx_f('categories', 'Categories', 'strings', ['help' => 'One per line. These become the filter buttons in the Knowledge Center.']),
            ],
        ],
        'stories' => [
            'view' => '/success-stories/', 'file' => 'stories.json', 'label' => 'Success stories', 'group' => 'Content',
            'intro' => 'Testimonials, visa and admission successes. Only publish stories your clients have agreed to share.',
            'type' => 'list', 'item_label' => 'name', 'item_sub' => 'type', 'add_label' => 'Add a story', 'add_first' => true,
            'fields' => [
                nx_f('name', 'Client name', 'text', ['required' => true, 'help' => 'First name and initial is fine, for example "Ali R."']),
                nx_f('type', 'Type', 'select', ['options' => $storyTypes, 'default' => 'testimonials']),
                nx_f('headline', 'Headline (optional)', 'text', ['placeholder' => 'UK student visa approved']),
                nx_f('detail', 'Detail line (optional)', 'text', ['placeholder' => 'MSc Data Science, University of Leeds']),
                nx_f('text', 'Story or quote', 'textarea', ['rows' => 5, 'required' => true]),
                nx_f('photo', 'Photo (optional)', 'image'),
                nx_f('featured', 'Show on the homepage', 'checkbox'),
                nx_f('consent', 'The client has agreed to publish this', 'checkbox', ['required' => true]),
            ],
        ],
        'partners' => [
            'view' => '/partners/', 'file' => 'partners.json', 'label' => 'Partners', 'group' => 'Content',
            'intro' => 'Only add partners who have authorized you to show their name and logo.',
            'type' => 'list', 'item_label' => 'name', 'item_sub' => 'type', 'add_label' => 'Add a partner',
            'fields' => [
                nx_f('name', 'Partner name', 'text', ['required' => true]),
                nx_f('type', 'Type', 'select', ['options' => $partnerTypes, 'default' => 'universities']),
                nx_f('country', 'Country (optional)', 'text'),
                nx_f('logo', 'Logo', 'image', ['keep_png' => true, 'max' => 600, 'help' => 'A PNG with a transparent background looks best.']),
                nx_f('url', 'Website (optional)', 'url'),
            ],
        ],
        'gallery' => [
            'view' => '/gallery/', 'file' => 'gallery.json', 'label' => 'Gallery', 'group' => 'Content',
            'intro' => 'Photos for the Gallery page. The page and its menu link appear once you add the first photo.',
            'type' => 'list', 'item_label' => 'caption', 'item_sub' => 'album', 'item_image' => 'image', 'add_label' => 'Add a photo', 'add_first' => true,
            'fields' => [
                nx_f('image', 'Photo', 'image', ['required' => true]),
                nx_f('caption', 'Caption (optional)', 'text'),
                nx_f('album', 'Album', 'text', ['placeholder' => 'Office, Events, University visits...', 'help' => 'Photos with the same album name are grouped together.']),
            ],
        ],
        'faqs' => [
            'view' => '/faqs/', 'file' => 'faqs.json', 'label' => 'FAQs', 'group' => 'Content',
            'intro' => 'The FAQ page. Questions on individual services and destinations are edited on those pages.',
            'type' => 'list', 'item_label' => 'group', 'add_label' => 'Add a topic',
            'fields' => [
                nx_f('group', 'Topic', 'text', ['required' => true]),
                nx_f('faqs', 'Questions', 'list', ['item_label' => 'q', 'add_label' => 'Add a question', 'fields' => nx_qa_fields()]),
            ],
        ],
        'services' => [
            'view' => '/services/', 'file' => 'services.json', 'label' => 'Services', 'group' => 'Services & destinations',
            'intro' => 'The six service categories and every service page inside them. Changing a web address changes the page link, so avoid it once a page is shared.',
            'type' => 'list', 'item_label' => 'name', 'fixed' => true,
            'fields' => [
                nx_f('name', 'Category name', 'text', ['required' => true]),
                nx_f('short_name', 'Short name (menus)', 'text'),
                nx_f('slug', 'Web address', 'slug', ['locked' => true, 'prefix' => '/services/']),
                nx_f('icon', 'Icon', 'icon'),
                nx_f('summary', 'Card summary', 'textarea', ['rows' => 2]),
                nx_f('intro', 'Introduction', 'textarea', ['rows' => 4]),
                nx_f('benefits', 'Benefits', 'list', ['item_label' => 'title', 'fields' => nx_pair_fields('Benefit')]),
                nx_f('process', 'Process steps', 'list', ['item_label' => 'title', 'fields' => nx_pair_fields('Step')]),
                nx_f('services', 'Services in this category', 'list', ['item_label' => 'name', 'add_label' => 'Add a service', 'fields' => [
                    nx_f('name', 'Service name', 'text', ['required' => true]),
                    nx_f('slug', 'Web address', 'slug', ['from' => 'name']),
                    nx_f('short', 'One-line summary', 'textarea', ['rows' => 2]),
                    nx_f('intro', 'Introduction', 'textarea', ['rows' => 4]),
                    nx_f('offers', 'What we offer', 'strings'),
                    nx_f('requirements', 'Requirements', 'strings'),
                    nx_f('documents', 'Documents required', 'strings'),
                    nx_f('faqs', 'FAQs', 'list', ['item_label' => 'q', 'add_label' => 'Add a question', 'fields' => nx_qa_fields()]),
                    nx_f('related', 'Related services (advanced)', 'strings', ['help' => 'One per line as category/service, for example tax-legal/ntn-registration.']),
                ]]),
            ],
        ],
        'destinations' => [
            'view' => '/destinations/', 'file' => 'destinations.json', 'label' => 'Destinations', 'group' => 'Services & destinations',
            'type' => 'object',
            'fields' => [
                nx_f('destinations', 'Study destinations', 'list', ['item_label' => 'name', 'add_label' => 'Add a destination', 'fields' => [
                    nx_f('name', 'Country', 'text', ['required' => true]),
                    nx_f('slug', 'Web address', 'slug', ['from' => 'name', 'prefix' => '/study-abroad/']),
                    nx_f('flag', 'Flag', 'flag'),
                    nx_f('image', 'Banner photo (optional)', 'image', ['help' => 'Replaces the flag at the top of the page.']),
                    nx_f('tagline', 'Tagline', 'textarea', ['rows' => 2]),
                    nx_f('card_points', 'Key facts (cards)', 'strings'),
                    nx_f('intro', 'Introduction', 'textarea', ['rows' => 4]),
                    nx_f('system', 'Education system', 'textarea', ['rows' => 4]),
                    nx_f('programs', 'Available programs', 'strings'),
                    nx_f('requirements', 'Admission requirements', 'strings'),
                    nx_f('tuition', 'Tuition fee overview', 'textarea', ['rows' => 3]),
                    nx_f('scholarships', 'Scholarships', 'list', ['item_label' => 'title', 'fields' => nx_pair_fields('Scholarship')]),
                    nx_f('process', 'Application steps', 'strings'),
                    nx_f('visa', 'Student visa information', 'textarea', ['rows' => 4]),
                    nx_f('faqs', 'FAQs', 'list', ['item_label' => 'q', 'add_label' => 'Add a question', 'fields' => nx_qa_fields()]),
                ]]),
                nx_f('other', 'Other destinations', 'strings', ['help' => 'Countries you also help with, one per line.']),
            ],
        ],
    ];
}

function nx_flags(): array
{
    $codes = [];
    foreach (glob(NX_ROOT . '/assets/img/flags/*.webp') ?: [] as $f) {
        $name = basename($f, '.webp');
        if (preg_match('/^[a-z]{2}$/', $name)) {
            $codes[] = $name;
        }
    }
    sort($codes);
    return $codes;
}

// ---------------------------------------------------------------- validation
final class ValidationError extends RuntimeException
{
}

function nx_validate_collection(array $col, $data)
{
    if ($col['type'] === 'list') {
        return nx_validate_list($col, $data, $col['label'], null);
    }
    return nx_validate_object($col['fields'], $data, $col['label'], $data);
}

function nx_validate_object(array $fields, $data, string $where, $root): array
{
    if (!is_array($data) || array_is_list($data) && $data) {
        throw new ValidationError("$where: expected a group of fields.");
    }
    $out = $data;
    foreach ($fields as $fd) {
        $out[$fd['key']] = nx_validate_field($fd, $data[$fd['key']] ?? null, "$where › {$fd['label']}", $root);
    }
    return $out;
}

function nx_validate_list(array $def, $data, string $where, $root): array
{
    if ($data === null) {
        return [];
    }
    if (!is_array($data) || !array_is_list($data)) {
        throw new ValidationError("$where: expected a list.");
    }
    $out = [];
    $slugs = [];
    foreach ($data as $i => $item) {
        $label = is_array($item) ? trim((string) ($item[$def['item_label'] ?? ''] ?? '')) : '';
        $iw = $where . ' › ' . ($label !== '' ? '"' . mb_substr($label, 0, 40) . '"' : 'item ' . ($i + 1));
        $clean = nx_validate_object($def['fields'], $item, $iw, $root);
        if (isset($clean['slug']) && $clean['slug'] !== '') {
            if (isset($slugs[$clean['slug']])) {
                throw new ValidationError("$iw: the web address \"{$clean['slug']}\" is already used by another item.");
            }
            $slugs[$clean['slug']] = true;
        }
        $out[] = $clean;
    }
    return $out;
}

function nx_validate_field(array $fd, $v, string $where, $root)
{
    $type = $fd['type'];
    $required = !empty($fd['required']);

    switch ($type) {
        case 'object':
            return nx_validate_object($fd['fields'], $v ?? [], $where, $root);
        case 'list':
            return nx_validate_list($fd, $v, $where, $root);
        case 'strings':
        case 'paragraphs':
            $items = is_array($v) ? $v : [];
            $items = array_values(array_filter(array_map(fn ($s) => is_string($s) ? trim($s) : '', $items), fn ($s) => $s !== ''));
            foreach ($items as $s) {
                nx_check_len($s, 5000, $where);
            }
            return $items;
        case 'checkbox':
            if ($required && $v !== true) {
                throw new ValidationError("$where: please confirm this before saving.");
            }
            return $v === true;
    }

    $s = is_scalar($v) ? trim((string) $v) : '';
    if ($s === '') {
        if ($required) {
            throw new ValidationError("$where: this field is required.");
        }
        return '';
    }
    nx_check_len($s, $type === 'markdown' ? 60000 : 5000, $where);

    switch ($type) {
        case 'slug':
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s)) {
                throw new ValidationError("$where: use lowercase letters, numbers and hyphens only (for example study-in-germany).");
            }
            break;
        case 'email':
            if (!filter_var($s, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationError("$where: this does not look like an email address.");
            }
            break;
        case 'url':
            if (!preg_match('#^https?://[^\s"\'<>]+$#', $s)) {
                throw new ValidationError("$where: enter a full link starting with https://");
            }
            break;
        case 'date':
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                throw new ValidationError("$where: use the date picker (YYYY-MM-DD).");
            }
            break;
        case 'image':
            if (!preg_match('#^/(assets|uploads)/[A-Za-z0-9/_.\-]+$#', $s) || str_contains($s, '..')) {
                throw new ValidationError("$where: choose an image with the upload button.");
            }
            break;
        case 'icon':
            if (!in_array($s, NX_ICONS, true)) {
                throw new ValidationError("$where: choose an icon from the list.");
            }
            break;
        case 'flag':
            if (!preg_match('/^[a-z]{2}$/', $s)) {
                throw new ValidationError("$where: choose a flag from the list.");
            }
            break;
        case 'select':
            $opts = isset($fd['options']) ? array_keys($fd['options']) : (is_array($root[$fd['options_from']] ?? null) ? $root[$fd['options_from']] : []);
            if ($opts && !in_array($s, array_map('strval', $opts), true)) {
                throw new ValidationError("$where: choose one of the options.");
            }
            break;
    }
    if (!empty($fd['pattern']) && !preg_match('/' . $fd['pattern'] . '/', $s)) {
        throw new ValidationError("$where: this value is not in the expected format.");
    }
    return $s;
}

function nx_check_len(string $s, int $max, string $where): void
{
    if (mb_strlen($s) > $max) {
        throw new ValidationError("$where: this is too long (maximum $max characters).");
    }
}
