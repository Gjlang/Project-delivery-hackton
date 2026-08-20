FROM python:3.11-slim

WORKDIR /app

# Build deps for mysql-connector-python and sentence-transformers' native
# extensions -- removed from the final layer via apt cleanup to keep the
# image lean (sentence-transformers pulls in torch regardless, so this
# image is not tiny either way).
RUN apt-get update \
    && apt-get install -y --no-install-recommends gcc default-libmysqlclient-dev pkg-config \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .

# Install CPU-only torch first (sentence-transformers pulls it in
# transitively, and pip would otherwise grab the default CUDA build --
# several GB of GPU libraries this container never uses since Cloud Run has
# no GPU). Pinning it before the main install satisfies the dependency so
# pip doesn't replace it.
RUN pip install --no-cache-dir torch --index-url https://download.pytorch.org/whl/cpu
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# storage/documents is where uploaded files land at runtime -- created here
# so the app doesn't have to lazily create it under a read-only-ish layer.
RUN mkdir -p storage/documents

EXPOSE 8080

# main:app is the real production FastAPI app (mounts threads.router) --
# not graph/dev_entry.py, which is LangGraph Studio's own dev-only debugger
# and is never deployed.
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8080"]
