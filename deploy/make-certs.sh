#!/usr/bin/env bash
# Makes sure certs/origin.key and certs/origin.pem exist, so nginx can always listen on 443.
#
#   * No key yet: generates one (ECDSA P-256).
#   * Key but no certificate: signs a self-signed one with it, and says so. Cloudflare accepts
#     that in "Full" mode; "Full (strict)" needs the Cloudflare Origin certificate instead.
#   * Both present: leaves them alone, so an installed Origin certificate is never replaced.
#
# To install the real certificate, put the PEM Cloudflare issues for certs/origin.csr at
# certs/origin.pem and reload nginx (see README, "HTTPS").
set -euo pipefail
cd "$(dirname "$0")"

umask 077
mkdir -p certs
key=certs/origin.key pem=certs/origin.pem csr=certs/origin.csr
hosts='DNS:mudarisy.com,DNS:*.mudarisy.com,DNS:localhost,DNS:*.mudarisy.localhost'

if [[ ! -f $key ]]; then
    openssl ecparam -name prime256v1 -genkey -noout -out "$key" 2>/dev/null
    echo "make-certs: generated $key"
fi
chmod 600 "$key"

if [[ ! -f $csr ]]; then
    openssl req -new -key "$key" -out "$csr" -subj "/CN=mudarisy.com" -addext "subjectAltName=$hosts" 2>/dev/null
fi

if [[ ! -f $pem ]]; then
    openssl req -x509 -key "$key" -out "$pem" -days 3650 -subj "/CN=mudarisy-origin-selfsigned" \
        -addext "subjectAltName=$hosts" 2>/dev/null
    chmod 644 "$pem"
    echo "make-certs: WARNING $pem is self-signed. Fine for Cloudflare 'Full'; install the Cloudflare" >&2
    echo "make-certs: Origin certificate for $csr to use 'Full (strict)' (README, \"HTTPS\")." >&2
fi
