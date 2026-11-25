# Traefik and Anubis security

The browser frontend has `traefik.enable=false`. Public requests for
`mtg.betz.coffee` pass through the central homelab Anubis instance, targeting
`magic_tournament:80`. Deploy homelab first. A priority-900 file-provider guard
blocks fallback to old backend routers if Anubis is unavailable; Anubis has
priority 1000. Recreate this application's container to remove its old direct
router. Do not add a public backend fallback.

The tournament's GET WebSocket upgrades under `/app/` use a dedicated homelab
route with Cloudflare peer verification and API limits. Reverb retains channel
authentication. Other browser HTTP and AJAX requests use the Anubis cookie.

Cloudflare peer verification precedes trust in `CF-Connecting-IP`. No changes
to application data or secrets are needed. Deployment remains manual for this
security branch; verify the central proxy before deploying backend label changes.

Laravel canonicalizes its forwarded client address from the validated
`CF-Connecting-IP` before route limiters run. This preserves per-visitor limits
even when Anubis has flattened `X-Forwarded-For` to a Cloudflare edge address.
Malformed multi-address values are rejected; local requests without the header
retain their usual address handling.
