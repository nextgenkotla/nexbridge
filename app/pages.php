<?php
declare(strict_types=1);

function pg_home(): array
{
    $_ = 'nx_id';
    $SITE = C('SITE');
    $HOME = C('HOME');
    $ABOUT = C('ABOUT');
    $DEST = C('DESTINATIONS');
    $CATS = C('CATEGORIES');
    $eyebrow = $HOME['hero_eyebrow'] !== '' ? '<span class="eyebrow on-dark">' . e($HOME['hero_eyebrow']) . '</span>' : '';
    $lead = $HOME['hero_lead'] !== '' ? '<p class="lead">' . e($HOME['hero_lead']) . '</p>' : '';
    $heroImg = $HOME['hero_image'] !== '' ? '<img class="hero-person" src="' . $HOME['hero_image'] . '" alt="" width="560" height="760" fetchpriority="high">' : '';
    $flags = implode('', array_map(fn ($d) => flag($d['flag'], 24), array_slice($DEST, 0, 4)));
    $nCat = count($CATS);
    $nDest = count($DEST);
    $nSvc = C('N_SERVICES');
    $hero = <<<HTML
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      {$eyebrow}
      <h1>{$_(highlight($HOME['hero_title']))}</h1>
      {$lead}
      <div class="hero-actions">{$_(btn_primary('Explore Our Services', '/services/', 'layers'))}{$_(btn_light('Book a Consultation', '/book-consultation/', 'calendar'))}</div>
      <ul class="hero-proof">
        <li><strong>{$nCat}</strong><span>consultancy areas</span></li>
        <li><strong>{$nSvc}</strong><span>specialist services</span></li>
        <li><strong>{$nDest}</strong><span>study destinations</span></li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="hero-orb"></div>
      {$heroImg}
      <div class="float-card fc-1">{$flags}<span>{$nDest} study destinations</span></div>
      <div class="float-card fc-2">{$_(icon('shield'))}<span>Transparent, step-by-step guidance</span></div>
    </div>
  </div>
</section>

HTML;
    $points = $HOME['about_points'] ? checklist($HOME['about_points']) : '';
    $about = <<<HTML
<section class="section">
  <div class="container split">
    <div class="split-media">
      <img src="{$HOME['about_image']}" alt="A Nexbridge Solutions adviser in the office" width="640" height="760" loading="lazy" decoding="async">
      <div class="media-badge">{$_(icon('users'))}<span>Advisers who follow your case from first call to final outcome</span></div>
    </div>
    <div class="split-copy">
      {$_(section_head('About Nexbridge', 'Connecting ambition with opportunity', '', false))}
      <p>{$_(e($ABOUT['intro'][0] ?? ''))}</p>
      <div class="vmc">
        <div><h3>{$_(icon('eye'))}Our vision</h3><p>{$_(e($ABOUT['vision']))}</p></div>
        <div><h3>{$_(icon('globe'))}Our mission</h3><p>{$_(e($ABOUT['mission']))}</p></div>
        <div><h3>{$_(icon('shield'))}Our commitment</h3><p>{$_(e($ABOUT['commitment']))}</p></div>
      </div>
      <h3 class="mini-head">Why choose Nexbridge?</h3>
      {$points}
      <a class="btn btn-outline" href="/about/">More about us {$_(icon('arrow'))}</a>
    </div>
  </div>
</section>

HTML;
    $catCards = implode('', array_map('category_card', $CATS));
    $services = <<<HTML
<section class="section tint">
  <div class="container">
    {$_(section_head('Our consultancy services', 'Expert guidance across ' . strtolower(num_word($nCat)) . ' areas', 'From your first university application to registering your company, our specialists handle the details so you can focus on your goals.'))}
    <div class="cat-grid">{$catCards}</div>
    <div class="center-row">{$_(btn_primary('View all services', '/services/'))}</div>
  </div>
</section>

HTML;
    $destCards = implode('', array_map('dest_card', array_slice($DEST, 0, 8)));
    $dests = <<<HTML
<section class="section">
  <div class="container">
    {$_(section_head('Study abroad destinations', 'Where would you like to study?', 'Explore universities, costs, scholarships and visa requirements country by country.'))}
    <div class="dest-grid-cards">{$destCards}</div>
    <div class="center-row">{$_(btn_primary("See all {$nDest} destinations", '/destinations/'))}</div>
  </div>
</section>

HTML;
    $why = <<<HTML
<section class="section tint">
  <div class="container">
    {$_(section_head('Why choose us', 'Guidance you can trust', 'We built Nexbridge around the things clients told us mattered most.'))}
    {$_(why_grid())}
  </div>
</section>

HTML;
    $process = <<<HTML
<section class="section navy-section">
  <div class="container">
    {$_(section_head('Our process', 'Five clear steps, every time', 'Whatever service you choose, you always know what happens next.'))}
    {$_(process_band())}
  </div>
</section>

HTML;
    $STORIES = C('STORIES');
    if ($STORIES) {
        $featured = $STORIES;
        usort($featured, fn ($a, $b) => (int) $b['featured'] - (int) $a['featured']);
        $cards = implode('', array_map('story_card', array_slice($featured, 0, 3)));
        $stories = <<<HTML
<section class="section">
  <div class="container">
    {$_(section_head('Success stories', 'Real results, shared with permission', "Visa approvals, admissions and client stories, published with our clients' consent."))}
    <div class="story-grid">{$cards}</div>
    <div class="center-row">{$_(btn_primary('Read more success stories', '/success-stories/'))}</div>
  </div>
</section>

HTML;
    } else {
        $stories = <<<HTML
<section class="section">
  <div class="container">
    {$_(section_head('Success stories', 'Real results, shared with permission', "We publish visa approvals, admissions and client stories only with our clients' consent. Verified stories are being added here."))}
    <div class="story-types">
      <a href="/success-stories/#visa">{$_(icon('passport'))}<h3>Visa success stories</h3><p>Study, visit and business visa approvals.</p></a>
      <a href="/success-stories/#admissions">{$_(icon('cap'))}<h3>Admission success stories</h3><p>Offers from universities around the world.</p></a>
      <a href="/success-stories/#testimonials">{$_(icon('quote'))}<h3>Client testimonials</h3><p>In our clients' own words.</p></a>
      <a href="/success-stories/#achievements">{$_(icon('trophy'))}<h3>Achievements &amp; case studies</h3><p>Scholarships, registrations and milestones.</p></a>
    </div>
  </div>
</section>

HTML;
    }
    $PARTNERS = C('PARTNERS');
    if ($PARTNERS) {
        $logos = implode('', array_map('partner_logo', array_slice($PARTNERS, 0, 12)));
        $partners = <<<HTML
<section class="section tint">
  <div class="container">
    {$_(section_head('Our partners', 'Building partnerships beyond borders', 'Led by our Director of Partner Relations, we work with universities, institutions and organizations to create better opportunities for our clients.'))}
    <div class="logo-grid wide">{$logos}</div>
    <div class="center-row">{$_(btn_primary('See all partners', '/partners/'))}</div>
  </div>
</section>

HTML;
    } else {
        $partners = <<<HTML
<section class="section tint">
  <div class="container">
    {$_(section_head('Our partners', 'Building partnerships beyond borders', 'Led by our Director of Partner Relations, we work with universities, institutions and organizations to create better opportunities for our clients. Partner logos appear here once authorized.'))}
    <div class="partner-types">
      <div>{$_(icon('cap'))}<h3>Universities</h3></div>
      <div>{$_(icon('book'))}<h3>Educational institutions</h3></div>
      <div>{$_(icon('building'))}<h3>Corporate partners</h3></div>
      <div>{$_(icon('globe'))}<h3>International organizations</h3></div>
    </div>
    <div class="center-row">{$_(btn_primary('Partner with Nexbridge', '/partners/'))}</div>
  </div>
</section>

HTML;
    }
    $posts = implode('', array_map('article_card', array_slice(C('ARTICLES'), 0, 3)));
    $insights = <<<HTML
<section class="section">
  <div class="container">
    {$_(section_head('Latest updates & insights', 'Guides, visa updates and advice', 'Practical articles on study abroad, scholarships, visas, business and tax.'))}
    <div class="post-grid">{$posts}</div>
    <div class="center-row"><a class="btn btn-outline" href="/insights/">Visit the Knowledge Center {$_(icon('arrow'))}</a></div>
  </div>
</section>

HTML;
    $contact = <<<HTML
<section class="section tint" id="contact">
  <div class="container">
    {$_(section_head('Contact & consultation', "Let's plan your next step together", 'Send an inquiry, book an appointment or message us. We reply with clear next steps.'))}
    <div class="contact-grid">{$_(inquiry_form())}{$_(contact_panel())}</div>
  </div>
</section>

HTML;
    $org = ['@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => $SITE['name'], 'url' => $SITE['url'] . '/',
        'logo' => $SITE['url'] . '/assets/img/logo.png', 'image' => $SITE['url'] . '/assets/img/og.png', 'description' => $SITE['description']];
    foreach (['phone' => 'telephone', 'email' => 'email', 'address' => 'address'] as $k => $ldk) {
        if ($SITE[$k] !== '') {
            $org[$ldk] = $SITE[$k];
        }
    }
    $org['sameAs'] = array_values(array_filter($SITE['socials']));
    return page($SITE['name'], $SITE['description'], $hero . $about . $services . $dests . $why . $process . $stories . $partners . $insights . $contact, [$org]);
}

