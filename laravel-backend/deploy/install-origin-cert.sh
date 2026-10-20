#!/usr/bin/env bash
# Installs the Cloudflare Origin certificate issued for certs/origin.csr.
#   ./install-origin-cert.sh /path/to/certificate.pem
# Refuses a certificate that does not belong to certs/origin.key, keeps the previous one as
# certs/origin.pem.previous, and reloads nginx only if its configuration still passes.
set -euo pipefail
cd "$(dirname "$0")"

new="${1:?path to the PEM certificate Cloudflare issued}"
key=certs/origin.key pem=certs/origin.pem

[[ -f $key ]] || { echo "no $key: run ./make-certs.sh first" >&2; exit 1; }
openssl x509 -in "$new" -noout 2>/dev/null || { echo "$new is not a PEM certificate" >&2; exit 1; }

cert_pub="$(openssl x509 -in "$new" -noout -pubkey | openssl md5)"
key_pub="$(openssl pkey -in "$key" -pubout | openssl md5)"
if [[ $cert_pub != "$key_pub" ]]; then
    echo "this certificate was not issued for $key (public keys differ); nothing changed" >&2
    echo "it must be the one Cloudflare created from certs/origin.csr" >&2
    exit 1
fi

openssl x509 -in "$new" -noout -subject -issuer -enddate -ext subjectAltName | sed 's/^/  /'

cp "$pem" "$pem.previous"
install -m 644 "$new" "$pem"
if docker compose exec -T lb sh -c 'nginx -t -q && nginx -s reload'; then
    echo "installed; nginx reloaded"
else
    cp "$pem.previous" "$pem"
    echo "nginx rejected the certificate; restored the previous one" >&2
    exit 1
fi
