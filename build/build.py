"""Generates the Nexbridge Solutions static site into the repository root.

Run from anywhere:  python build/build.py
"""
import hashlib
import html
import json
import re
import shutil
import sys
from datetime import date
from pathlib import Path
from urllib.parse import quote_plus

from content import (SITE, HOME, TEAM, ABOUT, WHY_US, PROCESS, FAQ_GROUPS, ARTICLES, INSIGHT_CATEGORIES,
                     CATEGORIES, DESTINATIONS, OTHER_DESTINATIONS, STORIES, STORY_TYPES, PARTNERS,
                     PARTNER_TYPES, GALLERY, ContentError)

ROOT = Path(__file__).resolve().parent.parent
GENERATED_DIRS = ["about", "services", "study-abroad", "destinations", "business-tax-consultancy",
                  "partners", "success-stories", "insights", "faqs", "contact", "book-consultation",
                  "thank-you", "privacy-policy", "terms", "gallery"]
SITEMAP = []


def e(text):
    return html.escape(str(text), quote=True)


def asset_version():
    h = hashlib.md5()
    for p in ["css/style.css", "js/script.js"]:
        h.update((ROOT / p).read_bytes().replace(b"\r\n", b"\n"))
    return h.hexdigest()[:8]


VER = asset_version()
CAT_BY_SLUG = {c["slug"]: c for c in CATEGORIES}
N_SERVICES = sum(len(c["services"]) for c in CATEGORIES)
for _required in ("education-study-abroad", "business-corporate", "tax-legal", "immigration-visa", "travel-tourism", "language-test-preparation"):
    if _required not in CAT_BY_SLUG or not CAT_BY_SLUG[_required]["services"]:
        raise ContentError(f"services.json: the category '{_required}' must exist and contain at least one service")
DEST_BY_SLUG = {d["slug"]: d for d in DESTINATIONS}


def svc_url(cat, svc):
    return f"/services/{cat['slug']}/{svc['slug']}/"


def cat_url(cat):
    return f"/services/{cat['slug']}/"


def dest_url(d):
    return f"/study-abroad/{d['slug']}/"


NUMBER_WORDS = ["Zero", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve",
                "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen", "Twenty"]


def num_word(n):
    return NUMBER_WORDS[n] if n < len(NUMBER_WORDS) else str(n)


def join_words(words):
    words = list(words)
    return words[0] if len(words) == 1 else ", ".join(words[:-1]) + " and " + words[-1] if words else ""


def highlight(t):
    """Escape a heading; text wrapped in *asterisks* gets the brand gradient."""
    return re.sub(r"\*([^*]+)\*", r'<span class="grad">\1</span>', e(t))


def inline(t):
    """Escape text and apply **bold** and [link](https://... or /path)."""
    t = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", e(t))

    def link(m):
        href = html.unescape(m.group(2))
        ext = ' target="_blank" rel="noopener"' if href.startswith("http") else ""
        return f'<a href="{e(href)}"{ext}>{m.group(1)}</a>'
    return re.sub(r"\[([^\]]+)\]\(((?:https?://|/)[^\s)]*)\)", link, t)


def render_body(text):
    """Render the article format: blank lines between blocks, '## ' headings, '- ' bullets, '1. ' numbered lists."""
    out = []
    for block in re.split(r"\n\s*\n", text.replace("\r\n", "\n").strip()):
        lines = [ln.strip() for ln in block.split("\n") if ln.strip()]
        if not lines:
            continue
        if lines[0].startswith("### "):
            out.append(f"<h3>{inline(lines[0][4:])}</h3>")
            lines = lines[1:]
        elif lines[0].startswith("#"):
            out.append(f"<h2>{inline(lines[0].lstrip('#').strip())}</h2>")
            lines = lines[1:]
        if not lines:
            continue
        if all(ln[:2] in ("- ", "* ") for ln in lines):
            out.append("<ul>" + "".join(f"<li>{inline(ln[2:])}</li>" for ln in lines) + "</ul>")
        elif all(re.match(r"^\d+[.)] ", ln) for ln in lines):
            out.append("<ol>" + "".join("<li>" + inline(re.sub(r"^\d+[.)] ", "", ln)) + "</li>" for ln in lines) + "</ol>")
        else:
            out.append("<p>" + "<br>".join(inline(ln) for ln in lines) + "</p>")
    return "".join(out)


def flag(code, w=40, cls="flag"):
    if not code or not (ROOT / "assets/img/flags" / f"{code}.webp").exists():
        return f'<span class="{cls} flag-none" style="width:{w}px;height:{int(w*0.75)}px">{icon("globe")}</span>'
    src = f"/assets/img/flags/{code}{'-lg' if w > 64 else ''}.webp"
    return (f'<img class="{cls}" src="{src}" width="{w}" height="{int(w*0.75)}" '
            f'alt="" loading="lazy" decoding="async">')


# ---------------------------------------------------------------- icons
ICON_PATHS = {
    "cap": "M22 10 12 5 2 10l10 5 10-5zM6 12v5c3 2.5 9 2.5 12 0v-5",
    "briefcase": "M3 7h18v13H3zM8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18",
    "document": "M14 3H6v18h12V7zM14 3v4h4M9 12h6M9 16h6",
    "passport": "M6 3h12v18H6zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM9 17h6",
    "plane": "M2 16l20-8-6 13-3-6-6-1zM13 15l9-7",
    "language": "M4 5h10v7H8l-4 3zM14 9h6v9l-3-2h-5v-3",
    "check": "M5 12l5 5 9-10",
    "arrow": "M5 12h14M13 6l6 6-6 6",
    "phone": "M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2",
    "mail": "M3 6h18v12H3zM3 7l9 6 9-6",
    "pin": "M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12zM12 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4",
    "clock": "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2",
    "user": "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0",
    "users": "M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 7.5M18 14a6 6 0 0 1 4 7",
    "award": "M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12zM8.5 14 7 22l5-3 5 3-1.5-8",
    "eye": "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
    "layers": "M12 3 2 8l10 5 10-5zM2 12.5l10 5 10-5M2 17l10 5 10-5",
    "shield": "M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4",
    "globe": "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18",
    "calendar": "M4 5h16v16H4zM4 10h16M8 3v4M16 3v4",
    "trophy": "M8 4h8v5a4 4 0 0 1-8 0zM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8 21h8M10 17h4",
    "building": "M4 21V4h10v17M14 9h6v12M7 8h3M7 12h3M7 16h3M3 21h18",
    "quote": "M7 7h4v4c0 3-1.5 5-4 6M15 7h4v4c0 3-1.5 5-4 6",
    "chevron": "M6 9l6 6 6-6",
    "menu": "M3 6h18M3 12h18M3 18h18",
    "close": "M6 6l12 12M18 6 6 18",
    "handshake": "M2 11l4-4 4 2 4-2 4 0 4 4M6 7l-4 9 4 3 3-2 3 3 3-2 3 1 4-6M9 13l3 3M12 11l3 3",
    "book": "M4 4h7a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4zM20 4h-6M20 4v15h-6",
    "send": "M3 11l18-8-8 18-2-8z",
}
WHATSAPP_SVG = ('<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3a.5.5 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .1-1.2c0-.1-.2-.2-.5-.3z"/></svg>')


