FROM alpine:3.19

# Install dependencies
RUN apk add --no-cache \
    bash \
    curl \
    jq \
    git \
    file \
    openssl \
    python3 \
    py3-pip

# Install VeriBits CLI
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /github/workspace

ENTRYPOINT ["/entrypoint.sh"]
