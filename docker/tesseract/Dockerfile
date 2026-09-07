FROM debian:bookworm-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        tesseract-ocr \
        tesseract-ocr-eng \
        tesseract-ocr-urd \
    && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["tesseract"]
