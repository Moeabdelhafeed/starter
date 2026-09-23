# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_SANCTUM_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Obtain a token from <code>POST /api/login</code> or <code>POST /api/firebase-login</code> (returned as <code>token</code>), then send it as <code>Authorization: Bearer {token}</code>.