function pg_about(): array
{
    $_ = 'nx_id';
    $ABOUT = C('ABOUT');
    $SITE = C('SITE');
    $HOME = C('HOME');
    [$hero, $bc] = page_hero('About Nexbridge Solutions', 'Professional, transparent and personalized guidance for education, immigration, business and travel.',
        [['About Us', '/about/']], 'Who we are',
        btn_primary('Meet our team', '/about/team/', 'users') . btn_light('Book a Consultation', '/book-consultation/', 'calendar'));
    $intro = implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $ABOUT['intro']));
    $values = implode('', array_map(fn ($v) => '<div class="value"><h3>' . e($v[0]) . '</h3><p>' . e($v[1]) . '</p></div>', $ABOUT['values']));
    $cats = implode('', array_map('category_card', C('CATEGORIES')));
    $team = implode('', array_map('team_card', C('TEAM')));
    $nCat = num_word(count(C('CATEGORIES')));
    $body = <<<HTML
<section class="section">
  <div class="container split">
    <div class="split-copy">
      {$_(section_head('Company introduction', 'Clear pathways between ambition and opportunity', '', false))}
      {$intro}
      <p class="pull">{$_(e($SITE['tagline']))}</p>
    </div>
    <div class="split-media"><img src="{$HOME['about_image']}" alt="A Nexbridge Solutions adviser in the office" width="640" height="760" loading="lazy" decoding="async"></div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    <div class="vmc-cards">
      <div class="card">{$_(icon('eye', 'icon icon-lg'))}<h2>Our vision</h2><p>{$_(e($ABOUT['vision']))}</p></div>
      <div class="card">{$_(icon('globe', 'icon icon-lg'))}<h2>Our mission</h2><p>{$_(e($ABOUT['mission']))}</p></div>
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    {$_(section_head('Our core values', 'What guides our work'))}
    <div class="values">{$values}</div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    {$_(section_head('Our expertise', "{$nCat} areas of specialist consultancy"))}
    <div class="cat-grid">{$cats}</div>
  </div>
</section>
<section class="section">
  <div class="container">
    {$_(section_head('Our team & leadership', 'The people behind Nexbridge'))}
    <div class="team-grid">{$team}</div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    {$_(section_head('Why Nexbridge Solutions?', 'Guidance you can trust'))}
    {$_(why_grid())}
  </div>
</section>
<section class="section">
  <div class="container narrow center-text">
    {$_(section_head('Our commitment to clients', 'Every journey is unique'))}
    <p class="big-text">{$_(e($ABOUT['commitment']))} We promise honest advice, clear communication about what each service involves, careful handling of your documents and personal information, and support from your first consultation to the final outcome.</p>
  </div>
</section>

HTML;
    return page('About Us', 'Learn about Nexbridge Solutions: our vision, mission, core values, expertise and leadership team.',
        $hero . $body . cta_band('Start your journey with Nexbridge', 'Book a consultation and get clear, honest advice on your plans.'), [$bc]);
}

function pg_team(): array
{
    [$hero, $bc] = page_hero('Our Team', 'Meet the leadership team building pathways between ambition and opportunity.',
        [['About Us', '/about/'], ['Our Team', '/about/team/']], 'Leadership');
    $body = '<section class="section"><div class="container"><div class="team-grid">' . implode('', array_map('team_card', C('TEAM'))) . '</div></div></section>';
    return page('Our Team', 'Meet the Nexbridge Solutions leadership team.', $hero . $body . cta_band('Talk to our team', 'Book a consultation with a Nexbridge adviser.'), [$bc]);
}

