"""
Core font-subsetting logic for the Aksara font preview service.

Given a full font file and a short piece of text, produce a minimal
.woff2 font that contains *only* the glyphs needed to render that text
(plus the handful of glyphs fontTools always keeps for a valid font:
.notdef, space, etc). This is what lets the storefront show a live
typing-preview of a font the visitor has not purchased, without ever
exposing the full character set (and therefore the full font) before
checkout.

Kept deliberately framework-agnostic (no Flask import here) so it can
be unit-tested or reused from a CLI without spinning up a web server.
"""

from __future__ import annotations

import io
from dataclasses import dataclass

from fontTools.subset import Options, Subsetter
from fontTools.ttLib import TTFont

# Hard cap on preview text length. Mirrors the PRD requirement ("maksimal
# 60-100 karakter") so a single request can't be (ab)used to subset an
# arbitrarily large chunk of the character set in one go. Combined with
# per-IP rate limiting upstream, this bounds how much of a font's outline
# data a single client can extract per unit time.
MAX_TEXT_LENGTH = 100


class SubsetError(ValueError):
    """Raised when the request can't be turned into a valid subset."""


@dataclass
class SubsetResult:
    woff2_bytes: bytes
    glyph_count: int
    requested_chars: int


def subset_font_to_woff2(font_bytes: bytes, text: str) -> SubsetResult:
    """Subset ``font_bytes`` down to the glyphs required by ``text``.

    Raises SubsetError for empty/too-long text or a font that fontTools
    can't parse; callers are expected to turn that into an HTTP 400.
    """
    if not text or not text.strip():
        raise SubsetError("text must not be empty")

    if len(text) > MAX_TEXT_LENGTH:
        raise SubsetError(
            f"text exceeds the {MAX_TEXT_LENGTH}-character preview limit"
        )

    try:
        font = TTFont(io.BytesIO(font_bytes))
    except Exception as exc:  # fontTools raises assorted exception types
        raise SubsetError(f"could not parse font file: {exc}") from exc

    options = Options()
    options.flavor = "woff2"
    # Keep the layout features that actually affect how the previewed
    # text renders (kerning, ligatures, etc.) rather than stripping GSUB/
    # GPOS entirely — a preview that mis-kerns isn't a useful preview.
    options.layout_features = ["*"]
    options.desubroutinize = True
    options.recalc_bounds = True
    options.recalc_timestamp = False
    # Trim the name table (foundry/vendor URLs, license text, etc.) to the
    # bare minimum needed for the browser to load the font — none of that
    # metadata belongs in a short-lived anonymous preview file.
    options.name_IDs = [1, 2, 4, 6]
    options.name_legacy = False
    options.notdef_outline = False
    options.glyph_names = False

    subsetter = Subsetter(options=options)
    subsetter.populate(text=text)
    subsetter.subset(font)

    # Subsetter.subset() doesn't apply options.flavor itself (that's done
    # by fontTools' subset CLI driver, which we bypass by using the
    # Subsetter API directly) — set it explicitly or font.save() below
    # would silently emit a plain .ttf instead of .woff2.
    font.flavor = options.flavor

    buf = io.BytesIO()
    font.save(buf)

    glyph_count = font["maxp"].numGlyphs if "maxp" in font else 0

    return SubsetResult(
        woff2_bytes=buf.getvalue(),
        glyph_count=glyph_count,
        requested_chars=len(set(text)),
    )
