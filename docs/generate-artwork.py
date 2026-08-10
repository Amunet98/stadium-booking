#!/usr/bin/env python3
"""
Generate the club badges and venue graphics under src/public/assets/img/.

    python3 docs/generate-artwork.py

The 2021 project shipped four PNGs and hardcoded one of them onto every card,
so a fixture between any two teams showed Tottenham versus Manchester United
(see docs/SECURITY-FINDINGS.md). Replacing them raised a licensing question:
club crests are registered trademarks and their photographs are somebody's
copyright, neither of which belongs in a public repository.

Club *colours* are not. So the artwork here is original: a shield mark carrying
each club's colours, its kit motif (Newcastle's stripes, Villa's claret and
blue band, City's chevron) and its three-letter code, plus an abstract
floodlit-ground graphic tinted to the home side. Nothing is traced from a
crest, and no photograph is involved.

Generated rather than hand-written because twelve files sharing one geometry
drift apart the moment they are edited separately. Committed output, so the
build does not depend on Python; re-run this only when the artwork changes.
"""

from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "src" / "public" / "assets" / "img"

# Sans stack rather than a webfont: an SVG loaded through <img> is an isolated
# document and cannot fetch the page's fonts, so anything not already on the
# system would silently fall back mid-render.
FONT = "system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif"

NIGHT = "#0b1116"  # the sky behind every ground, and the mixing base for tints


# --- clubs -----------------------------------------------------------------
#
# primary/secondary are the shirt colours; `motif` selects the kit pattern the
# badge is built from. `ink` is the code colour, chosen for contrast against
# whichever field it lands on.
CLUBS = {
    "arsenal": {
        "code": "ARS", "primary": "#EF0107", "secondary": "#FFFFFF",
        "ink": "#FFFFFF", "motif": "sleeves", "venue": "emirates",
    },
    "liverpool": {
        "code": "LIV", "primary": "#C8102E", "secondary": "#F6EB61",
        "ink": "#FFFFFF", "motif": "solid", "venue": "anfield",
    },
    "man-city": {
        "code": "MCI", "primary": "#6CABDD", "secondary": "#1C2C5B",
        "ink": "#FFFFFF", "motif": "chevron", "venue": "etihad",
    },
    "man-utd": {
        "code": "MUN", "primary": "#DA291C", "secondary": "#000000",
        "ink": "#FFFFFF", "motif": "base", "venue": "old-trafford",
    },
    "newcastle": {
        "code": "NEW", "primary": "#241F20", "secondary": "#FFFFFF",
        "ink": "#FFFFFF", "motif": "stripes", "venue": "st-james-park",
    },
    "aston-villa": {
        "code": "AVL", "primary": "#670E36", "secondary": "#95BFE5",
        "ink": "#FFFFFF", "motif": "band", "venue": "villa-park",
    },
}

VENUE_NAMES = {
    "emirates": "Emirates Stadium",
    "anfield": "Anfield",
    "etihad": "Etihad Stadium",
    "old-trafford": "Old Trafford",
    "st-james-park": "St James' Park",
    "villa-park": "Villa Park",
}

CLUB_NAMES = {
    "arsenal": "Arsenal",
    "liverpool": "Liverpool",
    "man-city": "Manchester City",
    "man-utd": "Manchester United",
    "newcastle": "Newcastle United",
    "aston-villa": "Aston Villa",
}

# A shield, drawn once. Rounded shoulders, a point at the foot, sized to sit
# inside a 64x64 box with room for the rim stroke.
SHIELD = ("M32 3.5 L57 11.5 V31 C57 45.5 46.2 55.6 32 60.5 "
          "C17.8 55.6 7 45.5 7 31 V11.5 Z")


def mix(hex_a: str, hex_b: str, t: float) -> str:
    """Blend two #rrggbb colours; t=0 returns a, t=1 returns b."""
    a = [int(hex_a[i:i + 2], 16) for i in (1, 3, 5)]
    b = [int(hex_b[i:i + 2], 16) for i in (1, 3, 5)]
    return "#" + "".join(f"{round(x + (y - x) * t):02x}" for x, y in zip(a, b))


