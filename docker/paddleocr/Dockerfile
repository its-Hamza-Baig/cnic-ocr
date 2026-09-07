FROM python:3.11-slim-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgomp1 \
        libglib2.0-0 \
        libgl1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir paddlepaddle==3.2.0 -i https://www.paddlepaddle.org.cn/packages/stable/cpu/ \
    && pip install --no-cache-dir -r /app/requirements.txt

COPY server.py /app/server.py

EXPOSE 8868
ENV PADDLEOCR_LANGS=en,ar \
    FLAGS_use_mkldnn=0 \
    OMP_NUM_THREADS=1 \
    MKL_NUM_THREADS=1 \
    PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK=True

CMD ["python", "/app/server.py"]
