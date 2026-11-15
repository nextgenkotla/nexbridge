"""Loads the editable site content from /content/*.json.

The JSON files are edited by the admin panel (/admin/) or by hand. This module
validates them and converts them into the shapes build.py uses, so a mistake in
the content stops the build with a clear message instead of publishing a broken site.
"""
import json
import re
from pathlib import Path

CONTENT = Path(__file__).resolve().parent.parent / "content"
SLUG_RE = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
ASSET_RE = re.compile(r"^/assets/[A-Za-z0-9/_.\-]+$")
DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")


class ContentError(ValueError):
    pass


def load(name):
    try:
        return json.loads((CONTENT / name).read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise ContentError(f"content/{name} is not valid JSON: {exc}") from exc


def text(v):
    return v.strip() if isinstance(v, str) else ""


def strings(v):
    return [s.strip() for s in v if isinstance(s, str) and s.strip()] if isinstance(v, list) else []


def items(v):
    return [x for x in v if isinstance(x, dict)] if isinstance(v, list) else []


def pairs(v, a="title", b="desc"):
    return [(text(x.get(a)), text(x.get(b))) for x in items(v) if text(x.get(a))]


def slug(v, where):
    s = text(v)
    if not SLUG_RE.match(s):
        raise ContentError(f"{where}: slug '{s}' may only use lowercase letters, numbers and single hyphens")
    return s


def asset(v, where):
    s = text(v)
    if s and (not ASSET_RE.match(s) or ".." in s):
        raise ContentError(f"{where}: image path '{s}' must point to a file under /assets/")
    return s


def url(v):
    s = text(v)
    return s if re.match(r"^https?://[^\s\"'<>]+$", s) else ""


def unique(slugs, where):
    seen = set()
    for s in slugs:
        if s in seen:
            raise ContentError(f"{where}: the slug '{s}' is used twice")
        seen.add(s)


# ---------------------------------------------------------------- site settings
_site = load("site.json")
SITE = {k: text(_site.get(k)) for k in ("name", "url", "tagline", "description", "phone", "email", "address", "hours", "map_query", "ga4_id")}
SITE["url"] = SITE["url"].rstrip("/") or "https://nexbridge-solutions.com"
SITE["whatsapp"] = re.sub(r"\D", "", text(_site.get("whatsapp")))
SITE["socials"] = {k: url(v) for k, v in (_site.get("socials") or {}).items()}
if not re.match(r"^(G-[A-Z0-9]+)?$", SITE["ga4_id"]):
    raise ContentError("site.json: the Google Analytics ID should look like G-XXXXXXX")

# ---------------------------------------------------------------- homepage
_home = load("home.json")
HOME = {
    "hero_eyebrow": text(_home.get("hero_eyebrow")),
    "hero_title": text(_home.get("hero_title")) or SITE["tagline"],
    "hero_lead": text(_home.get("hero_lead")),
    "hero_image": asset(_home.get("hero_image"), "home.json hero image"),
    "about_image": asset(_home.get("about_image"), "home.json about image") or "/assets/img/office.webp",
    "about_points": strings(_home.get("about_points")),
}

# ---------------------------------------------------------------- about
_about = load("about.json")
ABOUT = {
    "intro": strings(_about.get("intro")),
    "vision": text(_about.get("vision")),
    "mission": text(_about.get("mission")),
    "commitment": text(_about.get("commitment")),
    "values": pairs(_about.get("values")),
}
WHY_US = [(text(x.get("title")), text(x.get("desc")), text(x.get("icon")) or "check") for x in items(_about.get("why_us")) if text(x.get("title"))]
PROCESS = pairs(_about.get("process"))

# ---------------------------------------------------------------- team
TEAM = []
for i, m in enumerate(items(load("team.json"))):
    where = f"team.json member {i + 1}"
    TEAM.append({"slug": slug(m.get("slug"), where), "name": text(m.get("name")), "role": text(m.get("role")),
                 "photo": asset(m.get("photo"), where), "summary": text(m.get("summary")), "bio": strings(m.get("bio"))})
unique([m["slug"] for m in TEAM], "team.json")

# ---------------------------------------------------------------- knowledge center
_articles = load("articles.json")
INSIGHT_CATEGORIES = strings(_articles.get("categories"))
ARTICLES = []
for i, a in enumerate(items(_articles.get("articles"))):
    where = f"articles.json article {i + 1}"
    if a.get("draft"):
        continue
    d = text(a.get("date"))
    if not DATE_RE.match(d):
        raise ContentError(f"{where}: date must be YYYY-MM-DD")
    ARTICLES.append({"slug": slug(a.get("slug"), where), "title": text(a.get("title")), "category": text(a.get("category")),
                     "date": d, "excerpt": text(a.get("excerpt")), "image": asset(a.get("image"), where),
                     "body": text(a.get("body"))})
unique([a["slug"] for a in ARTICLES], "articles.json")

# ---------------------------------------------------------------- FAQs
FAQ_GROUPS = [(text(g.get("group")), pairs(g.get("faqs"), "q", "a")) for g in items(load("faqs.json")) if text(g.get("group"))]

# ---------------------------------------------------------------- services
CATEGORIES = []
for ci, c in enumerate(items(load("services.json"))):
    where = f"services.json category {ci + 1}"
    services = []
    for si, s in enumerate(items(c.get("services"))):
        sw = f"{where}, service {si + 1}"
        svc = {"slug": slug(s.get("slug"), sw), "name": text(s.get("name")), "short": text(s.get("short")),
               "intro": text(s.get("intro")), "offers": strings(s.get("offers")), "faqs": pairs(s.get("faqs"), "q", "a")}
        for key in ("requirements", "documents", "related"):
            if strings(s.get(key)):
                svc[key] = strings(s.get(key))
        services.append(svc)
    unique([s["slug"] for s in services], where)
    CATEGORIES.append({"slug": slug(c.get("slug"), where), "name": text(c.get("name")), "short_name": text(c.get("short_name")) or text(c.get("name")),
                       "icon": text(c.get("icon")) or "layers", "summary": text(c.get("summary")), "intro": text(c.get("intro")),
                       "benefits": pairs(c.get("benefits")), "process": pairs(c.get("process")), "services": services})
unique([c["slug"] for c in CATEGORIES], "services.json")

# ---------------------------------------------------------------- destinations
_dest = load("destinations.json")
DESTINATIONS = []
for i, d in enumerate(items(_dest.get("destinations"))):
    where = f"destinations.json destination {i + 1}"
    flag = text(d.get("flag")).lower()
    if flag and not re.match(r"^[a-z]{2}$", flag):
        raise ContentError(f"{where}: flag must be a two-letter country code such as tr or gb")
    DESTINATIONS.append({"slug": slug(d.get("slug"), where), "name": text(d.get("name")), "flag": flag,
                         "image": asset(d.get("image"), where), "tagline": text(d.get("tagline")),
                         "card_points": strings(d.get("card_points")), "intro": text(d.get("intro")), "system": text(d.get("system")),
                         "programs": strings(d.get("programs")), "requirements": strings(d.get("requirements")),
                         "tuition": text(d.get("tuition")), "scholarships": pairs(d.get("scholarships")),
                         "process": strings(d.get("process")), "visa": text(d.get("visa")), "faqs": pairs(d.get("faqs"), "q", "a")})
unique([d["slug"] for d in DESTINATIONS], "destinations.json")
OTHER_DESTINATIONS = strings(_dest.get("other"))

# ---------------------------------------------------------------- stories, partners, gallery
STORY_TYPES = [("visa", "passport", "Visa success stories", "Study, visit and business visa approvals."),
               ("admissions", "cap", "Admission success stories", "Offers and enrolments at universities abroad."),
               ("testimonials", "quote", "Client testimonials", "Feedback from students, families and business clients."),
               ("achievements", "trophy", "Achievements & case studies", "Scholarships won, companies registered and milestones reached.")]
STORIES = []
for i, s in enumerate(items(load("stories.json"))):
    where = f"stories.json story {i + 1}"
    if not text(s.get("name")) or not text(s.get("text")) or not s.get("consent"):
        continue
    kind = text(s.get("type"))
    STORIES.append({"type": kind if kind in {t[0] for t in STORY_TYPES} else "testimonials", "name": text(s.get("name")),
                    "headline": text(s.get("headline")), "detail": text(s.get("detail")), "text": text(s.get("text")),
                    "photo": asset(s.get("photo"), where), "featured": bool(s.get("featured"))})

PARTNER_TYPES = [("universities", "cap", "Universities", "Universities abroad that welcome our students and work with us on admissions, scholarships and student support."),
                 ("institutions", "book", "Educational institutions", "Colleges, language schools and pathway providers that help students prepare for and progress in their studies."),
                 ("corporate", "building", "Corporate partners", "Businesses and service providers we work with to deliver travel, accommodation, insurance and corporate services."),
                 ("international", "globe", "International organizations", "Organizations that share our commitment to education, mobility and opportunity.")]
PARTNERS = []
for i, p in enumerate(items(load("partners.json"))):
    where = f"partners.json partner {i + 1}"
    if not text(p.get("name")):
        continue
    kind = text(p.get("type"))
    PARTNERS.append({"name": text(p.get("name")), "type": kind if kind in {t[0] for t in PARTNER_TYPES} else "universities",
                     "country": text(p.get("country")), "logo": asset(p.get("logo"), where), "url": url(p.get("url"))})

GALLERY = []
for i, g in enumerate(items(load("gallery.json"))):
    img = asset(g.get("image"), f"gallery.json photo {i + 1}")
    if img:
        GALLERY.append({"image": img, "caption": text(g.get("caption")), "album": text(g.get("album")) or "Nexbridge"})
