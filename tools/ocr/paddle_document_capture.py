#!/usr/bin/env python3
"""Bounded PaddleOCR bridge for QuoteFlow assisted document capture.

The Laravel app treats this as an optional local analyzer. It must return plain
JSON on stdout and avoid writing outside the configured cache directory.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from importlib import metadata
from pathlib import Path
from typing import Any


TEXT_KEYS = {
    "rec_text",
    "text",
    "block_content",
    "markdown",
    "markdown_text",
}

TEXT_LIST_KEYS = {
    "rec_texts",
    "texts",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Extract text with PaddleOCR for QuoteFlow.")
    parser.add_argument("--input", required=True)
    parser.add_argument("--expected-version", required=True)
    parser.add_argument("--max-pages", type=int, default=2)
    parser.add_argument("--device", default="cpu")
    parser.add_argument("--cache-dir", default="")

    return parser.parse_args()


def fail(message: str) -> int:
    print(json.dumps({"error": message}, ensure_ascii=True), file=sys.stderr)

    return 1


def configure_cache(cache_dir: str) -> None:
    if not cache_dir:
        return

    path = Path(cache_dir)
    path.mkdir(parents=True, exist_ok=True)

    os.environ.setdefault("PADDLEOCR_HOME", str(path))
    os.environ.setdefault("PADDLE_HOME", str(path))
    os.environ.setdefault("HF_HOME", str(path / "huggingface"))
    os.environ.setdefault("XDG_CACHE_HOME", str(path))


def collect_text(value: Any, texts: list[str]) -> None:
    if isinstance(value, dict):
        for key, child in value.items():
            if key in TEXT_LIST_KEYS and isinstance(child, list):
                texts.extend(str(item).strip() for item in child if str(item).strip())
                continue

            if key in TEXT_KEYS and isinstance(child, str) and child.strip():
                texts.append(child.strip())
                continue

            if key in {"res", "ocr_res", "prunedResult", "layout_parsing_result", "parsing_res_list", "blocks"}:
                collect_text(child, texts)

        return

    if isinstance(value, list):
        for item in value:
            collect_text(item, texts)


def result_json(result: Any) -> Any:
    json_value = getattr(result, "json", None)
    if json_value is not None:
        return json_value

    to_dict = getattr(result, "to_dict", None)
    if callable(to_dict):
        return to_dict()

    return result


def main() -> int:
    args = parse_args()
    input_path = Path(args.input)

    if not input_path.is_file():
        return fail("Input file does not exist.")

    configure_cache(args.cache_dir)

    try:
        installed_version = metadata.version("paddleocr")
    except metadata.PackageNotFoundError:
        return fail("paddleocr is not installed.")

    if installed_version != args.expected_version:
        return fail(f"Expected paddleocr {args.expected_version}, found {installed_version}.")

    try:
        from paddleocr import PaddleOCR
    except Exception as exc:  # noqa: BLE001
        return fail(f"Cannot import PaddleOCR: {exc}")

    try:
        ocr = PaddleOCR(
            use_doc_orientation_classify=False,
            use_doc_unwarping=False,
            use_textline_orientation=False,
            device=args.device,
            engine="paddle",
            text_recognition_batch_size=1,
        )

        predict = getattr(ocr, "predict_iter", None) or ocr.predict
        results = predict(input=str(input_path))
    except Exception as exc:  # noqa: BLE001
        return fail(f"PaddleOCR prediction failed: {exc}")

    max_pages = max(1, args.max_pages)
    texts: list[str] = []
    pages_processed = 0

    for result in results:
        if pages_processed >= max_pages:
            break

        pages_processed += 1
        collect_text(result_json(result), texts)

    output = {
        "pipeline": "ppocrv5",
        "version": installed_version,
        "pages_processed": pages_processed,
        "text": "\n".join(dict.fromkeys(texts)),
    }

    print(json.dumps(output, ensure_ascii=False))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
