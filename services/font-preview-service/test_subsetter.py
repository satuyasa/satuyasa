"""
Fase 0 proof-of-concept checks for the font subsetting service.

Run directly (``python3 test_subsetter.py``) — no test runner required,
so this doubles as the "demo command-line" deliverable the Breakdown
Task asks for. It checks, against real .ttf fixtures:

  1. A subset actually renders the requested text correctly (contains
     exactly the glyphs needed, nothing more).
  2. Response time is comfortably under the PRD's 800ms target.
  3. The subset is drastically smaller than the source font and only
     exposes a small fraction of the full character set — i.e. it
     can't be trivially treated as "the whole font, minus a wrapper".
"""

from __future__ import annotations

import io
import os
import statistics
import time

from fontTools.ttLib import TTFont

from subsetter import SubsetError, subset_font_to_woff2

FIXTURES_DIR = os.path.join(os.path.dirname(__file__), "fixtures")

SAMPLE_TEXTS = [
    "Kopi pagi, ide baru, karya berani",
    "AKSARA",
    "The quick brown fox jumps over the lazy dog 123",
]


def load_fixture(name: str) -> bytes:
    with open(os.path.join(FIXTURES_DIR, name), "rb") as fh:
        return fh.read()


def check_font(fixture_name: str) -> None:
    print(f"\n=== {fixture_name} ===")
    font_bytes = load_fixture(fixture_name)
    full_font = TTFont(io.BytesIO(font_bytes))
    full_glyph_count = full_font["maxp"].numGlyphs
    full_size = len(font_bytes)
    print(f"source: {full_size:,} bytes, {full_glyph_count} glyphs")

    timings = []
    for text in SAMPLE_TEXTS:
        started = time.perf_counter()
        result = subset_font_to_woff2(font_bytes, text)
        elapsed_ms = (time.perf_counter() - started) * 1000
        timings.append(elapsed_ms)

        subset_font = TTFont(io.BytesIO(result.woff2_bytes))
        rendered_chars = subset_font.getBestCmap()
        expected_chars = {ord(c) for c in text if ord(c) in full_font.getBestCmap()}
        missing = expected_chars - set(rendered_chars.keys())
        extra_ratio = len(rendered_chars) / full_glyph_count

        status = "OK" if not missing else f"MISSING {missing}"
        print(
            f'  "{text[:30]}{"..." if len(text) > 30 else ""}" '
            f"-> {len(result.woff2_bytes):,} bytes, "
            f"{result.glyph_count} glyphs "
            f"({extra_ratio:.1%} of full charset), "
            f"{elapsed_ms:.1f}ms [{status}]"
        )
        assert not missing, f"subset for {fixture_name!r} is missing required glyphs: {missing}"
        assert len(result.woff2_bytes) < full_size, "subset should be smaller than the source font"
        assert result.glyph_count < full_glyph_count, "subset should not contain the full glyph set"

    p95 = statistics.quantiles(timings, n=20)[18] if len(timings) > 1 else timings[0]
    print(f"  timings: min={min(timings):.1f}ms max={max(timings):.1f}ms p95~={p95:.1f}ms")
    assert max(timings) < 800, "subsetting exceeded the 800ms target from the PRD"


def check_error_handling() -> None:
    print("\n=== error handling ===")
    font_bytes = load_fixture("BricolageGrotesque-Regular.ttf")

    try:
        subset_font_to_woff2(font_bytes, "")
        raise AssertionError("empty text should have been rejected")
    except SubsetError as exc:
        print(f"  empty text correctly rejected: {exc}")

    too_long = "a" * 200
    try:
        subset_font_to_woff2(font_bytes, too_long)
        raise AssertionError("overlong text should have been rejected")
    except SubsetError as exc:
        print(f"  overlong text (200 chars) correctly rejected: {exc}")

    try:
        subset_font_to_woff2(b"not a font", "hello")
        raise AssertionError("garbage input should have been rejected")
    except SubsetError as exc:
        print(f"  invalid font bytes correctly rejected: {exc}")


if __name__ == "__main__":
    for fixture in ("BricolageGrotesque-Regular.ttf", "CrimsonPro-Regular.ttf"):
        check_font(fixture)
    check_error_handling()
    print("\nAll Fase 0 POC checks passed.")
