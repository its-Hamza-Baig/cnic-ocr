#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, HTTPServer
from typing import Any
from urllib.parse import parse_qs, urlparse

os.environ.setdefault("FLAGS_use_mkldnn", "0")
os.environ.setdefault("OMP_NUM_THREADS", "1")
os.environ.setdefault("MKL_NUM_THREADS", "1")
os.environ.setdefault("PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK", "True")

from paddleocr import PaddleOCR

HOST = os.environ.get("PADDLEOCR_HOST", "0.0.0.0")
PORT = int(os.environ.get("PADDLEOCR_PORT", "8868"))
DEFAULT_LANGS = os.environ.get("PADDLEOCR_LANGS", "en,ar")

ENGINES: dict[str, PaddleOCR] = {}
PREDICT_LOCK = threading.Lock()


def make_engine(lang: str) -> PaddleOCR:
    common = {
        "use_doc_orientation_classify": False,
        "use_doc_unwarping": False,
        "use_textline_orientation": False,
        "device": "cpu",
        "enable_mkldnn": False,
        "cpu_threads": 1,
        "enable_hpi": False,
        "ocr_version": "PP-OCRv5",
        "text_detection_model_name": "PP-OCRv5_mobile_det",
    }

    if lang in {"ar", "ur", "fa", "ug"}:
        return PaddleOCR(
            text_recognition_model_name="arabic_PP-OCRv5_mobile_rec",
            **common,
        )

    return PaddleOCR(
        lang=lang or "en",
        text_recognition_model_name="PP-OCRv5_mobile_rec",
        **common,
    )


def get_engine(lang: str) -> PaddleOCR:
    if lang not in ENGINES:
        ENGINES[lang] = make_engine(lang)

    return ENGINES[lang]


def extract_texts(result: Any) -> tuple[list[str], list[float]]:
    texts: list[str] = []
    scores: list[float] = []

    for res in result:
        payload = getattr(res, "json", None)
        if payload is None and isinstance(res, dict):
            payload = res
        if not isinstance(payload, dict):
            continue

        inner = payload.get("res", payload)
        rec_texts = inner.get("rec_texts") or []
        rec_scores = inner.get("rec_scores") or []

        if hasattr(rec_scores, "tolist"):
            rec_scores = rec_scores.tolist()

        texts.extend(str(line).strip() for line in rec_texts if str(line).strip() != "")
        scores.extend(float(score) for score in rec_scores)

    return texts, scores


def recognize(image_path: str, langs: list[str]) -> dict[str, Any]:
    all_texts: list[str] = []
    all_scores: list[float] = []

    for lang in langs:
        lang = lang.strip()
        if lang == "":
            continue

        with PREDICT_LOCK:
            result = get_engine(lang).predict(image_path)

        texts, scores = extract_texts(result)
        all_texts.extend(texts)
        all_scores.extend(scores)

    return {"rec_texts": all_texts, "rec_scores": all_scores}


class Handler(BaseHTTPRequestHandler):
    def log_message(self, format: str, *args: Any) -> None:
        return

    def _send_json(self, status: int, payload: dict[str, Any]) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:
        if urlparse(self.path).path != "/health":
            self.send_error(404)
            return

        self._send_json(200, {"ok": True, "engines": sorted(ENGINES)})

    def do_POST(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path != "/ocr":
            self.send_error(404)
            return

        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0:
            self.send_error(400)
            return

        raw = self.rfile.read(length)
        langs = parse_qs(parsed.query).get("langs", [DEFAULT_LANGS])[0]
        lang_list = [item for item in langs.split(",") if item.strip() != ""]

        suffix = ".png" if "png" in (self.headers.get("Content-Type") or "").lower() else ".jpg"
        handle, path = tempfile.mkstemp(suffix=suffix)

        try:
            with os.fdopen(handle, "wb") as output:
                output.write(raw)
            payload = recognize(path, lang_list)
        except Exception:
            self._send_json(500, {"error": "ocr_failed", "rec_texts": [], "rec_scores": []})
            return
        finally:
            if os.path.exists(path):
                os.unlink(path)

        self._send_json(200, payload)


def main() -> None:
    for lang in ["en", "ar"]:
        try:
            get_engine(lang)
        except Exception:
            continue

    HTTPServer((HOST, PORT), Handler).serve_forever()


if __name__ == "__main__":
    main()