function pg_member(array $m): array
{
    $_ = 'nx_id';
    $SITE = C('SITE');
    [$crumbHtml, $bc] = crumbs([['About Us', '/about/'], ['Our Team', '/about/team/'], [$m['name'], "/about/team/{$m['slug']}/"]]);
    $others = implode('', array_map('team_card', array_filter(C('TEAM'), fn ($o) => $o['slug'] !== $m['slug'])));
    $photo = $m['photo'] ?: '/assets/img/logo.png';
    $bio = implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $m['bio']));
    $body = <<<HTML
<section class="profile-hero">
  <div class="container profile-grid">
    <div class="profile-photo"><img src="{$photo}" alt="{$_(e($m['name']))}" width="480" height="600"></div>
    <div class="profile-copy">{$crumbHtml}<span class="eyebrow on-dark">Nexbridge leadership</span><h1>{$_(e($m['name']))}</h1><span class="role-pill">{$_(e($m['role']))}</span><p class="lead">{$_(e($m['summary']))}</p></div>
  </div>
</section>
<section class="section"><div class="container narrow prose">{$bio}</div></section>
<section class="section tint"><div class="container">{$_(section_head('Our team', 'Meet the rest of the team'))}<div class="team-grid three">{$others}</div></div></section>

HTML;
    $person = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $m['name'], 'jobTitle' => $m['role'],
        'worksFor' => ['@type' => 'Organization', 'name' => $SITE['name']]];
    if ($m['photo']) {
        $person['image'] = $SITE['url'] . $m['photo'];
    }
    return page("{$m['name']}, {$m['role']}", "{$m['name']} is {$m['role']} at Nexbridge Solutions. {$m['summary']}", $body, [$bc, $person]);
}

function pg_services(): array
{
    [$hero, $bc] = page_hero('Consultancy Services', C('N_SERVICES') . ' specialist services across education, immigration, business, tax, travel and language preparation.',
        [['Consultancy Services', '/services/']], 'What we do',
        btn_primary('Book a Consultation', '/book-consultation/', 'calendar') . btn_light('Ask a question', '#inquiry', 'send'));
    $jump = '';
    $blocks = '';
    foreach (C('CATEGORIES') as $c) {
        $jump .= '<a href="#' . $c['slug'] . '">' . icon($c['icon']) . e($c['short_name']) . '</a>';
        $links = implode('', array_map(fn ($s) => '<li><a href="' . svc_url($c, $s) . '"><strong>' . e($s['name']) . '</strong><span>' . e($s['short']) . '</span></a></li>', $c['services']));
        $blocks .= '<section class="svc-block" id="' . $c['slug'] . "\">\n"
            . '  <div class="svc-block-head"><span class="icon-badge">' . icon($c['icon']) . '</span><div><h2><a href="' . cat_url($c) . '">' . e($c['name']) . '</a></h2><p>' . e($c['summary']) . "</p></div></div>\n"
            . '  <ul class="svc-list">' . $links . "</ul>\n</section>";
    }
    $body = '<section class="section"><div class="container"><nav class="jump-nav" aria-label="Service categories">' . $jump . '</nav>' . $blocks . '</div></section>';
    return page('Consultancy Services', 'All Nexbridge Solutions consultancy services: study abroad, immigration and visa, business, tax, travel and language preparation.',
        $hero . $body . consult_section(), [$bc]);
}

function pg_category(array $c): array
{
    $_ = 'nx_id';
    [$hero, $bc] = page_hero($c['name'], $c['summary'], [['Consultancy Services', '/services/'], [$c['short_name'], cat_url($c)]], 'Consultancy services',
        btn_primary('Book a Consultation', '/book-consultation/', 'calendar') . btn_light('Send an inquiry', '#inquiry', 'send'),
        '<span class="hero-icon">' . icon($c['icon']) . '</span>');
    $cards = implode('', array_map(fn ($s) => service_card($c, $s), $c['services']));
    $body = <<<HTML
<section class="section">
  <div class="container narrow center-text"><p class="big-text">{$_(e($c['intro']))}</p></div>
  <div class="container"><div class="svc-grid">{$cards}</div></div>
</section>
<section class="section tint"><div class="container">{$_(section_head('Why Nexbridge', 'Benefits of choosing us'))}{$_(benefit_grid($c))}</div></section>
<section class="section"><div class="container narrow">{$_(section_head('How it works', 'Our step-by-step process'))}{$_(steps($c['process']))}</div></section>

HTML;
    return page($c['name'], $c['summary'] . ' Explore ' . count($c['services']) . ' ' . strtolower($c['short_name']) . ' services from Nexbridge Solutions.',
        $hero . $body . consult_section(), [$bc]);
}

function related_for(array $c, array $s): string
{
    $CAT = C('CAT_BY_SLUG');
    $DEST = C('DEST_BY_SLUG');
    $out = [];
    foreach ($s['related'] as $ref) {
        $parts = explode('/', $ref);
        if (count($parts) !== 2) {
            continue;
        }
        [$cs, $ss] = $parts;
        if ($cs === 'study-abroad') {
            if (isset($DEST[$ss])) {
                $out[] = ['dest', $DEST[$ss], $ss];
            }
        } elseif (isset($CAT[$cs])) {
            foreach ($CAT[$cs]['services'] as $x) {
                if ($x['slug'] === $ss) {
                    $out[] = ['svc', [$CAT[$cs], $x], $cs . '/' . $ss];
                }
            }
        }
    }
    foreach ($c['services'] as $x) {
        if (count($out) >= 3) {
            break;
        }
        $key = $c['slug'] . '/' . $x['slug'];
        if ($x['slug'] !== $s['slug'] && !in_array($key, array_column($out, 2), true)) {
            $out[] = ['svc', [$c, $x], $key];
        }
    }
    return implode('', array_map(fn ($o) => $o[0] === 'dest' ? dest_card($o[1]) : service_card($o[1][0], $o[1][1]), array_slice($out, 0, 3)));
}

