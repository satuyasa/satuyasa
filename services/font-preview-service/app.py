"""
Aksara font preview microservice — Fase 0 proof of concept.

Architecture (per Starter Brief decisions):
  - Runs on the SAME server as WordPress, on an internal-only port
    (bind to 127.0.0.1, never expose publicly — WordPress is the only
    caller, via loopback HTTP).
  - Font originals live on local disk, outside the web root, in
    AKSARA_FONT_STORAGE_DIR. WordPress passes a path *relative* to that
    directory rather than uploading the font on every preview request,
    since both processes share the same filesystem.

This service does ONE thing: turn "font + short text" into a small
.woff2 subset. It does not know about WooCommerce, orders, or signed
URLs — issuing a short-lived signed URL for the result is the calling
WordPress plugin's job (`class-preview-service-client.php`, Fase 2).
Keeping this boundary is what let this be built and load-tested as a
standalone POC before the rest of the plugin exists.
"""

from __future__ import annotations

import os
import time
from collections import defaultdict, deque

from flask import Flask, jsonify, request, send_file
import io

from subsetter import MAX_TEXT_LENGTH, SubsetError, subset_font_to_woff2

app = Flask(__name__)

# Directory the WordPress plugin stores original font files in. Requests
# may only reference files inside this directory (see _resolve_font_path) —
# this is what stands between "internal API on localhost" and an arbitrary
# local-file-read bug.
FONT_STORAGE_DIR = os.environ.get(
    "AKSARA_FONT_STORAGE_DIR",
    os.path.join(os.path.dirname(__file__), "fixtures"),
)

# Very small in-memory per-IP rate limiter. This is a POC-grade backstop,
# NOT the real defense — it resets on process restart and doesn't survive
# multiple workers. The PRD calls for rate limiting the preview endpoint
# to stop charset-scraping via many small requests; the durable version of
# that belongs at the WordPress REST layer / reverse proxy in Fase 2,
# where it can be tied to logged-in session + persistent storage.
RATE_LIMIT_WINDOW_SECONDS = 60
RATE_LIMIT_MAX_REQUESTS = 30
_request_log: dict[str, deque] = defaultdict(deque)


def _rate_limited(client_ip: str) -> bool:
    now = time.monotonic()
    log = _request_log[client_ip]
    while log and now - log[0] > RATE_LIMIT_WINDOW_SECONDS:
        log.popleft()
    if len(log) >= RATE_LIMIT_MAX_REQUESTS:
        return True
    log.append(now)
    return False


def _resolve_font_path(font_path: str) -> str:
    """Resolve a caller-supplied relative path safely inside FONT_STORAGE_DIR.

    Rejects absolute paths and any ``..`` traversal so this endpoint can't
    be used to read arbitrary files off the server.
    """
    base = os.path.realpath(FONT_STORAGE_DIR)
    candidate = os.path.realpath(os.path.join(base, font_path))
    if os.path.commonpath([base, candidate]) != base:
        raise SubsetError("font_path is outside the allowed storage directory")
    if not os.path.isfile(candidate):
        raise SubsetError(f"no such font file: {font_path}")
    return candidate


@app.get("/health")
def health():
    return jsonify(status="ok", max_text_length=MAX_TEXT_LENGTH)


@app.post("/v1/subset")
def subset():
    client_ip = request.headers.get("X-Forwarded-For", request.remote_addr or "unknown")
    if _rate_limited(client_ip):
        return jsonify(error="rate limit exceeded, try again shortly"), 429

    text = request.form.get("text", "")

    try:
        if "font" in request.files:
            font_bytes = request.files["font"].read()
        elif request.form.get("font_path"):
            path = _resolve_font_path(request.form["font_path"])
            with open(path, "rb") as fh:
                font_bytes = fh.read()
        else:
            return jsonify(error="provide either a 'font' file or a 'font_path'"), 400

        started = time.perf_counter()
        result = subset_font_to_woff2(font_bytes, text)
        elapsed_ms = round((time.perf_counter() - started) * 1000, 1)
    except SubsetError as exc:
        return jsonify(error=str(exc)), 400

    response = send_file(
        io.BytesIO(result.woff2_bytes),
        mimetype="font/woff2",
        as_attachment=False,
        download_name="preview.woff2",
    )
    response.headers["X-Subset-Glyph-Count"] = str(result.glyph_count)
    response.headers["X-Subset-Time-Ms"] = str(elapsed_ms)
    response.headers["Cache-Control"] = "no-store"
    return response


if __name__ == "__main__":
    # Bind to loopback only — this service must never be reachable from
    # outside the box. WordPress calls it over http://127.0.0.1:5055.
    app.run(host="127.0.0.1", port=int(os.environ.get("PORT", 5055)))
