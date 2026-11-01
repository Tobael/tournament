#!/usr/bin/env bash
set -euo pipefail
# Run from the deployment directory. rsync preserves secrets/ between releases.
test -s .env
install -d -m 700 secrets
if [ ! -s secrets/reverb.env ]; then
    (
        umask 077
        for name in REVERB_APP_KEY REVERB_APP_SECRET; do
            value="$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')"
            printf '%s=%s\n' "$name" "$value"
        done > secrets/reverb.env.new
        mv secrets/reverb.env.new secrets/reverb.env
    )
fi
# Explicit credentials in ENV_FILE take precedence. Otherwise reuse host secrets,
# even though the workflow replaces .env on every deployment.
for name in REVERB_APP_KEY REVERB_APP_SECRET; do
    if ! grep -Eq "^${name}=.+" .env; then
        printf '\n' >> .env
        grep -E "^${name}=" secrets/reverb.env >> .env
    fi
done