function pg_service(array $c, array $s): array
{
    $_ = 'nx_id';
    $SITE = C('SITE');
    $trail = [['Consultancy Services', '/services/'], [$c['short_name'], cat_url($c)], [$s['name'], svc_url($c, $s)]];
    $waMsg = "Hello Nexbridge Solutions, I would like to ask about {$s['name']}.";
    $wa = btn_wa('Ask on WhatsApp', $waMsg);
    $book = '/book-consultation/?interest=' . qp($s['name']);
    [$hero, $bc] = page_hero($s['name'], $s['short'], $trail, $c['short_name'],
        btn_primary('Book a Consultation', $book, 'calendar') . ($wa ?: btn_light('Send an inquiry', '#inquiry', 'send')),
        '<span class="hero-icon">' . icon($c['icon']) . '</span>');
    $toc = [['overview', 'Overview'], ['offer', 'What we offer'], ['benefits', 'Benefits']];
    $sec = '<section id="overview"><h2>Introduction to the service</h2><p>' . e($s['intro']) . '</p></section>'
        . '<section id="offer"><h2>What we offer</h2>' . checklist($s['offers'], 'checklist two-col') . '</section>'
        . '<section id="benefits"><h2>Benefits of choosing Nexbridge</h2>' . benefit_grid($c) . '</section>';
    if ($s['requirements']) {
        $toc[] = ['requirements', 'Requirements'];
        $sec .= '<section id="requirements"><h2>Eligibility &amp; requirements</h2>' . checklist($s['requirements']) . '</section>';
    }
    $toc[] = ['process', 'Process'];
    $sec .= '<section id="process"><h2>Step-by-step process</h2>' . steps($c['process']) . '</section>';
    if ($s['documents']) {
        $toc[] = ['documents', 'Documents'];
        $sec .= '<section id="documents"><h2>Required documents</h2>' . checklist($s['documents'], 'checklist docs two-col')
            . '<p class="note">Requirements can vary by case. We confirm the exact list during your consultation.</p></section>';
    }
    $toc[] = ['faqs', 'FAQs'];
    $sec .= '<section id="faqs"><h2>Frequently asked questions</h2>' . faq_list($s['faqs']) . '</section>';
    $tocHtml = implode('', array_map(fn ($t) => '<li><a href="#' . $t[0] . '">' . e($t[1]) . '</a></li>', $toc));
    $siblings = implode('', array_map(fn ($x) => '<li><a href="' . svc_url($c, $x) . '"' . ($x['slug'] === $s['slug'] ? ' aria-current=page' : '') . '>' . e($x['name']) . '</a></li>', $c['services']));
    $aside = <<<HTML
<aside class="svc-aside">
  <div class="aside-card accent">
    <h3>Talk to an adviser</h3>
    <p>Get clear advice on {$_(e($s['name']))} for your situation.</p>
    {$_(btn_light('Book a Consultation', $book, 'calendar'))}
    {$wa}
    <a class="aside-link" href="#inquiry">Or send an inquiry {$_(icon('arrow'))}</a>
  </div>
  <nav class="aside-card" aria-label="On this page"><h3>On this page</h3><ul class="toc">{$tocHtml}</ul></nav>
  <nav class="aside-card" aria-label="{$_(e($c['short_name']))} services"><h3>{$_(e($c['short_name']))}</h3><ul class="sibling-list">{$siblings}</ul></nav>
</aside>
HTML;
    $waBand = '';
    if ($SITE['whatsapp'] !== '') {
        $waBand = '<section class="wa-band"><div class="container wa-inner">' . icon('whatsapp') . '<p><strong>Have a quick question about ' . e($s['name'])
            . '?</strong> Message our team on WhatsApp.</p>' . btn_wa('Chat on WhatsApp', $waMsg) . '</div></section>';
    }
    $body = <<<HTML
<div class="container with-aside">
  <article class="svc-main">{$sec}</article>
  {$aside}
</div>
<section class="section tint"><div class="container">{$_(section_head('Related services', 'You may also need'))}<div class="svc-grid">{$_(related_for($c, $s))}</div></div></section>
{$_(consult_section($s['name'], "Get started with {$s['name']}", 'Tell us about your situation and an adviser will reply with clear next steps.'))}
{$waBand}
HTML;
    $ld = [$bc, faq_ld($s['faqs']), ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $s['name'], 'description' => $s['intro'],
        'serviceType' => $c['name'], 'provider' => ['@type' => 'Organization', 'name' => $SITE['name'], 'url' => $SITE['url']]]];
    return page($s['name'], "{$s['short']} Expert " . strtolower($c['short_name']) . ' consultancy from Nexbridge Solutions.', $hero . $body, $ld);
}

function pg_study_abroad(): array
{
    $_ = 'nx_id';
    $edu = C('CAT_BY_SLUG')['education-study-abroad'];
    $DEST = C('DESTINATIONS');
    [$hero, $bc] = page_hero('Study Abroad', 'Choose the right country, university and program, then apply, secure your visa and settle in with one team beside you.',
        [['Study Abroad', '/study-abroad/']], 'Education & study abroad',
        btn_primary('Book a Consultation', '/book-consultation/?interest=Study+Abroad+Counseling', 'calendar') . btn_light('Explore destinations', '#destinations', 'globe'),
        '<div class="hero-flags">' . implode('', array_map(fn ($d) => flag($d['flag'], 40), $DEST)) . '</div>');
    $other = join_words(C('OTHER_DESTINATIONS')) ?: 'other countries';
    $svcLinks = implode('', array_map(fn ($s) => '<li><a href="' . svc_url($edu, $s) . '"><strong>' . e($s['name']) . '</strong><span>' . e($s['short']) . '</span></a></li>', $edu['services']));
    $cards = implode('', array_map('dest_card', $DEST));
    $nSvc = num_word(count($edu['services']));
    $body = <<<HTML
<section class="section">
  <div class="container narrow center-text"><p class="big-text">{$_(e($edu['intro']))}</p></div>
</section>
<section class="section tint" id="destinations">
  <div class="container">
    {$_(section_head('Destinations', 'Country-wise study destinations', 'Select a country to see its education system, programs, requirements, costs, scholarships and visa process.'))}
    <div class="dest-grid-cards">{$cards}</div>
    <div class="other-dest"><h3>Other available destinations</h3><p>We also guide students applying to {$_(e($other))}. <a href="/book-consultation/?interest=Other+study+destination">Ask us about another country</a>.</p></div>
  </div>
</section>
<section class="section">
  <div class="container">
    {$_(section_head('Education services', 'Support at every stage', "{$nSvc} services that cover your whole journey, from first counseling session to life after arrival."))}
    <ul class="svc-list">{$svcLinks}</ul>
  </div>
</section>
<section class="section tint"><div class="container narrow">{$_(section_head('How it works', 'Your study abroad journey'))}{$_(steps($edu['process']))}</div></section>

HTML;
    $first = implode(', ', array_map(fn ($d) => $d['name'], array_slice($DEST, 0, 4)));
    return page('Study Abroad', "Study abroad with Nexbridge Solutions: university selection, admissions, scholarships and student visas for {$first} and " . max(count($DEST) - 4, 0) . ' more destinations.',
        $hero . $body . consult_section('Study Abroad Counseling'), [$bc]);
}

