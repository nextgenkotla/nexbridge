from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageOps

SRC = Path(__file__).resolve().parent / "source-img"


def cover(img, w, h, anchor_y=0.15):
    img = ImageOps.exif_transpose(img)
    scale = max(w / img.width, h / img.height)
    resized = img.resize((round(img.width * scale), round(img.height * scale)), Image.LANCZOS)
    left = (resized.width - w) // 2
    top = round((resized.height - h) * anchor_y)
    return resized.crop((left, top, left + w, top + h))


def logo_on_transparent(img):
    """Turn the white logo background transparent while keeping colors."""
    img = img.convert("RGBA")
    px = img.load()
    for y in range(img.height):
        for x in range(img.width):
            r, g, b, a = px[x, y]
            lum = (r + g + b) / 3
            alpha = min(255, int((255 - lum) * 3.2)) if a else 0
            px[x, y] = (r, g, b, min(a, alpha))
    return img.crop(img.getbbox())


def white_version(img):
    out = Image.new("RGBA", img.size, (255, 255, 255, 0))
    alpha = img.getchannel("A")
    out.putalpha(alpha)
    white = Image.new("RGBA", img.size, (255, 255, 255, 255))
    white.putalpha(alpha)
    return white


def font(size, bold=False):
    for name in (["segoeuib.ttf", "arialbd.ttf"] if bold else ["segoeui.ttf", "arial.ttf"]):
        try:
            return ImageFont.truetype(f"C:/Windows/Fonts/{name}", size)
        except OSError:
            continue
    return ImageFont.load_default()


def build_all(root):
    out = root / "assets" / "img"
    out.mkdir(parents=True, exist_ok=True)

    logo = logo_on_transparent(Image.open(SRC / "logo.png"))
    h = 112
    logo_sized = logo.resize((round(logo.width * h / logo.height), h), Image.LANCZOS)
    logo_sized.save(out / "logo.png", optimize=True)
    white_version(logo_sized).save(out / "logo-white.png", optimize=True)

    full = Image.open(SRC / "logo.png").convert("RGBA")
    x_mark = logo_on_transparent(full.crop((530, 140, 1000, 480)))
    px = x_mark.load()
    for y in range(x_mark.height):
        for x in range(x_mark.width):
            r, g, b, a = px[x, y]
            if b < 120 and g < 110:
                px[x, y] = (r, g, b, 0)
    x_mark = x_mark.crop(x_mark.getbbox())
    side = max(x_mark.size) + 24
    for size, name, bg in ((64, "favicon.png", (255, 255, 255, 0)), (180, "apple-touch-icon.png", (255, 255, 255, 255))):
        canvas = Image.new("RGBA", (side, side), bg)
        canvas.alpha_composite(x_mark, ((side - x_mark.width) // 2, (side - x_mark.height) // 2))
        canvas.resize((size, size), Image.LANCZOS).save(out / name, optimize=True)

    person = ImageOps.exif_transpose(Image.open(SRC / "ashir.png")).convert("RGBA")
    person = person.resize((round(person.width * 760 / person.height), 760), Image.LANCZOS)
    person.save(out / "hero-person.webp", quality=82, method=6)

    team = {"uraiz": ("uraiz-light.jpeg", 0.12), "aqsa": ("aqsa.jpeg", 0.12),
            "ashir": ("ashir-istanbul.jpeg", 0.22), "noman": ("noman.png", 0.05)}
    for key, (fname, anchor) in team.items():
        img = Image.open(SRC / fname).convert("RGB")
        cover(img, 480, 600, anchor).save(out / f"team-{key}.webp", quality=80, method=6)

    cover(Image.open(SRC / "noman-office.jpeg").convert("RGB"), 640, 760, 0.25).save(out / "office.webp", quality=78, method=6)

    flags = out / "flags"
    flags.mkdir(exist_ok=True)
    for f in (SRC / "flags").glob("*.png"):
        img = Image.open(f).convert("RGB")
        cover(img, 128, 96, 0.5).save(flags / f"{f.stem}.webp", quality=88, method=6)
        cover(img, 320, 240, 0.5).save(flags / f"{f.stem}-lg.webp", quality=88, method=6)

    og = Image.new("RGB", (1200, 630), (13, 43, 94))
    draw = ImageDraw.Draw(og)
    for i in range(630):
        t = i / 630
        draw.line([(0, i), (1200, i)], fill=(int(13 + 5 * t), int(43 + 10 * t), int(94 + 30 * t)))
    draw.rectangle([0, 600, 1200, 630], fill=(22, 112, 232))
    draw.rectangle([0, 600, 600, 630], fill=(22, 199, 154))
    wl = white_version(logo)
    wl = wl.resize((640, round(640 * wl.height / wl.width)), Image.LANCZOS)
    og.paste(wl, ((1200 - wl.width) // 2, 170), wl)
    tagline = "Your Journey. Our Expertise. Your Future."
    f = font(40)
    tw = draw.textlength(tagline, font=f)
    draw.text(((1200 - tw) / 2, 430), tagline, font=f, fill=(200, 216, 240))
    og.save(out / "og.png", optimize=True)
