# Traefik security

The tournament router uses `public-api@file`: Cloudflare peer verification,
50 requests/second per visitor with burst 200, and security headers. Browser
challenges are deliberately absent to preserve live boards, WebSocket handshakes
and application requests. Limits apply to HTTP requests, not subsequent WebSocket
messages. Deploy homelab first. Existing Laravel login/join limits remain. Laravel trusts the Docker/LAN hop
and Cloudflare ranges explicitly, so those limits resolve the visitor from the
right of the forwarded chain instead of grouping visitors by Cloudflare edge
or accepting a forged leftmost address. Keep these ranges synchronized too.

Cloudflare must preserve `CF-Connecting-IP`; do not enable the Remove visitor IP
headers transform. For IPv6 visitor identities use Pseudo IPv4 Off or Add Header,
not Overwrite Headers. Peer allowlists must match https://www.cloudflare.com/ips/.
Direct origin/LAN requests to these public routers are rejected, even with forged
Cloudflare headers. Use the Cloudflare public hostname or a separate internal
route. Missing visitor headers share an empty-header rate bucket rather than
being assigned to a Cloudflare edge bucket.

This policy does not enable Authenticated Origin Pulls/mTLS; server certificates
and Cloudflare peer allowlists are distinct controls. Application authorization
remains necessary. Clients sharing a public IP also share a proxy rate budget.

Changes are prepared on a security branch to avoid automatically deploying to
production. Merge/deploy only after the shared homelab middlewares are available
(where used), then recreate the affected containers. Check normal browser/app
access, existing authentication and unexpected 403/429 responses. These are
request limits, not bandwidth or volumetric DDoS protection.