function pg_destinations(): array
{
    $_ = 'nx_id';
    $DEST = C('DESTINATIONS');
    [$hero, $bc] = page_hero('Our Destinations', num_word(count($DEST)) . ' countries, one team to guide your application from shortlist to arrival.',
        [['Our Destinations', '/destinations/']], 'Study destinations', btn_primary('Book a Consultation', '/book-consultation/', 'calendar'));
    $other = implode('', array_map(fn ($o) => '<li>' . e(mb_strtoupper(mb_substr($o, 0, 1)) . mb_substr($o, 1)) . '</li>', C('OTHER_DESTINATIONS')));
    $cards = implode('', array_map('dest_card', $DEST));
    $body = <<<HTML
<section class="section"><div class="container">
  <div class="dest-grid-cards">{$cards}</div>
  <div class="other-dest"><h3>Other available destinations</h3><ul class="pill-list">{$other}</ul><p><a href="/book-consultation/?interest=Other+study+destination">Ask about another destination {$_(icon('arrow'))}</a></p></div>
</div></section>
HTML;
    return page('Our Destinations', 'Study destinations supported by Nexbridge Solutions: ' . join_words(array_map(fn ($d) => $d['name'], $DEST)) . '.',
        $hero . $body . cta_band('Not sure which country is right for you?', 'Our counselors compare destinations for your profile and budget.'), [$bc]);
}

function pg_destination(array $d): array
{
    $_ = 'nx_id';
    $trail = [['Study Abroad', '/study-abroad/'], [$d['name'], dest_url($d)]];
    $interest = qp("Study in {$d['name']}");
    $aside = $d['image'] ? '<div class="dest-hero-media"><img src="' . $d['image'] . '" alt="Studying in ' . e($d['name']) . '" width="520" height="560"></div>'
        : '<div class="dest-hero-flag">' . flag($d['flag'], 160, 'flag flag-xl') . '</div>';
    [$hero, $bc] = page_hero("Study in {$d['name']}", $d['tagline'], $trail, 'Study destination',
        btn_primary('Apply Now', "/book-consultation/?interest=$interest", 'send') . btn_light('Book a Consultation', "/book-consultation/?interest=$interest", 'calendar'),
        $aside, 'dest-hero');
    $facts = implode('', array_map(fn ($p) => '<li>' . icon('check') . e($p) . '</li>', $d['card_points']));
    $schol = '<div class="scholarships">' . implode('', array_map(fn ($x) => '<div class="scholarship">' . icon('award') . '<div><h3>' . e($x[0]) . '</h3><p>' . e($x[1]) . '</p></div></div>', $d['scholarships'])) . '</div>';
    $sections = [
        ['overview', "Introduction to {$d['name']}", '<p>' . e($d['intro']) . '</p>'],
        ['system', 'Education system', '<p>' . e($d['system']) . '</p>'],
        ['programs', 'Available programs', checklist($d['programs'], 'checklist two-col')],
        ['requirements', 'Admission requirements', checklist($d['requirements'])],
        ['tuition', 'Tuition fee overview', '<p>' . e($d['tuition']) . '</p><p class="note">Fees change every year. We share current figures for the universities on your shortlist.</p>'],
        ['scholarships', 'Scholarships & funding', $schol],
        ['process', 'Application process', str_replace('<p></p>', '', steps(array_map(fn ($st) => [$st, ''], $d['process'])))],
        ['visa', 'Student visa information', '<p>' . e($d['visa']) . '</p><p class="note">Visa decisions are made only by the relevant embassy or immigration authority. Requirements change, and we check the current rules for every application.</p>'],
        ['faqs', 'Frequently asked questions', faq_list($d['faqs'])],
    ];
    $toc = implode('', array_map(fn ($x) => '<li><a href="#' . $x[0] . '">' . e($x[1]) . '</a></li>', $sections));
    $secs = implode('', array_map(fn ($x) => '<section id="' . $x[0] . '"><h2>' . e($x[1]) . '</h2>' . $x[2] . '</section>', $sections));
    $others = implode('', array_map('dest_card', array_slice(array_values(array_filter(C('DESTINATIONS'), fn ($x) => $x['slug'] !== $d['slug'])), 0, 4)));
    $body = <<<HTML
<div class="fact-strip"><div class="container"><ul>{$facts}</ul></div></div>
<div class="container with-aside">
  <article class="svc-main">{$secs}</article>
  <aside class="svc-aside">
    <div class="aside-card accent"><h3>Apply to study in {$_(e($d['name']))}</h3><p>Get a shortlist of universities and a clear plan for your application and visa.</p>
      {$_(btn_light('Apply Now', "/book-consultation/?interest=$interest", 'send'))}{$_(btn_wa('Ask on WhatsApp', "Hello Nexbridge Solutions, I would like to study in {$d['name']}."))}</div>
    <nav class="aside-card" aria-label="On this page"><h3>On this page</h3><ul class="toc">{$toc}</ul></nav>
  </aside>
</div>
<section class="section tint"><div class="container">{$_(section_head('More destinations', 'Compare other countries'))}<div class="dest-grid-cards">{$others}</div></div></section>
{$_(consult_section("Study in {$d['name']}", "Start your application to {$d['name']}", 'Tell us your academic background and goals, and we will suggest universities and next steps.'))}
HTML;
    return page("Study in {$d['name']}", "Study in {$d['name']} with Nexbridge Solutions: {$d['tagline']} Programs, admission requirements, tuition, scholarships and student visa guidance.",
        $hero . $body, [$bc, faq_ld($d['faqs'])]);
}