def badge(slug: str, club: dict) -> str:
    """A 64x64 shield mark in the club's colours."""
    p, s, ink = club["primary"], club["secondary"], club["ink"]
    motif = club["motif"]

    if motif == "stripes":
        # Newcastle: black and white vertical stripes.
        parts = [f'<rect x="{x}" y="0" width="7" height="64" fill="{s}"/>'
                 for x in range(7, 58, 14)]
    elif motif == "sleeves":
        # Arsenal: red body, white sleeves. Inset from the shield edge — flush
        # against it the curve clips them down to a sliver.
        parts = [f'<rect x="11" y="0" width="8" height="64" fill="{s}"/>',
                 f'<rect x="45" y="0" width="8" height="64" fill="{s}"/>']
    elif motif == "band":
        # Villa: claret field, sky-blue band down the left.
        parts = [f'<rect x="12" y="0" width="12" height="64" fill="{s}"/>']
    elif motif == "chevron":
        # City: sky blue field, navy chevron across the foot.
        parts = [f'<path d="M0 47 L32 57 L64 47 V64 H0 Z" fill="{s}"/>']
    elif motif == "base":
        # United: red field over a black base.
        parts = [f'<rect x="0" y="48" width="64" height="16" fill="{s}"/>']
    else:  # solid — Liverpool wears one colour and needs no second mark
        parts = []

    body = "\n    ".join([f'<rect width="64" height="64" fill="{p}"/>'] + parts)
    label = CLUB_NAMES[slug]
    plate = mix(p, NIGHT, 0.62)

    # The code sits on a nameplate rather than straight on the motif. Newcastle
    # is why: white letters on vertical white stripes are unreadable, and
    # picking a per-club text colour to dodge that would leave six badges with
    # no shared structure. The plate also guarantees the 4.5:1 the code needs,
    # whatever the field behind it.
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="{label}">
  <defs>
    <clipPath id="s"><path d="{SHIELD}"/></clipPath>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffffff" stop-opacity=".18"/>
      <stop offset=".55" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
  </defs>
  <g clip-path="url(#s)">
    {body}
    <rect x="0" y="23" width="64" height="20" fill="{plate}"/>
    <rect x="0" y="23" width="64" height="1.5" fill="{mix(p, "#ffffff", .3)}"/>
    <rect x="0" y="41" width="64" height="2" fill="{s}"/>
    <rect width="64" height="64" fill="url(#g)"/>
  </g>
  <path d="{SHIELD}" fill="none" stroke="{mix(p, "#ffffff", .45)}" stroke-width="2.5"/>
  <text x="32" y="38" text-anchor="middle" font-family="{FONT}" font-size="15"
        font-weight="700" letter-spacing=".5" fill="{ink}">{club["code"]}</text>
</svg>
'''


def venue(slug: str, tint: str) -> str:
    """A 400x225 floodlit-ground graphic, tinted to the home club."""
    name = VENUE_NAMES[slug]
    horizon = mix(NIGHT, tint, 0.38)
    stand = mix(NIGHT, tint, 0.22)
    seats = mix(NIGHT, tint, 0.42)
    # The sky and stands carry the club tint; grass does not. At 0.18 Anfield's
    # red pushed the pitch to brown, which reads as a bug rather than a colour.
    turf = mix("#123d26", tint, 0.07)

    # Two tiers of crowd, drawn as blocks rather than individual seats: at card
    # size anything finer turns to mush.
    crowd = "\n    ".join(
        f'<rect x="{x}" y="{y}" width="26" height="9" rx="2.5"/>'
        for y in (74, 88)
        for x in range(18, 380, 32)
    )

    # Floodlight pylons, mirrored either side.
    def pylon(cx: int) -> str:
        lamps = "".join(
            f'<circle cx="{cx - 15 + c * 10}" cy="{26 + r * 9}" r="2.6"/>'
            for r in range(2) for c in range(4)
        )
        return (f'<g>'
                f'<ellipse cx="{cx}" cy="34" rx="54" ry="40" fill="url(#beam)"/>'
                f'<rect x="{cx - 2.5}" y="40" width="5" height="72" fill="{stand}"/>'
                f'<rect x="{cx - 22}" y="16" width="44" height="26" rx="4" fill="{stand}"/>'
                f'<g fill="#fdf6e3">{lamps}</g>'
                f'</g>')

    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 225" role="img" aria-label="{name}">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{NIGHT}"/>
      <stop offset="1" stop-color="{horizon}"/>
    </linearGradient>
    <radialGradient id="beam" cx=".5" cy=".5" r=".5">
      <stop offset="0" stop-color="#fff8e1" stop-opacity=".28"/>
      <stop offset="1" stop-color="#fff8e1" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="pitch" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{turf}"/>
      <stop offset="1" stop-color="{mix(turf, NIGHT, .35)}"/>
    </linearGradient>
  </defs>

  <rect width="400" height="225" fill="url(#sky)"/>
  {pylon(52)}
  {pylon(348)}

  <!-- Far stand: roof line, then the crowd behind it. -->
  <path d="M0 66 L400 66 L400 112 L0 112 Z" fill="{stand}"/>
  <path d="M0 66 L400 66 L400 60 L0 60 Z" fill="{mix(stand, "#ffffff", .12)}"/>
  <g fill="{seats}" opacity=".85">
    {crowd}
  </g>

  <!-- Pitch, cropped by the frame so the touchline curves out of shot. -->
  <ellipse cx="200" cy="215" rx="272" ry="108" fill="url(#pitch)"/>
  <g fill="none" stroke="#eaf5ee" stroke-width="2" opacity=".45">
    <ellipse cx="200" cy="215" rx="228" ry="88"/>
    <circle cx="200" cy="215" r="42"/>
    <line x1="200" y1="127" x2="200" y2="225"/>
  </g>
</svg>
'''


def main() -> None:
    (OUT / "badges").mkdir(parents=True, exist_ok=True)
    (OUT / "venues").mkdir(parents=True, exist_ok=True)

    written = 0
    for slug, club in CLUBS.items():
        (OUT / "badges" / f"{slug}.svg").write_text(badge(slug, club), "utf-8")
        (OUT / "venues" / f"{club['venue']}.svg").write_text(
            venue(club["venue"], club["primary"]), "utf-8")
        written += 2

    print(f"wrote {written} files to {OUT}")


if __name__ == "__main__":
    main()