def icon(name, cls="icon"):
    if name == "whatsapp":
        return WHATSAPP_SVG
    return (f'<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            f'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{ICON_PATHS[name]}"/></svg>')


def wa_link(text="Hello Nexbridge Solutions, I would like to ask about your services."):
    if not SITE["whatsapp"]:
        return ""
    return f"https://wa.me/{SITE['whatsapp']}?text={quote_plus(text)}"


# ---------------------------------------------------------------- navigation
def nav_items():
    edu = CAT_BY_SLUG["education-study-abroad"]
    lang = CAT_BY_SLUG["language-test-preparation"]
    biz = CAT_BY_SLUG["business-corporate"]
    tax = CAT_BY_SLUG["tax-legal"]
    services_cols = []
    for c in CATEGORIES:
        links = [(s["name"], svc_url(c, s)) for s in c["services"][:5]]
        links.append((f"All {len(c['services'])} services", cat_url(c)))
        services_cols.append((c["short_name"], cat_url(c), links))
    return [
        {"label": "Home", "href": "/"},
        {"label": "About Us", "href": "/about/", "menu": [("", "", [("About Nexbridge", "/about/"), ("Our Team", "/about/team/"),
                                                                    ("Success Stories", "/success-stories/"), ("Knowledge Center", "/insights/")] +
                                                                   ([("Gallery", "/gallery/")] if GALLERY else []) + [("FAQs", "/faqs/")])]},
        {"label": "Consultancy Services", "href": "/services/", "mega": True, "menu": services_cols},
        {"label": "Study Abroad", "href": "/study-abroad/", "menu": [("", "", [("Study abroad overview", "/study-abroad/")] +
                                                                          [(s["name"], svc_url(edu, s)) for s in edu["services"][:8]] +
                                                                          [("All education services", cat_url(edu))])]},
        {"label": "Business & Tax", "href": "/business-tax-consultancy/", "mega": True, "menu": [
            (biz["short_name"], cat_url(biz), [(s["name"], svc_url(biz, s)) for s in biz["services"]]),
            (tax["short_name"], cat_url(tax), [(s["name"], svc_url(tax, s)) for s in tax["services"]])]},
        {"label": "Language & Test Prep", "href": cat_url(lang), "menu": [("", "", [(s["name"], svc_url(lang, s)) for s in lang["services"]])]},
        {"label": "Our Destinations", "href": "/destinations/", "mega": True, "dest": True},
        {"label": "Our Partners", "href": "/partners/"},
        {"label": "Contact Us", "href": "/contact/"},
    ]


def render_nav(active):
    out = []
    for i, item in enumerate(nav_items()):
        is_active = active == item["href"] or (item["href"] != "/" and active.startswith(item["href"]))
        cls = "nav-item" + (" has-menu" if "menu" in item or item.get("dest") else "") + (" is-mega" if item.get("mega") else "")
        current = ' aria-current="page"' if is_active else ""
        link = f'<a class="nav-link{" active" if is_active else ""}" href="{item["href"]}"{current}>{e(item["label"])}</a>'
        if "menu" not in item and not item.get("dest"):
            out.append(f'<li class="{cls}">{link}</li>')
            continue
        toggle = (f'<button class="sub-toggle" type="button" aria-expanded="false" aria-controls="menu-{i}" '
                  f'aria-label="Show {e(item["label"])} menu">{icon("chevron")}</button>')
        if item.get("dest"):
            cells = "".join(f'<a class="dest-link" href="{dest_url(d)}">{flag(d["flag"], 24)}<span>{e(d["name"])}</span></a>' for d in DESTINATIONS)
            panel = f'<div class="dest-grid">{cells}</div><a class="menu-all" href="/destinations/">All destinations {icon("arrow")}</a>'
        else:
            cols = []
            for title, href, links in item["menu"]:
                head = f'<a class="menu-head" href="{href}">{e(title)}</a>' if title else ""
                lis = "".join(f'<li><a{" class=all-link" if t.startswith("All ") else ""} href="{h}">{e(t)}</a></li>' for t, h in links)
                cols.append(f'<div class="menu-col">{head}<ul>{lis}</ul></div>')
            panel = "".join(cols)
        out.append(f'<li class="{cls}">{link}{toggle}<div class="menu" id="menu-{i}"><div class="menu-inner">{panel}</div></div></li>')
    return "".join(out)


# ---------------------------------------------------------------- page shell
def head(title, desc, path, extra_ld=None, og_image="/assets/img/og.png"):
    full_title = title if title == SITE["name"] else f"{title} | {SITE['name']}"
    url = SITE["url"] + path
    ld = extra_ld or []
    ld_html = "".join(f'<script type="application/ld+json">{json.dumps(x, ensure_ascii=False)}</script>' for x in ld)
    ga = ""
    if SITE["ga4_id"]:
        ga = (f'<script async src="https://www.googletagmanager.com/gtag/js?id={SITE["ga4_id"]}"></script>'
              f'<script>window.dataLayer=window.dataLayer||[];function gtag(){{dataLayer.push(arguments);}}'
              f'gtag("js",new Date());gtag("config","{SITE["ga4_id"]}");</script>')
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{e(full_title)}</title>
<meta name="description" content="{e(desc)}">
<link rel="canonical" href="{url}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{e(SITE['name'])}">
<meta property="og:title" content="{e(full_title)}">
<meta property="og:description" content="{e(desc)}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE['url']}{og_image}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0d2b5e">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/css/style.css?v={VER}">
{ld_html}{ga}
</head>
"""


def header(active):
    contact_bits = []
    if SITE["phone"]:
        contact_bits.append(f'<a href="tel:{e(SITE["phone"].replace(" ", ""))}">{icon("phone")}<span>{e(SITE["phone"])}</span></a>')
    if SITE["email"]:
        contact_bits.append(f'<a href="mailto:{e(SITE["email"])}">{icon("mail")}<span>{e(SITE["email"])}</span></a>')
    contact = f'<div class="brandbar-contact">{"".join(contact_bits)}</div>' if contact_bits else ""
    return f"""<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="brandbar">
  <div class="container brandbar-inner">
    <a class="brand" href="/" aria-label="{e(SITE['name'])} home"><img src="/assets/img/logo.png" alt="{e(SITE['name'])}" width="188" height="56"></a>
    {contact}
    <div class="brandbar-actions">
      <a class="btn btn-ghost btn-sm" href="/book-consultation/?interest=apply">Apply Now</a>
      <a class="btn btn-primary btn-sm" href="/book-consultation/">{icon("calendar")}Book a Consultation</a>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">{icon("menu", "icon icon-open")}{icon("close", "icon icon-close")}</button>
  </div>
</div>
<nav class="navbar" id="site-nav" aria-label="Main">
  <div class="container">
    <ul class="nav-list">{render_nav(active)}</ul>
    <div class="nav-drawer-cta">
      <a class="btn btn-primary" href="/book-consultation/">Book a Consultation</a>
      <a class="btn btn-ghost" href="/book-consultation/?interest=apply">Apply Now</a>
    </div>
  </div>
</nav>
<main id="main">
"""


def footer():
    edu = CAT_BY_SLUG["education-study-abroad"]
    services_links = [
        ("Study Abroad", "/study-abroad/"),
        ("Business Advisory", cat_url(CAT_BY_SLUG["business-corporate"])),
        ("Tax Consultancy", cat_url(CAT_BY_SLUG["tax-legal"])),
        ("Visa Consultancy", cat_url(CAT_BY_SLUG["immigration-visa"])),
        ("Travel & Tourism", cat_url(CAT_BY_SLUG["travel-tourism"])),
        ("Language & Test Preparation", cat_url(CAT_BY_SLUG["language-test-preparation"])),
    ]
    dests = [d for d in DESTINATIONS if d["slug"] in ("turkiye", "uk", "germany", "italy", "spain")]
    dest_links = "".join(f'<li><a href="{dest_url(d)}">{flag(d["flag"], 18)}{e(d["name"])}</a></li>' for d in dests)
    contact_items = []
    if SITE["address"]:
        contact_items.append(f'<li>{icon("pin")}<span>{e(SITE["address"])}</span></li>')
    if SITE["phone"]:
        contact_items.append(f'<li>{icon("phone")}<a href="tel:{e(SITE["phone"].replace(" ", ""))}">{e(SITE["phone"])}</a></li>')
    if SITE["whatsapp"]:
        contact_items.append(f'<li>{icon("whatsapp")}<a href="{wa_link()}" target="_blank" rel="noopener">WhatsApp us</a></li>')
    if SITE["email"]:
        contact_items.append(f'<li>{icon("mail")}<a href="mailto:{e(SITE["email"])}">{e(SITE["email"])}</a></li>')
    if not contact_items:
        contact_items.append(f'<li>{icon("send")}<a href="/contact/">Send us a message</a></li>')
        contact_items.append(f'<li>{icon("calendar")}<a href="/book-consultation/">Book a consultation</a></li>')
    socials = "".join(f'<a href="{e(u)}" target="_blank" rel="noopener">{e(n.capitalize() if n != "tiktok" else "TikTok")}</a>'
                      for n, u in SITE["socials"].items() if u)
    socials_html = f'<div class="footer-social">{socials}</div>' if socials else ""
    wa_float = ""
    if SITE["whatsapp"]:
        wa_float = f'<a class="wa-float" href="{wa_link()}" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">{icon("whatsapp")}</a>'
    year = date.today().year
    return f"""</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <img class="footer-logo" src="/assets/img/logo-white.png" alt="{e(SITE['name'])}" width="188" height="56" loading="lazy">
        <p>Clear pathways between ambition and opportunity: professional guidance for international education, visas, business, tax and travel.</p>
        <p class="footer-tagline">{e(SITE['tagline'])}</p>
      </div>
      <div>
        <h2 class="footer-head">Quick Links</h2>
        <ul><li><a href="/">Home</a></li><li><a href="/about/">About Us</a></li><li><a href="/about/team/">Our Team</a></li>
        <li><a href="/success-stories/">Success Stories</a></li><li><a href="/insights/">Knowledge Center</a></li>{'<li><a href="/gallery/">Gallery</a></li>' if GALLERY else ""}<li><a href="/faqs/">FAQs</a></li><li><a href="/contact/">Contact Us</a></li></ul>
      </div>
      <div>
        <h2 class="footer-head">Consultancy Services</h2>
        <ul>{"".join(f'<li><a href="{h}">{e(t)}</a></li>' for t, h in services_links)}</ul>
      </div>
      <div>
        <h2 class="footer-head">Study Destinations</h2>
        <ul class="footer-dests">{dest_links}<li><a href="/destinations/">Other destinations</a></li></ul>
      </div>
    </div>
    <div class="footer-bottom">
      <ul class="footer-contact">{"".join(contact_items)}</ul>
      {socials_html}
      <div class="footer-legal">
        <span>&copy; {year} {e(SITE['name'])}. All rights reserved.</span>
        <a href="/privacy-policy/">Privacy Policy</a>
        <a href="/terms/">Terms &amp; Conditions</a>
      </div>
    </div>
  </div>
</footer>
{wa_float}
<script src="/js/script.js?v={VER}" defer></script>
</body>
</html>
"""


def write_page(path, title, desc, body, ld=None, sitemap_priority="0.6"):
    content = head(title, desc, path, ld) + header(path) + body + footer()
    target = ROOT / (path.strip("/") + "/index.html" if path.endswith("/") and path != "/" else "index.html" if path == "/" else path.lstrip("/"))
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")
    if path not in ("/404.html", "/thank-you/"):
        SITEMAP.append((path, sitemap_priority))


# ---------------------------------------------------------------- shared blocks
def crumbs(trail):
    items = [("Home", "/")] + trail
    parts = []
    for i, (name, href) in enumerate(items):
        if i == len(items) - 1:
            parts.append(f'<li aria-current="page">{e(name)}</li>')
        else:
            parts.append(f'<li><a href="{href}">{e(name)}</a></li>')
    ld = {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "name": n, "item": SITE["url"] + h} for i, (n, h) in enumerate(items)]}
    return f'<nav class="crumbs" aria-label="Breadcrumb"><ol>{"".join(parts)}</ol></nav>', ld


def page_hero(title, lead, trail, eyebrow="", actions="", aside="", cls=""):
    crumb_html, ld = crumbs(trail)
    eb = f'<span class="eyebrow">{e(eyebrow)}</span>' if eyebrow else ""
    acts = f'<div class="hero-actions">{actions}</div>' if actions else ""
    aside_html = f'<div class="page-hero-aside">{aside}</div>' if aside else ""
    return f"""<section class="page-hero {cls}">
  <div class="container page-hero-inner{' has-aside' if aside else ''}">
    <div class="page-hero-copy">{crumb_html}{eb}<h1>{e(title)}</h1><p class="lead">{e(lead)}</p>{acts}</div>
    {aside_html}
  </div>
</section>
""", ld


def section_head(eyebrow, title, lead="", center=True):
    lead_html = f'<p>{e(lead)}</p>' if lead else ""
    return f'<div class="section-head{" center" if center else ""}"><span class="eyebrow">{e(eyebrow)}</span><h2>{e(title)}</h2>{lead_html}</div>'


def btn_primary(text, href, ico=""):
    return f'<a class="btn btn-primary" href="{href}">{icon(ico) if ico else ""}{e(text)}</a>'


def btn_light(text, href, ico=""):
    return f'<a class="btn btn-light" href="{href}">{icon(ico) if ico else ""}{e(text)}</a>'


def btn_wa(text="Chat on WhatsApp", msg=None):
    link = wa_link(msg) if msg else wa_link()
    if not link:
        return ""
    return f'<a class="btn btn-wa" href="{link}" target="_blank" rel="noopener">{icon("whatsapp")}{e(text)}</a>'


def service_options(selected=""):
    groups = []
    for c in CATEGORIES:
        opts = "".join(f'<option value="{e(s["name"])}"{" selected" if s["name"] == selected else ""}>{e(s["name"])}</option>' for s in c["services"])
        groups.append(f'<optgroup label="{e(c["short_name"])}">{opts}</optgroup>')
    dest_opts = "".join(f'<option value="Study in {e(d["name"])}"{" selected" if "Study in " + d["name"] == selected else ""}>Study in {e(d["name"])}</option>' for d in DESTINATIONS)
    groups.append(f'<optgroup label="Study destinations">{dest_opts}<option value="Other study destination">Other study destination</option></optgroup>')
    return '<option value="">Select a service</option><option value="Not sure yet">Not sure yet, I need advice</option>' + "".join(groups)


FORM_STATUS = ('<div class="form-status" role="status" hidden data-form-error>'
               'Please check the highlighted fields. Name, phone and a valid email are needed so we can reply.</div>')


def inquiry_form(selected="", form_type="inquiry", heading="Send us an inquiry", submit="Send inquiry", message_label="How can we help?"):
    return f"""<form class="form-card" method="post" action="/api/inquiry.php" data-validate>
  <h3>{e(heading)}</h3>
  {FORM_STATUS}
  <input type="hidden" name="form_type" value="{e(form_type)}">
  <input type="hidden" name="page" value="" data-page-field>
  <div class="hp" aria-hidden="true"><label for="website-{form_type}">Website</label><input id="website-{form_type}" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="form-grid">
    <div class="field"><label for="{form_type}-name">Full name</label><input id="{form_type}-name" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label for="{form_type}-phone">Phone / WhatsApp</label><input id="{form_type}-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+92 3xx xxxxxxx"></div>
    <div class="field"><label for="{form_type}-email">Email</label><input id="{form_type}-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
    <div class="field"><label for="{form_type}-service">Service</label><select id="{form_type}-service" name="service" data-interest>{service_options(selected)}</select></div>
    <div class="field full"><label for="{form_type}-message">{e(message_label)}</label><textarea id="{form_type}-message" name="message" rows="4" maxlength="2000"></textarea></div>
  </div>
  <label class="consent"><input type="checkbox" name="consent" value="yes" required> I agree to be contacted about my inquiry and accept the <a href="/privacy-policy/">Privacy Policy</a>.</label>
  <button class="btn btn-primary btn-block" type="submit">{icon("send")}{e(submit)}</button>
</form>"""


def contact_panel():
    rows = []
    if SITE["address"]:
        rows.append(f'<li>{icon("pin")}<div><strong>Office</strong><span>{e(SITE["address"])}</span></div></li>')
    if SITE["phone"]:
        rows.append(f'<li>{icon("phone")}<div><strong>Phone</strong><a href="tel:{e(SITE["phone"].replace(" ", ""))}">{e(SITE["phone"])}</a></div></li>')
    if SITE["email"]:
        rows.append(f'<li>{icon("mail")}<div><strong>Email</strong><a href="mailto:{e(SITE["email"])}">{e(SITE["email"])}</a></div></li>')
    if SITE["hours"]:
        rows.append(f'<li>{icon("clock")}<div><strong>Office hours</strong><span>{e(SITE["hours"])}</span></div></li>')
    rows.append(f'<li>{icon("calendar")}<div><strong>Appointments</strong><span>In office, by phone, WhatsApp call or online.</span></div></li>')
    map_html = ""
    if SITE["map_query"]:
        map_html = (f'<iframe class="map" title="Map of our office" loading="lazy" referrerpolicy="no-referrer-when-downgrade" '
                    f'src="https://www.google.com/maps?q={quote_plus(SITE["map_query"])}&output=embed"></iframe>')
    wa = btn_wa()
    return f"""<div class="contact-panel">
  <h3>Talk to our team</h3>
  <p>Tell us about your plans and an adviser will get back to you. Prefer a set time? Book an appointment.</p>
  <ul class="contact-list">{"".join(rows)}</ul>
  <div class="contact-actions">{btn_primary("Book an appointment", "/book-consultation/", "calendar")}{wa}</div>
  {map_html}
</div>"""


def consult_section(selected="", title="Ready to take the next step?", lead="Send us your question or request a consultation. An adviser will reply with clear next steps."):
    return f"""<section class="section consult" id="inquiry">
  <div class="container consult-grid">
    <div class="consult-copy">
      <span class="eyebrow on-dark">Consultation &amp; inquiry</span>
      <h2>{e(title)}</h2>
      <p>{e(lead)}</p>
      <ul class="consult-points">
        <li>{icon("check")}Free initial guidance on your options</li>
        <li>{icon("check")}Honest advice on eligibility and timelines</li>
        <li>{icon("check")}One adviser who follows your case</li>
      </ul>
      <div class="hero-actions">{btn_light("Book an appointment", "/book-consultation/", "calendar")}{btn_wa()}</div>
    </div>
    {inquiry_form(selected)}
  </div>
</section>
"""


def faq_list(faqs):
    items = "".join(f'<details class="faq"><summary>{e(q)}{icon("chevron")}</summary><div class="faq-body"><p>{e(a)}</p></div></details>' for q, a in faqs)
    return f'<div class="faq-list">{items}</div>'


def faq_ld(faqs):
    return {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
        {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in faqs]}


def steps(items):
    lis = "".join(f'<li class="step"><span class="step-num">{i+1}</span><div><h3>{e(t)}</h3><p>{e(d)}</p></div></li>' for i, (t, d) in enumerate(items))
    return f'<ol class="steps">{lis}</ol>'


def checklist(items, cls="checklist"):
    return f'<ul class="{cls}">' + "".join(f'<li>{icon("check")}<span>{e(x)}</span></li>' for x in items) + "</ul>"


def category_card(c):
    lis = "".join(f'<li><a href="{svc_url(c, s)}">{e(s["name"])}</a></li>' for s in c["services"][:4])
    return f"""<article class="cat-card">
  <span class="icon-badge">{icon(c["icon"])}</span>
  <h3><a href="{cat_url(c)}">{e(c["short_name"])}</a></h3>
  <p>{e(c["summary"])}</p>
  <ul class="cat-links">{lis}</ul>
  <a class="text-link" href="{cat_url(c)}">All {len(c["services"])} services {icon("arrow")}</a>
</article>"""


def service_card(c, s):
    return f"""<a class="svc-card" href="{svc_url(c, s)}">
  <span class="svc-cat">{e(c["short_name"])}</span>
  <h3>{e(s["name"])}</h3>
  <p>{e(s["short"])}</p>
  <span class="text-link">Learn more {icon("arrow")}</span>
</a>"""


def dest_card(d):
    pts = "".join(f"<li>{e(p)}</li>" for p in d["card_points"])
    return f"""<a class="dest-card" href="{dest_url(d)}">
  <div class="dest-flag">{flag(d["flag"], 64, "flag flag-lg")}</div>
  <div class="dest-body">
    <h3>Study in {e(d["name"])}</h3>
    <ul>{pts}</ul>
    <span class="text-link">Explore {icon("arrow")}</span>
  </div>
</a>"""


def team_card(m):
    return f"""<a class="team-card" href="/about/team/{m['slug']}/">
  <div class="team-photo"><img src="{m['photo'] or '/assets/img/logo.png'}" alt="{e(m['name'])}" width="480" height="600" loading="lazy" decoding="async"></div>
  <div class="team-info"><h3>{e(m["name"])}</h3><span class="role">{e(m["role"])}</span><p>{e(m["summary"])}</p></div>
</a>"""


def article_card(a):
    d = date.fromisoformat(a["date"])
    cover = f'<img class="post-img" src="{a["image"]}" alt="" width="480" height="270" loading="lazy" decoding="async">' if a["image"] else ""
    return f"""<a class="post-card{' has-img' if cover else ''}" href="/insights/{a['slug']}/" data-category="{e(a['category'])}">
  {cover}<span class="post-cat">{e(a["category"])}</span>
  <h3>{e(a["title"])}</h3>
  <p>{e(a["excerpt"])}</p>
  <time datetime="{a['date']}">{d.strftime('%d %B %Y').lstrip('0')}</time>
</a>"""


def story_card(s):
    photo = (f'<img src="{s["photo"]}" alt="" width="56" height="56" loading="lazy" decoding="async">' if s["photo"]
             else f'<span class="story-initial" aria-hidden="true">{e(s["name"][:1].upper())}</span>')
    head = f'<h3>{e(s["headline"])}</h3>' if s["headline"] else ""
    detail = f'<span>{e(s["detail"])}</span>' if s["detail"] else ""
    return f"""<figure class="story-card">{icon("quote", "icon story-quote")}{head}
  <blockquote><p>{e(s["text"])}</p></blockquote>
  <figcaption>{photo}<div><strong>{e(s["name"])}</strong>{detail}</div></figcaption>
</figure>"""


def partner_logo(p):
    inner = (f'<img src="{p["logo"]}" alt="{e(p["name"])}" loading="lazy" decoding="async">' if p["logo"]
             else f'<span class="logo-name">{e(p["name"])}</span>')
    title = e(p["name"] + (f", {p['country']}" if p["country"] else ""))
    if p["url"]:
        return f'<a class="partner-logo" href="{e(p["url"])}" target="_blank" rel="noopener" title="{title}">{inner}</a>'
    return f'<div class="partner-logo" title="{title}">{inner}</div>'


def why_grid():
    return '<div class="why-grid">' + "".join(
        f'<div class="why-item"><span class="icon-badge soft">{icon(ic)}</span><h3>{e(t)}</h3><p>{e(d)}</p></div>' for t, d, ic in WHY_US) + "</div>"


def process_band():
    lis = "".join(f'<li><span class="pstep-num">{i+1}</span><h3>{e(t)}</h3><p>{e(d)}</p></li>' for i, (t, d) in enumerate(PROCESS))
    return f'<ol class="process">{lis}</ol>'


def cta_band(title, text, primary=("Book a Consultation", "/book-consultation/"), secondary=None):
    sec = btn_light(*secondary) if secondary else btn_wa()
    return f"""<section class="cta-band"><div class="container cta-inner">
  <div><h2>{e(title)}</h2><p>{e(text)}</p></div>
  <div class="hero-actions">{btn_light(primary[0], primary[1], "calendar")}{sec}</div>
</div></section>"""


# ---------------------------------------------------------------- pages
def build_home():
    hero = f"""<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      {f'<span class="eyebrow on-dark">{e(HOME["hero_eyebrow"])}</span>' if HOME["hero_eyebrow"] else ""}
      <h1>{highlight(HOME["hero_title"])}</h1>
      {f'<p class="lead">{e(HOME["hero_lead"])}</p>' if HOME["hero_lead"] else ""}
      <div class="hero-actions">{btn_primary("Explore Our Services", "/services/", "layers")}{btn_light("Book a Consultation", "/book-consultation/", "calendar")}</div>
      <ul class="hero-proof">
        <li><strong>{len(CATEGORIES)}</strong><span>consultancy areas</span></li>
        <li><strong>{N_SERVICES}</strong><span>specialist services</span></li>
        <li><strong>{len(DESTINATIONS)}</strong><span>study destinations</span></li>
      </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="hero-orb"></div>
      {f'<img class="hero-person" src="{HOME["hero_image"]}" alt="" width="560" height="760" fetchpriority="high">' if HOME["hero_image"] else ""}
      <div class="float-card fc-1">{"".join(flag(d["flag"], 24) for d in DESTINATIONS[:4])}<span>{len(DESTINATIONS)} study destinations</span></div>
      <div class="float-card fc-2">{icon("shield")}<span>Transparent, step-by-step guidance</span></div>
    </div>
  </div>
</section>
"""
    edu = CAT_BY_SLUG["education-study-abroad"]
    about = f"""<section class="section">
  <div class="container split">
    <div class="split-media">
      <img src="{HOME["about_image"]}" alt="A Nexbridge Solutions adviser in the office" width="640" height="760" loading="lazy" decoding="async">
      <div class="media-badge">{icon("users")}<span>Advisers who follow your case from first call to final outcome</span></div>
    </div>
    <div class="split-copy">
      {section_head("About Nexbridge", "Connecting ambition with opportunity", center=False)}
      <p>{e(ABOUT["intro"][0])}</p>
      <div class="vmc">
        <div><h3>{icon("eye")}Our vision</h3><p>{e(ABOUT["vision"])}</p></div>
        <div><h3>{icon("globe")}Our mission</h3><p>{e(ABOUT["mission"])}</p></div>
        <div><h3>{icon("shield")}Our commitment</h3><p>{e(ABOUT["commitment"])}</p></div>
      </div>
      <h3 class="mini-head">Why choose Nexbridge?</h3>
      {checklist(HOME["about_points"]) if HOME["about_points"] else ""}
      <a class="btn btn-outline" href="/about/">More about us {icon("arrow")}</a>
    </div>
  </div>
</section>
"""
    services = f"""<section class="section tint">
  <div class="container">
    {section_head("Our consultancy services", f"Expert guidance across {num_word(len(CATEGORIES)).lower()} areas", "From your first university application to registering your company, our specialists handle the details so you can focus on your goals.")}
    <div class="cat-grid">{"".join(category_card(c) for c in CATEGORIES)}</div>
    <div class="center-row">{btn_primary("View all services", "/services/")}</div>
  </div>
</section>
"""
    dests = f"""<section class="section">
  <div class="container">
    {section_head("Study abroad destinations", "Where would you like to study?", "Explore universities, costs, scholarships and visa requirements country by country.")}
    <div class="dest-grid-cards">{"".join(dest_card(d) for d in DESTINATIONS[:8])}</div>
    <div class="center-row">{btn_primary(f"See all {len(DESTINATIONS)} destinations", "/destinations/")}</div>
  </div>
</section>
"""
    why = f"""<section class="section tint">
  <div class="container">
    {section_head("Why choose us", "Guidance you can trust", "We built Nexbridge around the things clients told us mattered most.")}
    {why_grid()}
  </div>
</section>
"""
    process = f"""<section class="section navy-section">
  <div class="container">
    {section_head("Our process", "Five clear steps, every time", "Whatever service you choose, you always know what happens next.")}
    {process_band()}
  </div>
</section>
"""
    featured = sorted(STORIES, key=lambda s: not s["featured"])[:3]
    stories = f"""<section class="section">
  <div class="container">
    {section_head("Success stories", "Real results, shared with permission", "Visa approvals, admissions and client stories, published with our clients' consent.")}
    <div class="story-grid">{"".join(story_card(s) for s in featured)}</div>
    <div class="center-row">{btn_primary("Read more success stories", "/success-stories/")}</div>
  </div>
</section>
""" if STORIES else f"""<section class="section">
  <div class="container">
    {section_head("Success stories", "Real results, shared with permission", "We publish visa approvals, admissions and client stories only with our clients' consent. Verified stories are being added here.")}
    <div class="story-types">
      <a href="/success-stories/#visa">{icon("passport")}<h3>Visa success stories</h3><p>Study, visit and business visa approvals.</p></a>
      <a href="/success-stories/#admissions">{icon("cap")}<h3>Admission success stories</h3><p>Offers from universities around the world.</p></a>
      <a href="/success-stories/#testimonials">{icon("quote")}<h3>Client testimonials</h3><p>In our clients' own words.</p></a>
      <a href="/success-stories/#achievements">{icon("trophy")}<h3>Achievements &amp; case studies</h3><p>Scholarships, registrations and milestones.</p></a>
    </div>
  </div>
</section>
"""
    partners = f"""<section class="section tint">
  <div class="container">
    {section_head("Our partners", "Building partnerships beyond borders", "Led by our Director of Partner Relations, we work with universities, institutions and organizations to create better opportunities for our clients.")}
    <div class="logo-grid wide">{"".join(partner_logo(p) for p in PARTNERS[:12])}</div>
    <div class="center-row">{btn_primary("See all partners", "/partners/")}</div>
  </div>
</section>
""" if PARTNERS else f"""<section class="section tint">
  <div class="container">
    {section_head("Our partners", "Building partnerships beyond borders", "Led by our Director of Partner Relations, we work with universities, institutions and organizations to create better opportunities for our clients. Partner logos appear here once authorized.")}
    <div class="partner-types">
      <div>{icon("cap")}<h3>Universities</h3></div>
      <div>{icon("book")}<h3>Educational institutions</h3></div>
      <div>{icon("building")}<h3>Corporate partners</h3></div>
      <div>{icon("globe")}<h3>International organizations</h3></div>
    </div>
    <div class="center-row">{btn_primary("Partner with Nexbridge", "/partners/")}</div>
  </div>
</section>
"""
    insights = f"""<section class="section">
  <div class="container">
    {section_head("Latest updates & insights", "Guides, visa updates and advice", "Practical articles on study abroad, scholarships, visas, business and tax.")}
    <div class="post-grid">{"".join(article_card(a) for a in sorted(ARTICLES, key=lambda x: x["date"], reverse=True)[:3])}</div>
    <div class="center-row"><a class="btn btn-outline" href="/insights/">Visit the Knowledge Center {icon("arrow")}</a></div>
  </div>
</section>
"""
    contact = f"""<section class="section tint" id="contact">
  <div class="container">
    {section_head("Contact & consultation", "Let's plan your next step together", "Send an inquiry, book an appointment or message us. We reply with clear next steps.")}
    <div class="contact-grid">{inquiry_form()}{contact_panel()}</div>
  </div>
</section>
"""
    ld = [{"@context": "https://schema.org", "@type": "ProfessionalService", "name": SITE["name"], "url": SITE["url"] + "/",
           "logo": SITE["url"] + "/assets/img/logo.png", "image": SITE["url"] + "/assets/img/og.png", "description": SITE["description"],
           **({"telephone": SITE["phone"]} if SITE["phone"] else {}), **({"email": SITE["email"]} if SITE["email"] else {}),
           **({"address": SITE["address"]} if SITE["address"] else {}),
           "sameAs": [u for u in SITE["socials"].values() if u]}]
    write_page("/", SITE["name"], SITE["description"],
               hero + about + services + dests + why + process + stories + partners + insights + contact, ld, "1.0")


def build_about():
    hero, bc = page_hero("About Nexbridge Solutions", "Professional, transparent and personalized guidance for education, immigration, business and travel.",
                         [("About Us", "/about/")], "Who we are",
                         btn_primary("Meet our team", "/about/team/", "users") + btn_light("Book a Consultation", "/book-consultation/", "calendar"))
    intro = f"""<section class="section">
  <div class="container split">
    <div class="split-copy">
      {section_head("Company introduction", "Clear pathways between ambition and opportunity", center=False)}
      {"".join(f"<p>{e(p)}</p>" for p in ABOUT["intro"])}
      <p class="pull">{e(SITE["tagline"])}</p>
    </div>
    <div class="split-media"><img src="{HOME["about_image"]}" alt="A Nexbridge Solutions adviser in the office" width="640" height="760" loading="lazy" decoding="async"></div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    <div class="vmc-cards">
      <div class="card">{icon("eye", "icon icon-lg")}<h2>Our vision</h2><p>{e(ABOUT["vision"])}</p></div>
      <div class="card">{icon("globe", "icon icon-lg")}<h2>Our mission</h2><p>{e(ABOUT["mission"])}</p></div>
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    {section_head("Our core values", "What guides our work")}
    <div class="values">{"".join(f'<div class="value"><h3>{e(t)}</h3><p>{e(d)}</p></div>' for t, d in ABOUT["values"])}</div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    {section_head("Our expertise", f"{num_word(len(CATEGORIES))} areas of specialist consultancy")}
    <div class="cat-grid">{"".join(category_card(c) for c in CATEGORIES)}</div>
  </div>
</section>
<section class="section">
  <div class="container">
    {section_head("Our team & leadership", "The people behind Nexbridge")}
    <div class="team-grid">{"".join(team_card(m) for m in TEAM)}</div>
  </div>
</section>
<section class="section tint">
  <div class="container">
    {section_head("Why Nexbridge Solutions?", "Guidance you can trust")}
    {why_grid()}
  </div>
</section>
<section class="section">
  <div class="container narrow center-text">
    {section_head("Our commitment to clients", "Every journey is unique")}
    <p class="big-text">{e(ABOUT["commitment"])} We promise honest advice, clear communication about what each service involves, careful handling of your documents and personal information, and support from your first consultation to the final outcome.</p>
  </div>
</section>
"""
    write_page("/about/", "About Us", "Learn about Nexbridge Solutions: our vision, mission, core values, expertise and leadership team.",
               hero + intro + cta_band("Start your journey with Nexbridge", "Book a consultation and get clear, honest advice on your plans."), [bc], "0.8")


def build_team():
    hero, bc = page_hero("Our Team", "Meet the leadership team building pathways between ambition and opportunity.",
                         [("About Us", "/about/"), ("Our Team", "/about/team/")], "Leadership")
    body = f"""<section class="section"><div class="container"><div class="team-grid">{"".join(team_card(m) for m in TEAM)}</div></div></section>"""
    write_page("/about/team/", "Our Team", "Meet the Nexbridge Solutions leadership team.", hero + body + cta_band("Talk to our team", "Book a consultation with a Nexbridge adviser."), [bc], "0.7")
    for m in TEAM:
        crumb_html, bc = crumbs([("About Us", "/about/"), ("Our Team", "/about/team/"), (m["name"], f"/about/team/{m['slug']}/")])
        others = "".join(team_card(o) for o in TEAM if o is not m)
        body = f"""<section class="profile-hero">
  <div class="container profile-grid">
    <div class="profile-photo"><img src="{m['photo'] or '/assets/img/logo.png'}" alt="{e(m['name'])}" width="480" height="600"></div>
    <div class="profile-copy">{crumb_html}<span class="eyebrow on-dark">Nexbridge leadership</span><h1>{e(m["name"])}</h1><span class="role-pill">{e(m["role"])}</span><p class="lead">{e(m["summary"])}</p></div>
  </div>
</section>
<section class="section"><div class="container narrow prose">{"".join(f"<p>{e(p)}</p>" for p in m["bio"])}</div></section>
<section class="section tint"><div class="container">{section_head("Our team", "Meet the rest of the team")}<div class="team-grid three">{others}</div></div></section>
"""
        ld = [bc, {"@context": "https://schema.org", "@type": "Person", "name": m["name"], "jobTitle": m["role"],
                   "worksFor": {"@type": "Organization", "name": SITE["name"]}, **({"image": SITE['url'] + m['photo']} if m['photo'] else {})}]
        write_page(f"/about/team/{m['slug']}/", f"{m['name']}, {m['role']}", f"{m['name']} is {m['role']} at Nexbridge Solutions. {m['summary']}", body, ld, "0.5")


def build_services_hub():
    hero, bc = page_hero("Consultancy Services", f"{N_SERVICES} specialist services across education, immigration, business, tax, travel and language preparation.",
                         [("Consultancy Services", "/services/")], "What we do",
                         btn_primary("Book a Consultation", "/book-consultation/", "calendar") + btn_light("Ask a question", "#inquiry", "send"))
    jump = "".join(f'<a href="#{c["slug"]}">{icon(c["icon"])}{e(c["short_name"])}</a>' for c in CATEGORIES)
    blocks = []
    for c in CATEGORIES:
        links = "".join(f'<li><a href="{svc_url(c, s)}"><strong>{e(s["name"])}</strong><span>{e(s["short"])}</span></a></li>' for s in c["services"])
        blocks.append(f"""<section class="svc-block" id="{c['slug']}">
  <div class="svc-block-head"><span class="icon-badge">{icon(c["icon"])}</span><div><h2><a href="{cat_url(c)}">{e(c["name"])}</a></h2><p>{e(c["summary"])}</p></div></div>
  <ul class="svc-list">{links}</ul>
</section>""")
    body = f"""<section class="section"><div class="container"><nav class="jump-nav" aria-label="Service categories">{jump}</nav>{"".join(blocks)}</div></section>"""
    write_page("/services/", "Consultancy Services", "All Nexbridge Solutions consultancy services: study abroad, immigration and visa, business, tax, travel and language preparation.",
               hero + body + consult_section(), [bc], "0.9")


def build_category(c):
    hero, bc = page_hero(c["name"], c["summary"], [("Consultancy Services", "/services/"), (c["short_name"], cat_url(c))], "Consultancy services",
                         btn_primary("Book a Consultation", "/book-consultation/", "calendar") + btn_light("Send an inquiry", "#inquiry", "send"),
                         aside=f'<span class="hero-icon">{icon(c["icon"])}</span>')
    body = f"""<section class="section">
  <div class="container narrow center-text"><p class="big-text">{e(c["intro"])}</p></div>
  <div class="container"><div class="svc-grid">{"".join(service_card(c, s) for s in c["services"])}</div></div>
</section>
<section class="section tint"><div class="container">{section_head("Why Nexbridge", "Benefits of choosing us")}{benefit_grid(c)}</div></section>
<section class="section"><div class="container narrow">{section_head("How it works", "Our step-by-step process")}{steps(c["process"])}</div></section>
"""
    write_page(cat_url(c), c["name"], f"{c['summary']} Explore {len(c['services'])} {c['short_name'].lower()} services from Nexbridge Solutions.",
               hero + body + consult_section(), [bc], "0.8")


def benefit_grid(c):
    return '<div class="benefits">' + "".join(f'<div class="benefit">{icon("check")}<div><h3>{e(t)}</h3><p>{e(d)}</p></div></div>' for t, d in c["benefits"]) + "</div>"


def related_for(c, s):
    out = []
    for ref in s.get("related", []):
        cs, ss = ref.split("/")
        if cs == "study-abroad":
            d = DEST_BY_SLUG[ss]
            out.append(("dest", d))
        else:
            cc = CAT_BY_SLUG[cs]
            out.append(("svc", (cc, next(x for x in cc["services"] if x["slug"] == ss))))
    for x in c["services"]:
        if len(out) >= 3:
            break
        if x is not s and not any(k == "svc" and v[1] is x for k, v in out):
            out.append(("svc", (c, x)))
    cards = []
    for kind, v in out[:3]:
        cards.append(dest_card(v) if kind == "dest" else service_card(*v))
    return "".join(cards)


def build_service(c, s):
    trail = [("Consultancy Services", "/services/"), (c["short_name"], cat_url(c)), (s["name"], svc_url(c, s))]
    wa = btn_wa("Ask on WhatsApp", f"Hello Nexbridge Solutions, I would like to ask about {s['name']}.")
    hero, bc = page_hero(s["name"], s["short"], trail, c["short_name"],
                         btn_primary("Book a Consultation", f"/book-consultation/?interest={quote_plus(s['name'])}", "calendar") + (wa or btn_light("Send an inquiry", "#inquiry", "send")),
                         aside=f'<span class="hero-icon">{icon(c["icon"])}</span>')
    toc = [("overview", "Overview"), ("offer", "What we offer"), ("benefits", "Benefits")]
    sec = []
    sec.append(f'<section id="overview"><h2>Introduction to the service</h2><p>{e(s["intro"])}</p></section>')
    sec.append(f'<section id="offer"><h2>What we offer</h2>{checklist(s["offers"], "checklist two-col")}</section>')
    sec.append(f'<section id="benefits"><h2>Benefits of choosing Nexbridge</h2>{benefit_grid(c)}</section>')
    if s.get("requirements"):
        toc.append(("requirements", "Requirements"))
        sec.append(f'<section id="requirements"><h2>Eligibility &amp; requirements</h2>{checklist(s["requirements"])}</section>')
    toc.append(("process", "Process"))
    sec.append(f'<section id="process"><h2>Step-by-step process</h2>{steps(c["process"])}</section>')
    if s.get("documents"):
        toc.append(("documents", "Documents"))
        sec.append(f'<section id="documents"><h2>Required documents</h2>{checklist(s["documents"], "checklist docs two-col")}'
                   f'<p class="note">Requirements can vary by case. We confirm the exact list during your consultation.</p></section>')
    toc.append(("faqs", "FAQs"))
    sec.append(f'<section id="faqs"><h2>Frequently asked questions</h2>{faq_list(s["faqs"])}</section>')
    toc_html = "".join(f'<li><a href="#{a}">{e(t)}</a></li>' for a, t in toc)
    siblings = "".join(f'<li><a href="{svc_url(c, x)}"{" aria-current=page" if x is s else ""}>{e(x["name"])}</a></li>' for x in c["services"])
    aside = f"""<aside class="svc-aside">
  <div class="aside-card accent">
    <h3>Talk to an adviser</h3>
    <p>Get clear advice on {e(s["name"])} for your situation.</p>
    {btn_light("Book a Consultation", f"/book-consultation/?interest={quote_plus(s['name'])}", "calendar")}
    {wa}
    <a class="aside-link" href="#inquiry">Or send an inquiry {icon("arrow")}</a>
  </div>
  <nav class="aside-card" aria-label="On this page"><h3>On this page</h3><ul class="toc">{toc_html}</ul></nav>
  <nav class="aside-card" aria-label="{e(c["short_name"])} services"><h3>{e(c["short_name"])}</h3><ul class="sibling-list">{siblings}</ul></nav>
</aside>"""
    wa_band = ""
    if SITE["whatsapp"]:
        wa_band = f"""<section class="wa-band"><div class="container wa-inner">{icon("whatsapp")}<p><strong>Have a quick question about {e(s["name"])}?</strong> Message our team on WhatsApp.</p>{btn_wa("Chat on WhatsApp", f"Hello Nexbridge Solutions, I would like to ask about {s['name']}.")}</div></section>"""
    body = f"""<div class="container with-aside">
  <article class="svc-main">{"".join(sec)}</article>
  {aside}
</div>
<section class="section tint"><div class="container">{section_head("Related services", "You may also need")}<div class="svc-grid">{related_for(c, s)}</div></div></section>
{consult_section(s["name"], f"Get started with {s['name']}", "Tell us about your situation and an adviser will reply with clear next steps.")}
{wa_band}"""
    ld = [bc, faq_ld(s["faqs"]), {"@context": "https://schema.org", "@type": "Service", "name": s["name"], "description": s["intro"],
                                   "serviceType": c["name"], "provider": {"@type": "Organization", "name": SITE["name"], "url": SITE["url"]}}]
    write_page(svc_url(c, s), s["name"], f"{s['short']} Expert {c['short_name'].lower()} consultancy from Nexbridge Solutions.", hero + body, ld, "0.7")


def build_study_abroad():
    edu = CAT_BY_SLUG["education-study-abroad"]
    hero, bc = page_hero("Study Abroad", "Choose the right country, university and program, then apply, secure your visa and settle in with one team beside you.",
                         [("Study Abroad", "/study-abroad/")], "Education & study abroad",
                         btn_primary("Book a Consultation", "/book-consultation/?interest=Study+Abroad+Counseling", "calendar") + btn_light("Explore destinations", "#destinations", "globe"),
                         aside=f'<div class="hero-flags">{"".join(flag(d["flag"], 40) for d in DESTINATIONS)}</div>')
    other = join_words(OTHER_DESTINATIONS)
    svc_links = "".join(f'<li><a href="{svc_url(edu, s)}"><strong>{e(s["name"])}</strong><span>{e(s["short"])}</span></a></li>' for s in edu["services"])
    body = f"""<section class="section">
  <div class="container narrow center-text"><p class="big-text">{e(edu["intro"])}</p></div>
</section>
<section class="section tint" id="destinations">
  <div class="container">
    {section_head("Destinations", "Country-wise study destinations", "Select a country to see its education system, programs, requirements, costs, scholarships and visa process.")}
    <div class="dest-grid-cards">{"".join(dest_card(d) for d in DESTINATIONS)}</div>
    <div class="other-dest"><h3>Other available destinations</h3><p>We also guide students applying to {e(other or "other countries")}. <a href="/book-consultation/?interest=Other+study+destination">Ask us about another country</a>.</p></div>
  </div>
</section>
<section class="section">
  <div class="container">
    {section_head("Education services", "Support at every stage", f"{num_word(len(edu['services']))} services that cover your whole journey, from first counseling session to life after arrival.")}
    <ul class="svc-list">{svc_links}</ul>
  </div>
</section>
<section class="section tint"><div class="container narrow">{section_head("How it works", "Your study abroad journey")}{steps(edu["process"])}</div></section>
"""
    write_page("/study-abroad/", "Study Abroad", f"Study abroad with Nexbridge Solutions: university selection, admissions, scholarships and student visas for {', '.join(d['name'] for d in DESTINATIONS[:4])} and {max(len(DESTINATIONS) - 4, 0)} more destinations.",
               hero + body + consult_section("Study Abroad Counseling"), [bc], "0.9")


def build_destinations_index():
    hero, bc = page_hero("Our Destinations", f"{num_word(len(DESTINATIONS))} countries, one team to guide your application from shortlist to arrival.",
                         [("Our Destinations", "/destinations/")], "Study destinations",
                         btn_primary("Book a Consultation", "/book-consultation/", "calendar"))
    other = "".join(f"<li>{e(o[0].upper() + o[1:])}</li>" for o in OTHER_DESTINATIONS)
    body = f"""<section class="section"><div class="container">
  <div class="dest-grid-cards">{"".join(dest_card(d) for d in DESTINATIONS)}</div>
  <div class="other-dest"><h3>Other available destinations</h3><ul class="pill-list">{other}</ul><p><a href="/book-consultation/?interest=Other+study+destination">Ask about another destination {icon("arrow")}</a></p></div>
</div></section>"""
    write_page("/destinations/", "Our Destinations", f"Study destinations supported by Nexbridge Solutions: {join_words([d['name'] for d in DESTINATIONS])}.",
               hero + body + cta_band("Not sure which country is right for you?", "Our counselors compare destinations for your profile and budget."), [bc], "0.8")


def build_destination(d):
    trail = [("Study Abroad", "/study-abroad/"), (d["name"], dest_url(d))]
    interest = quote_plus(f"Study in {d['name']}")
    aside = (f'<div class="dest-hero-media"><img src="{d["image"]}" alt="Studying in {e(d["name"])}" width="520" height="560"></div>'
             if d.get("image") else f'<div class="dest-hero-flag">{flag(d["flag"], 160, "flag flag-xl")}</div>')
    hero, bc = page_hero(f"Study in {d['name']}", d["tagline"], trail, "Study destination",
                         btn_primary("Apply Now", f"/book-consultation/?interest={interest}", "send") + btn_light("Book a Consultation", f"/book-consultation/?interest={interest}", "calendar"),
                         aside=aside, cls="dest-hero")
    facts = "".join(f'<li>{icon("check")}{e(p)}</li>' for p in d["card_points"])
    sections = [
        ("overview", f"Introduction to {d['name']}", f"<p>{e(d['intro'])}</p>"),
        ("system", "Education system", f"<p>{e(d['system'])}</p>"),
        ("programs", "Available programs", checklist(d["programs"], "checklist two-col")),
        ("requirements", "Admission requirements", checklist(d["requirements"])),
        ("tuition", "Tuition fee overview", f"<p>{e(d['tuition'])}</p><p class=\"note\">Fees change every year. We share current figures for the universities on your shortlist.</p>"),
        ("scholarships", "Scholarships & funding", '<div class="scholarships">' + "".join(f'<div class="scholarship">{icon("award")}<div><h3>{e(n)}</h3><p>{e(t)}</p></div></div>' for n, t in d["scholarships"]) + "</div>"),
        ("process", "Application process", steps([(st, "") for st in d["process"]]).replace("<p></p>", "")),
        ("visa", "Student visa information", f"<p>{e(d['visa'])}</p><p class=\"note\">Visa decisions are made only by the relevant embassy or immigration authority. Requirements change, and we check the current rules for every application.</p>"),
        ("faqs", "Frequently asked questions", faq_list(d["faqs"])),
    ]
    toc = "".join(f'<li><a href="#{a}">{e(t)}</a></li>' for a, t, _ in sections)
    secs = "".join(f'<section id="{a}"><h2>{e(t)}</h2>{h}</section>' for a, t, h in sections)
    others = [x for x in DESTINATIONS if x is not d][:4]
    body = f"""<div class="fact-strip"><div class="container"><ul>{facts}</ul></div></div>
<div class="container with-aside">
  <article class="svc-main">{secs}</article>
  <aside class="svc-aside">
    <div class="aside-card accent"><h3>Apply to study in {e(d["name"])}</h3><p>Get a shortlist of universities and a clear plan for your application and visa.</p>
      {btn_light("Apply Now", f"/book-consultation/?interest={interest}", "send")}{btn_wa("Ask on WhatsApp", f"Hello Nexbridge Solutions, I would like to study in {d['name']}.")}</div>
    <nav class="aside-card" aria-label="On this page"><h3>On this page</h3><ul class="toc">{toc}</ul></nav>
  </aside>
</div>
<section class="section tint"><div class="container">{section_head("More destinations", "Compare other countries")}<div class="dest-grid-cards">{"".join(dest_card(x) for x in others)}</div></div></section>
{consult_section(f"Study in {d['name']}", f"Start your application to {d['name']}", "Tell us your academic background and goals, and we will suggest universities and next steps.")}"""
    write_page(dest_url(d), f"Study in {d['name']}", f"Study in {d['name']} with Nexbridge Solutions: {d['tagline']} Programs, admission requirements, tuition, scholarships and student visa guidance.",
               hero + body, [bc, faq_ld(d["faqs"])], "0.8")


def build_business_tax():
    biz, tax = CAT_BY_SLUG["business-corporate"], CAT_BY_SLUG["tax-legal"]
    hero, bc = page_hero("Business & Tax Consultancy", "Company and firm registration, SECP and FBR, tax returns, trademarks and strategic advice for businesses and individuals.",
                         [("Business & Tax Consultancy", "/business-tax-consultancy/")], "For entrepreneurs, businesses & individuals",
                         btn_primary("Book a Consultation", "/book-consultation/?interest=Business+Advisory+%26+Consultation", "calendar") + btn_light("Send an inquiry", "#inquiry", "send"),
                         aside=f'<span class="hero-icon">{icon("briefcase")}</span>')
    blocks = ""
    for c in (biz, tax):
        blocks += f"""<section class="section{' tint' if c is tax else ''}"><div class="container">
  {section_head(c["short_name"], c["name"], c["intro"])}
  <div class="svc-grid">{"".join(service_card(c, s) for s in c["services"])}</div>
</div></section>"""
    write_page("/business-tax-consultancy/", "Business & Tax Consultancy", "Business setup, company and SECP registration, NTN and FBR registration, income tax returns and trademark registration in Pakistan.",
               hero + blocks + f'<section class="section"><div class="container">{section_head("Why Nexbridge", "Benefits for your business")}{benefit_grid(biz)}</div></section>' + consult_section("Business Advisory & Consultation"),
               [bc], "0.9")


def build_partners():
    hero, bc = page_hero("Our Partners", "We build long-term partnerships that open doors to quality education, professional growth and international opportunity.",
                         [("Our Partners", "/partners/")], "Partnerships",
                         btn_primary("Become a partner", "#inquiry", "handshake"))
    def group(kind, i, t, d):
        mine = [p for p in PARTNERS if p["type"] == kind]
        logos = f'<div class="logo-grid">{"".join(partner_logo(p) for p in mine)}</div>' if mine else '<div class="logo-slot">Authorized partner logos will appear here</div>'
        return f'<div class="partner-card{" has-logos" if mine else ""}">{icon(i, "icon icon-lg")}<h2>{e(t)}</h2><p>{e(d)}</p>{logos}</div>'
    cards = "".join(group(k, i, t, d) for k, i, t, d in PARTNER_TYPES)
    body = f"""<section class="section"><div class="container narrow center-text"><p class="big-text">Our partner relations team represents Nexbridge internationally, building strategic relationships with universities, institutions, organizations and global partners. We display partner names and logos only with their authorization.</p></div>
<div class="container"><div class="partner-grid">{cards}</div></div></section>
{consult_section("Not sure yet", "Partner with Nexbridge Solutions", "Universities, institutions and organizations interested in working with us can reach our partner relations team here.")}"""
    write_page("/partners/", "Our Partners", "Nexbridge Solutions partners with universities, educational institutions, corporate partners and international organizations.",
               hero + body, [bc], "0.6")


def build_success():
    hero, bc = page_hero("Success Stories", "Visa approvals, admissions and client achievements, shared with our clients' permission.",
                         [("Success Stories", "/success-stories/")], "Results",
                         btn_primary("Share your story", "#inquiry", "quote"))
    def group(a, i, t, d):
        mine = [s for s in STORIES if s["type"] == a]
        inner = (f'<div class="story-grid">{"".join(story_card(s) for s in mine)}</div>' if mine else
                 f'<div class="gallery-empty">{icon("calendar")}<p>Verified stories are being collected with written consent from our clients and will be published here.</p></div>')
        return f"""<section class="story-group" id="{a}"><div class="story-group-head">{icon(i, "icon icon-lg")}<div><h2>{e(t)}</h2><p>{e(d)}</p></div></div>{inner}</section>"""
    order = sorted(STORY_TYPES, key=lambda g: not any(s["type"] == g[0] for s in STORIES))
    blocks = "".join(group(*g) for g in order)
    body = f"""<section class="section"><div class="container">{blocks}</div></section>
{consult_section("Not sure yet", "Are you a Nexbridge client?", "We would love to share your journey. Tell us about your visa, admission or business success, and we will contact you for your consent before publishing anything.")}"""
    write_page("/success-stories/", "Success Stories", "Visa approvals, university admissions, client testimonials and achievements from Nexbridge Solutions clients.", hero + body, [bc], "0.6")


def build_insights():
    hero, bc = page_hero("Knowledge Center", "Study abroad guides, visa updates, scholarship information, business advice, tax updates and travel guides.",
                         [("Knowledge Center", "/insights/")], "Latest updates & insights")
    chips = '<button class="chip active" type="button" data-filter="all">All</button>' + "".join(
        f'<button class="chip" type="button" data-filter="{e(c)}">{e(c)}</button>'
        for c in INSIGHT_CATEGORIES + sorted({a["category"] for a in ARTICLES} - set(INSIGHT_CATEGORIES)))
    posts = sorted(ARTICLES, key=lambda x: x["date"], reverse=True)
    body = f"""<section class="section"><div class="container">
  <div class="chips" role="group" aria-label="Filter articles">{chips}</div>
  <div class="post-grid" data-posts>{"".join(article_card(a) for a in posts)}</div>
  <p class="empty-filter" hidden data-empty>No articles in this category yet. Check back soon.</p>
</div></section>"""
    write_page("/insights/", "Knowledge Center", "Study abroad guides, visa updates, scholarships, business advisory, tax updates and travel guides from Nexbridge Solutions.",
               hero + body + cta_band("Have a question we haven't covered?", "Ask our advisers directly."), [bc], "0.7")
    for a in posts:
        d = date.fromisoformat(a["date"])
        crumb_html, bc = crumbs([("Knowledge Center", "/insights/"), (a["title"], f"/insights/{a['slug']}/")])
        parts = [f'<figure class="article-cover"><img src="{a["image"]}" alt="" width="960" height="540" decoding="async"></figure>'] if a["image"] else []
        parts.append(render_body(a["body"]))
        more = "".join(article_card(x) for x in posts if x is not a)
        body = f"""<section class="page-hero article-hero"><div class="container narrow">{crumb_html}<span class="eyebrow on-dark">{e(a["category"])}</span><h1>{e(a["title"])}</h1>
<p class="article-meta"><time datetime="{a['date']}">{d.strftime('%d %B %Y').lstrip('0')}</time> · Nexbridge Solutions</p></div></section>
<section class="section"><div class="container narrow prose">{"".join(parts)}<div class="article-cta">{btn_primary("Book a Consultation", "/book-consultation/", "calendar")}</div></div></section>
<section class="section tint"><div class="container">{section_head("Keep reading", "More from the Knowledge Center")}<div class="post-grid">{more}</div></div></section>"""
        ld = [bc, {"@context": "https://schema.org", "@type": "Article", "headline": a["title"], "datePublished": a["date"],
                   "author": {"@type": "Organization", "name": SITE["name"]}, "publisher": {"@type": "Organization", "name": SITE["name"],
                   "logo": {"@type": "ImageObject", "url": SITE["url"] + "/assets/img/logo.png"}}, "description": a["excerpt"]}]
        write_page(f"/insights/{a['slug']}/", a["title"], a["excerpt"], body, ld, "0.6")


def build_faqs():
    hero, bc = page_hero("Frequently Asked Questions", "Answers to common questions about our consultancy, study abroad, visas, business and tax, and travel services.",
                         [("FAQs", "/faqs/")], "Help center")
    jump = "".join(f'<a href="#faq-{i}">{e(t)}</a>' for i, (t, _) in enumerate(FAQ_GROUPS))
    groups = "".join(f'<section class="faq-group" id="faq-{i}"><h2>{e(t)}</h2>{faq_list(items)}</section>' for i, (t, items) in enumerate(FAQ_GROUPS))
    all_faqs = [f for _, items in FAQ_GROUPS for f in items]
    body = f"""<section class="section"><div class="container narrow"><nav class="jump-nav small" aria-label="FAQ topics">{jump}</nav>{groups}</div></section>"""
    write_page("/faqs/", "FAQs", "Frequently asked questions about Nexbridge Solutions consultancy, study abroad, visas, business and tax, and travel services.",
               hero + body + consult_section(title="Still have a question?"), [bc, faq_ld(all_faqs)], "0.6")


def build_contact():
    hero, bc = page_hero("Contact Us", "Send an inquiry, book an appointment or message our team.",
                         [("Contact Us", "/contact/")], "Get in touch")
    body = f"""<section class="section"><div class="container contact-grid">{inquiry_form(heading="Send us a message", submit="Send message", message_label="Your message")}{contact_panel()}</div></section>"""
    write_page("/contact/", "Contact Us", "Contact Nexbridge Solutions: send an inquiry, book a consultation or message us on WhatsApp.", hero + body, [bc], "0.8")


def build_booking():
    hero, bc = page_hero("Book a Consultation", "Choose a service and a preferred time. We will confirm your appointment by phone, WhatsApp or email.",
                         [("Book a Consultation", "/book-consultation/")], "Appointments")
    times = "".join(f"<option>{t}</option>" for t in ["Morning (10am to 1pm)", "Afternoon (1pm to 4pm)", "Evening (4pm to 7pm)"])
    modes = "".join(f'<label class="radio"><input type="radio" name="mode" value="{m}"{" checked" if i == 0 else ""}><span>{icon(ic)}{m}</span></label>'
                    for i, (m, ic) in enumerate([("In office", "building"), ("Phone call", "phone"), ("WhatsApp call", "whatsapp"), ("Online meeting", "globe")]))
    form = f"""<form class="form-card" method="post" action="/api/inquiry.php" data-validate>
  <h3>Request an appointment</h3>
  {FORM_STATUS}
  <input type="hidden" name="form_type" value="booking">
  <input type="hidden" name="page" value="" data-page-field>
  <div class="hp" aria-hidden="true"><label for="website-booking">Website</label><input id="website-booking" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="form-grid">
    <div class="field"><label for="b-name">Full name</label><input id="b-name" name="name" required maxlength="100" autocomplete="name"></div>
    <div class="field"><label for="b-phone">Phone / WhatsApp</label><input id="b-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+92 3xx xxxxxxx"></div>
    <div class="field"><label for="b-email">Email</label><input id="b-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
    <div class="field"><label for="b-service">Service</label><select id="b-service" name="service" required data-interest>{service_options()}</select></div>
    <div class="field"><label for="b-date">Preferred date</label><input id="b-date" name="date" type="date" required data-min-today></div>
    <div class="field"><label for="b-time">Preferred time</label><select id="b-time" name="time">{times}</select></div>
    <fieldset class="field full"><legend>How would you like to meet?</legend><div class="radio-row">{modes}</div></fieldset>
    <div class="field full"><label for="b-message">Anything we should know? (optional)</label><textarea id="b-message" name="message" rows="3" maxlength="2000"></textarea></div>
  </div>
  <label class="consent"><input type="checkbox" name="consent" value="yes" required> I agree to be contacted about my appointment and accept the <a href="/privacy-policy/">Privacy Policy</a>.</label>
  <button class="btn btn-primary btn-block" type="submit">{icon("calendar")}Request appointment</button>
</form>"""
    expect = f"""<div class="contact-panel">
  <h3>What to expect</h3>
  {steps([("We confirm your slot", "Our team contacts you to confirm the date, time and format."), ("Bring your documents", "Have your passport, transcripts or business details ready if you have them."), ("Get clear advice", "Your adviser explains your options, costs, timelines and next steps.")])}
  <div class="contact-actions">{btn_wa()}</div>
</div>"""
    write_page("/book-consultation/", "Book a Consultation", "Book a consultation with Nexbridge Solutions for study abroad, visa, business, tax, travel or language services.",
               hero + f'<section class="section"><div class="container contact-grid">{form}{expect}</div></section>', [bc], "0.9")


def build_thanks():
    body = f"""<section class="section thanks"><div class="container narrow center-text">
  <span class="icon-badge big">{icon("check")}</span>
  <h1>Thank you, we've received your request</h1>
  <p class="big-text">An adviser will contact you shortly, usually within one working day. Keep an eye on your phone and email.</p>
  <div class="hero-actions center">{btn_primary("Back to home", "/")}{btn_wa()}</div>
</div></section>"""
    write_page("/thank-you/", "Thank you", "Your request has been received.", body)


def legal_page(path, title, sections, desc):
    hero, bc = page_hero(title, f"Last updated {date.today().strftime('%d %B %Y').lstrip('0')}.", [(title, path)], "Legal")
    html_parts = "".join(f"<h2>{e(h)}</h2>" + "".join(f"<p>{e(p)}</p>" for p in ps) for h, ps in sections)
    write_page(path, title, desc, hero + f'<section class="section"><div class="container narrow prose">{html_parts}</div></section>', [bc], "0.3")


def build_legal():
    legal_page("/privacy-policy/", "Privacy Policy", [
        ("Who we are", [f"This website is operated by {SITE['name']}. This policy explains how we collect and use personal information when you use our website or contact us."]),
        ("Information we collect", ["When you submit an inquiry or booking form, we collect the details you provide, such as your name, phone number, email address, the service you are interested in, your preferred appointment time and your message.", "Our web server may also record standard technical information, such as your IP address and browser type, for security and performance."]),
        ("How we use your information", ["We use your information to respond to your inquiry, arrange consultations, provide the services you request and keep records of our communication with you. We do not sell your personal information."]),
        ("Sharing your information", ["We share information only where needed to provide a service you have asked for, for example with a university, embassy, government authority or service provider, and only with your knowledge, or where the law requires it."]),
        ("Third-party services", ["Our website loads fonts from Google Fonts, and may include an embedded Google Map and WhatsApp links. These providers may receive technical information such as your IP address under their own privacy policies. If we enable analytics, we will use it only to understand how our website is used."]),
        ("Keeping your information safe", ["We take reasonable technical and organizational measures to protect your information, and keep it only as long as needed for the purposes above or as required by law."]),
        ("Your choices", ["You can ask us to access, correct or delete the personal information we hold about you by contacting us through our contact page."]),
        ("Changes to this policy", ["We may update this policy from time to time. The date at the top of this page shows when it was last updated."]),
    ], "How Nexbridge Solutions collects, uses and protects your personal information.")
    legal_page("/terms/", "Terms & Conditions", [
        ("Using this website", [f"By using this website you agree to these terms. The website is provided by {SITE['name']} for general information about our services."]),
        ("Information only", ["Content on this website, including articles and destination information, is general guidance and not legal, immigration, tax or financial advice for your specific situation. Requirements change often, so always confirm current rules with us or the relevant authority before acting."]),
        ("No guaranteed outcomes", ["Admissions, scholarships, visas and registrations are decided by universities, embassies and authorities. We do not guarantee any particular outcome. We commit to handling your case carefully, honestly and professionally."]),
        ("Fees", ["Our service fees are confirmed with you before work begins. University, embassy, government and third-party fees are separate and payable by the client unless agreed otherwise in writing."]),
        ("Your responsibilities", ["You are responsible for providing accurate, complete and genuine information and documents. We cannot be responsible for outcomes affected by incorrect or misleading information."]),
        ("Intellectual property", ["The Nexbridge Solutions name, logo and website content belong to Nexbridge Solutions and may not be copied or used without permission."]),
        ("External links", ["This website may link to external websites. We are not responsible for their content or practices."]),
        ("Changes", ["We may update these terms from time to time. Continued use of the website means you accept the current terms."]),
    ], "Terms and conditions for using the Nexbridge Solutions website and services.")


def build_gallery():
    if not GALLERY:
        return
    hero, bc = page_hero("Gallery", "Moments from our office, events, university visits and client celebrations.",
                         [("About Us", "/about/"), ("Gallery", "/gallery/")], "Life at Nexbridge")
    albums = list(dict.fromkeys(g["album"] for g in GALLERY))
    chips = ""
    if len(albums) > 1:
        chips = '<div class="chips" role="group" aria-label="Filter photos"><button class="chip active" type="button" data-filter="all">All</button>' + "".join(
            f'<button class="chip" type="button" data-filter="{e(a)}">{e(a)}</button>' for a in albums) + "</div>"
    figs = "".join(f"""<figure class="gallery-item" data-category="{e(g['album'])}"><a href="{g['image']}" target="_blank" rel="noopener">
<img src="{g['image']}" alt="{e(g['caption'] or g['album'])}" loading="lazy" decoding="async"></a>{f"<figcaption>{e(g['caption'])}</figcaption>" if g['caption'] else ""}</figure>""" for g in GALLERY)
    body = f"""<section class="section"><div class="container">{chips}<div class="gallery-grid" data-posts>{figs}</div>
<p class="empty-filter" hidden data-empty>No photos in this album yet.</p></div></section>"""
    write_page("/gallery/", "Gallery", "Photos from Nexbridge Solutions: our office, events, university visits and client celebrations.",
               hero + body + cta_band("Start your own journey", "Book a consultation with a Nexbridge adviser."), [bc], "0.5")


def build_404():
    body = f"""<section class="section thanks"><div class="container narrow center-text">
  <span class="icon-badge big">{icon("globe")}</span>
  <h1>We couldn't find that page</h1>
  <p class="big-text">The page may have moved. Try one of these instead.</p>
  <div class="hero-actions center">{btn_primary("Home", "/")}<a class="btn btn-outline" href="/services/">Consultancy Services</a><a class="btn btn-outline" href="/study-abroad/">Study Abroad</a></div>
</div></section>"""
    write_page("/404.html", "Page not found", "Page not found.", body)


def build_sitemap():
    today = date.today().isoformat()
    urls = "".join(f"<url><loc>{SITE['url']}{p}</loc><lastmod>{today}</lastmod><priority>{pr}</priority></url>" for p, pr in SITEMAP)
    (ROOT / "sitemap.xml").write_text(f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{urls}</urlset>\n', encoding="utf-8")
    (ROOT / "robots.txt").write_text(f"User-agent: *\nDisallow: /api/\nDisallow: /build/\nDisallow: /admin/\nDisallow: /content/\n\nSitemap: {SITE['url']}/sitemap.xml\n", encoding="utf-8")


def main():
    for d in GENERATED_DIRS:
        shutil.rmtree(ROOT / d, ignore_errors=True)
    if "--no-images" not in sys.argv:
        import images  # needs Pillow; the admin panel's automatic build skips this step
        images.build_all(ROOT)
    build_home()
    build_about()
    build_team()
    build_services_hub()
    for c in CATEGORIES:
        build_category(c)
        for s in c["services"]:
            build_service(c, s)
    build_study_abroad()
    build_destinations_index()
    for d in DESTINATIONS:
        build_destination(d)
    build_business_tax()
    build_partners()
    build_success()
    build_insights()
    build_faqs()
    build_contact()
    build_booking()
    build_thanks()
    build_legal()
    build_gallery()
    build_404()
    build_sitemap()
    print(f"Built {len(SITEMAP) + 2} pages (asset version {VER}).")


if __name__ == "__main__":
    main()