function pg_business_tax(): array
{
    $_ = 'nx_id';
    $CAT = C('CAT_BY_SLUG');
    $biz = $CAT['business-corporate'];
    $tax = $CAT['tax-legal'];
    [$hero, $bc] = page_hero('Business & Tax Consultancy', 'Company and firm registration, SECP and FBR, tax returns, trademarks and strategic advice for businesses and individuals.',
        [['Business & Tax Consultancy', '/business-tax-consultancy/']], 'For entrepreneurs, businesses & individuals',
        btn_primary('Book a Consultation', '/book-consultation/?interest=Business+Advisory+%26+Consultation', 'calendar') . btn_light('Send an inquiry', '#inquiry', 'send'),
        '<span class="hero-icon">' . icon('briefcase') . '</span>');
    $blocks = '';
    foreach ([$biz, $tax] as $c) {
        $tint = $c['slug'] === $tax['slug'] ? ' tint' : '';
        $cards = implode('', array_map(fn ($s) => service_card($c, $s), $c['services']));
        $blocks .= <<<HTML
<section class="section{$tint}"><div class="container">
  {$_(section_head($c['short_name'], $c['name'], $c['intro']))}
  <div class="svc-grid">{$cards}</div>
</div></section>
HTML;
    }
    return page('Business & Tax Consultancy', 'Business setup, company and SECP registration, NTN and FBR registration, income tax returns and trademark registration in Pakistan.',
        $hero . $blocks . '<section class="section"><div class="container">' . section_head('Why Nexbridge', 'Benefits for your business') . benefit_grid($biz) . '</div></section>'
        . consult_section('Business Advisory & Consultation'), [$bc]);
}

function pg_partners(): array
{
    [$hero, $bc] = page_hero('Our Partners', 'We build long-term partnerships that open doors to quality education, professional growth and international opportunity.',
        [['Our Partners', '/partners/']], 'Partnerships', btn_primary('Become a partner', '#inquiry', 'handshake'));
    $cards = '';
    foreach (C('PARTNER_TYPES') as [$kind, $i, $t, $d]) {
        $mine = array_filter(C('PARTNERS'), fn ($p) => $p['type'] === $kind);
        $logos = $mine ? '<div class="logo-grid">' . implode('', array_map('partner_logo', $mine)) . '</div>' : '<div class="logo-slot">Authorized partner logos will appear here</div>';
        $cards .= '<div class="partner-card' . ($mine ? ' has-logos' : '') . '">' . icon($i, 'icon icon-lg') . '<h2>' . e($t) . '</h2><p>' . e($d) . '</p>' . $logos . '</div>';
    }
    $body = '<section class="section"><div class="container narrow center-text"><p class="big-text">Our partner relations team represents Nexbridge internationally, building strategic relationships with universities, institutions, organizations and global partners. We display partner names and logos only with their authorization.</p></div>'
        . "\n" . '<div class="container"><div class="partner-grid">' . $cards . "</div></div></section>\n"
        . consult_section('Not sure yet', 'Partner with Nexbridge Solutions', 'Universities, institutions and organizations interested in working with us can reach our partner relations team here.');
    return page('Our Partners', 'Nexbridge Solutions partners with universities, educational institutions, corporate partners and international organizations.', $hero . $body, [$bc]);
}

function pg_success(): array
{
    [$hero, $bc] = page_hero('Success Stories', "Visa approvals, admissions and client achievements, shared with our clients' permission.",
        [['Success Stories', '/success-stories/']], 'Results', btn_primary('Share your story', '#inquiry', 'quote'));
    $STORIES = C('STORIES');
    $types = C('STORY_TYPES');
    usort($types, function ($a, $b) use ($STORIES) {
        $ha = (bool) array_filter($STORIES, fn ($s) => $s['type'] === $a[0]);
        $hb = (bool) array_filter($STORIES, fn ($s) => $s['type'] === $b[0]);
        return (int) $hb - (int) $ha;
    });
    $blocks = '';
    foreach ($types as [$a, $i, $t, $d]) {
        $mine = array_filter($STORIES, fn ($s) => $s['type'] === $a);
        $inner = $mine ? '<div class="story-grid">' . implode('', array_map('story_card', $mine)) . '</div>'
            : '<div class="gallery-empty">' . icon('calendar') . '<p>Verified stories are being collected with written consent from our clients and will be published here.</p></div>';
        $blocks .= '<section class="story-group" id="' . $a . '"><div class="story-group-head">' . icon($i, 'icon icon-lg') . '<div><h2>' . e($t) . '</h2><p>' . e($d) . '</p></div></div>' . $inner . '</section>';
    }
    $body = '<section class="section"><div class="container">' . $blocks . "</div></section>\n"
        . consult_section('Not sure yet', 'Are you a Nexbridge client?', 'We would love to share your journey. Tell us about your visa, admission or business success, and we will contact you for your consent before publishing anything.');
    return page('Success Stories', 'Visa approvals, university admissions, client testimonials and achievements from Nexbridge Solutions clients.', $hero . $body, [$bc]);
}

function pg_insights(): array
{
    [$hero, $bc] = page_hero('Knowledge Center', 'Study abroad guides, visa updates, scholarship information, business advice, tax updates and travel guides.',
        [['Knowledge Center', '/insights/']], 'Latest updates & insights');
    $ARTICLES = C('ARTICLES');
    $cats = C('INSIGHT_CATEGORIES');
    $extra = array_values(array_diff(array_unique(array_column($ARTICLES, 'category')), $cats));
    sort($extra);
    $chips = '<button class="chip active" type="button" data-filter="all">All</button>';
    foreach (array_merge($cats, $extra) as $c) {
        if ($c === '') {
            continue;
        }
        $chips .= '<button class="chip" type="button" data-filter="' . e($c) . '">' . e($c) . '</button>';
    }
    $posts = implode('', array_map('article_card', $ARTICLES));
    $body = <<<HTML
<section class="section"><div class="container">
  <div class="chips" role="group" aria-label="Filter articles">{$chips}</div>
  <div class="post-grid" data-posts>{$posts}</div>
  <p class="empty-filter" hidden data-empty>No articles in this category yet. Check back soon.</p>
</div></section>
HTML;
    return page('Knowledge Center', 'Study abroad guides, visa updates, scholarships, business advisory, tax updates and travel guides from Nexbridge Solutions.',
        $hero . $body . cta_band("Have a question we haven't covered?", 'Ask our advisers directly.'), [$bc]);
}

function pg_article(array $a): array
{
    $_ = 'nx_id';
    $SITE = C('SITE');
    [$crumbHtml, $bc] = crumbs([['Knowledge Center', '/insights/'], [$a['title'], "/insights/{$a['slug']}/"]]);
    $parts = $a['image'] ? '<figure class="article-cover"><img src="' . $a['image'] . '" alt="" width="960" height="540" decoding="async"></figure>' : '';
    $parts .= render_body($a['body']);
    $more = implode('', array_map('article_card', array_filter(C('ARTICLES'), fn ($x) => $x['slug'] !== $a['slug'])));
    $body = <<<HTML
<section class="page-hero article-hero"><div class="container narrow">{$crumbHtml}<span class="eyebrow on-dark">{$_(e($a['category']))}</span><h1>{$_(e($a['title']))}</h1>
<p class="article-meta"><time datetime="{$a['date']}">{$_(long_date($a['date']))}</time> · Nexbridge Solutions</p></div></section>
<section class="section"><div class="container narrow prose">{$parts}<div class="article-cta">{$_(btn_primary('Book a Consultation', '/book-consultation/', 'calendar'))}</div></div></section>
<section class="section tint"><div class="container">{$_(section_head('Keep reading', 'More from the Knowledge Center'))}<div class="post-grid">{$more}</div></div></section>
HTML;
    $ld = [$bc, ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $a['title'], 'datePublished' => $a['date'],
        'author' => ['@type' => 'Organization', 'name' => $SITE['name']], 'publisher' => ['@type' => 'Organization', 'name' => $SITE['name'],
            'logo' => ['@type' => 'ImageObject', 'url' => $SITE['url'] . '/assets/img/logo.png']], 'description' => $a['excerpt']]];
    if ($a['image']) {
        $ld[1]['image'] = $SITE['url'] . $a['image'];
    }
    return page($a['title'], $a['excerpt'], $body, $ld);
}

function pg_faqs(): array
{
    [$hero, $bc] = page_hero('Frequently Asked Questions', 'Answers to common questions about our consultancy, study abroad, visas, business and tax, and travel services.',
        [['FAQs', '/faqs/']], 'Help center');
    $jump = '';
    $groups = '';
    $all = [];
    foreach (C('FAQ_GROUPS') as $i => [$t, $items]) {
        $jump .= '<a href="#faq-' . $i . '">' . e($t) . '</a>';
        $groups .= '<section class="faq-group" id="faq-' . $i . '"><h2>' . e($t) . '</h2>' . faq_list($items) . '</section>';
        $all = array_merge($all, $items);
    }
    $body = '<section class="section"><div class="container narrow"><nav class="jump-nav small" aria-label="FAQ topics">' . $jump . '</nav>' . $groups . '</div></section>';
    return page('FAQs', 'Frequently asked questions about Nexbridge Solutions consultancy, study abroad, visas, business and tax, and travel services.',
        $hero . $body . consult_section('', 'Still have a question?'), [$bc, faq_ld($all)]);
}

function pg_contact(): array
{
    [$hero, $bc] = page_hero('Contact Us', 'Send an inquiry, book an appointment or message our team.', [['Contact Us', '/contact/']], 'Get in touch');
    $body = '<section class="section"><div class="container contact-grid">' . inquiry_form('', 'inquiry', 'Send us a message', 'Send message', 'Your message') . contact_panel() . '</div></section>';
    return page('Contact Us', 'Contact Nexbridge Solutions: send an inquiry, book a consultation or message us on WhatsApp.', $hero . $body, [$bc]);
}

function pg_booking(): array
{
    $_ = 'nx_id';
    [$hero, $bc] = page_hero('Book a Consultation', 'Choose a service and a preferred time. We will confirm your appointment by phone, WhatsApp or email.',
        [['Book a Consultation', '/book-consultation/']], 'Appointments');
    $times = implode('', array_map(fn ($t) => "<option>$t</option>", ['Morning (10am to 1pm)', 'Afternoon (1pm to 4pm)', 'Evening (4pm to 7pm)']));
    $modes = '';
    foreach ([['In office', 'building'], ['Phone call', 'phone'], ['WhatsApp call', 'whatsapp'], ['Online meeting', 'globe']] as $i => [$m, $ic]) {
        $modes .= '<label class="radio"><input type="radio" name="mode" value="' . $m . '"' . ($i === 0 ? ' checked' : '') . '><span>' . icon($ic) . $m . '</span></label>';
    }
    $fs = FORM_STATUS;
    $form = <<<HTML
<form class="form-card" method="post" action="/api/inquiry.php" data-validate>
  <h3>Request an appointment</h3>
  {$fs}
  <input type="hidden" name="form_type" value="booking">
  <input type="hidden" name="page" value="" data-page-field>
  <div class="hp" aria-hidden="true"><label for="website-booking">Website</label><input id="website-booking" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="form-grid">
    <div class="field"><label for="b-name">Full name</label><input id="b-name" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label for="b-phone">Phone / WhatsApp</label><input id="b-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+92 3xx xxxxxxx"></div>
    <div class="field"><label for="b-email">Email</label><input id="b-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
    <div class="field"><label for="b-service">Service</label><select id="b-service" name="service" required data-interest>{$_(service_options())}</select></div>
    <div class="field"><label for="b-date">Preferred date</label><input id="b-date" name="date" type="date" required data-min-today></div>
    <div class="field"><label for="b-time">Preferred time</label><select id="b-time" name="time">{$times}</select></div>
    <fieldset class="field full"><legend>How would you like to meet?</legend><div class="radio-row">{$modes}</div></fieldset>
    <div class="field full"><label for="b-message">Anything we should know? (optional)</label><textarea id="b-message" name="message" rows="3" maxlength="2000"></textarea></div>
  </div>
  <label class="consent"><input type="checkbox" name="consent" value="yes" required> I agree to be contacted about my appointment and accept the <a href="/privacy-policy/">Privacy Policy</a>.</label>
  <button class="btn btn-primary btn-block" type="submit">{$_(icon('calendar'))}Request appointment</button>
</form>
HTML;
    $steps = steps([['We confirm your slot', 'Our team contacts you to confirm the date, time and format.'],
        ['Bring your documents', 'Have your passport, transcripts or business details ready if you have them.'],
        ['Get clear advice', 'Your adviser explains your options, costs, timelines and next steps.']]);
    $expect = <<<HTML
<div class="contact-panel">
  <h3>What to expect</h3>
  {$steps}
  <div class="contact-actions">{$_(btn_wa())}</div>
</div>
HTML;
    return page('Book a Consultation', 'Book a consultation with Nexbridge Solutions for study abroad, visa, business, tax, travel or language services.',
        $hero . '<section class="section"><div class="container contact-grid">' . $form . $expect . '</div></section>', [$bc]);
}

function pg_thanks(): array
{
    $_ = 'nx_id';
    $body = <<<HTML
<section class="section thanks"><div class="container narrow center-text">
  <span class="icon-badge big">{$_(icon('check'))}</span>
  <h1>Thank you, we've received your request</h1>
  <p class="big-text">An adviser will contact you shortly, usually within one working day. Keep an eye on your phone and email.</p>
  <div class="hero-actions center">{$_(btn_primary('Back to home', '/'))}{$_(btn_wa())}</div>
</div></section>
HTML;
    return page('Thank you', 'Your request has been received.', $body);
}

function legal_page(string $path, string $title, array $sections, string $desc): array
{
    $updated = C('LEGAL_UPDATED');
    [$hero, $bc] = page_hero($title, 'Last updated ' . long_date($updated) . '.', [[$title, $path]], 'Legal');
    $html = '';
    foreach ($sections as [$h, $ps]) {
        $html .= '<h2>' . e($h) . '</h2>' . implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $ps));
    }
    return page($title, $desc, $hero . '<section class="section"><div class="container narrow prose">' . $html . '</div></section>', [$bc]);
}

function pg_privacy(): array
{
    $name = C('SITE')['name'];
    return legal_page('/privacy-policy/', 'Privacy Policy', [
        ['Who we are', ["This website is operated by $name. This policy explains how we collect and use personal information when you use our website or contact us."]],
        ['Information we collect', ['When you submit an inquiry or booking form, we collect the details you provide, such as your name, phone number, email address, the service you are interested in, your preferred appointment time and your message.', 'Our web server may also record standard technical information, such as your IP address and browser type, for security and performance.']],
        ['How we use your information', ['We use your information to respond to your inquiry, arrange consultations, provide the services you request and keep records of our communication with you. We do not sell your personal information.']],
        ['Sharing your information', ['We share information only where needed to provide a service you have asked for, for example with a university, embassy, government authority or service provider, and only with your knowledge, or where the law requires it.']],
        ['Third-party services', ['Our website loads fonts from Google Fonts, and may include an embedded Google Map and WhatsApp links. These providers may receive technical information such as your IP address under their own privacy policies. If we enable analytics, we will use it only to understand how our website is used.']],
        ['Keeping your information safe', ['We take reasonable technical and organizational measures to protect your information, and keep it only as long as needed for the purposes above or as required by law.']],
        ['Your choices', ['You can ask us to access, correct or delete the personal information we hold about you by contacting us through our contact page.']],
        ['Changes to this policy', ['We may update this policy from time to time. The date at the top of this page shows when it was last updated.']],
    ], 'How Nexbridge Solutions collects, uses and protects your personal information.');
}

function pg_terms(): array
{
    $name = C('SITE')['name'];
    return legal_page('/terms/', 'Terms & Conditions', [
        ['Using this website', ["By using this website you agree to these terms. The website is provided by $name for general information about our services."]],
        ['Information only', ['Content on this website, including articles and destination information, is general guidance and not legal, immigration, tax or financial advice for your specific situation. Requirements change often, so always confirm current rules with us or the relevant authority before acting.']],
        ['No guaranteed outcomes', ['Admissions, scholarships, visas and registrations are decided by universities, embassies and authorities. We do not guarantee any particular outcome. We commit to handling your case carefully, honestly and professionally.']],
        ['Fees', ['Our service fees are confirmed with you before work begins. University, embassy, government and third-party fees are separate and payable by the client unless agreed otherwise in writing.']],
        ['Your responsibilities', ['You are responsible for providing accurate, complete and genuine information and documents. We cannot be responsible for outcomes affected by incorrect or misleading information.']],
        ['Intellectual property', ['The Nexbridge Solutions name, logo and website content belong to Nexbridge Solutions and may not be copied or used without permission.']],
        ['External links', ['This website may link to external websites. We are not responsible for their content or practices.']],
        ['Changes', ['We may update these terms from time to time. Continued use of the website means you accept the current terms.']],
    ], 'Terms and conditions for using the Nexbridge Solutions website and services.');
}

function pg_gallery(): array
{
    [$hero, $bc] = page_hero('Gallery', 'Moments from our office, events, university visits and client celebrations.',
        [['About Us', '/about/'], ['Gallery', '/gallery/']], 'Life at Nexbridge');
    $G = C('GALLERY');
    $albums = array_values(array_unique(array_column($G, 'album')));
    $chips = '';
    if (count($albums) > 1) {
        $chips = '<div class="chips" role="group" aria-label="Filter photos"><button class="chip active" type="button" data-filter="all">All</button>'
            . implode('', array_map(fn ($a) => '<button class="chip" type="button" data-filter="' . e($a) . '">' . e($a) . '</button>', $albums)) . '</div>';
    }
    $figs = '';
    foreach ($G as $g) {
        $cap = $g['caption'] !== '' ? '<figcaption>' . e($g['caption']) . '</figcaption>' : '';
        $figs .= '<figure class="gallery-item" data-category="' . e($g['album']) . '"><a href="' . $g['image'] . '" target="_blank" rel="noopener">' . "\n"
            . '<img src="' . $g['image'] . '" alt="' . e($g['caption'] ?: $g['album']) . '" loading="lazy" decoding="async"></a>' . $cap . '</figure>';
    }
    $body = '<section class="section"><div class="container">' . $chips . '<div class="gallery-grid" data-posts>' . $figs . "</div>\n"
        . '<p class="empty-filter" hidden data-empty>No photos in this album yet.</p></div></section>';
    return page('Gallery', 'Photos from Nexbridge Solutions: our office, events, university visits and client celebrations.',
        $hero . $body . cta_band('Start your own journey', 'Book a consultation with a Nexbridge adviser.'), [$bc]);
}

function pg_404(): array
{
    $_ = 'nx_id';
    $body = <<<HTML
<section class="section thanks"><div class="container narrow center-text">
  <span class="icon-badge big">{$_(icon('globe'))}</span>
  <h1>We couldn't find that page</h1>
  <p class="big-text">The page may have moved. Try one of these instead.</p>
  <div class="hero-actions center">{$_(btn_primary('Home', '/'))}<a class="btn btn-outline" href="/services/">Consultancy Services</a><a class="btn btn-outline" href="/study-abroad/">Study Abroad</a></div>
</div></section>
HTML;
    return page('Page not found', 'Page not found.', $body);
}
