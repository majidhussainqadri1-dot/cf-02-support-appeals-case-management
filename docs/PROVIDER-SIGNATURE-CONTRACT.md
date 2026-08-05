# CF-02 Signed Provider Request Contract

## Version

C2-M hardened contract for the `1.0.0-rc.6` candidate. This is a pre-staging integration contract; it does not prove that any real provider has accepted it.

## Required headers

- `X-CF02-Key-Id`: 3–64 characters from `A-Z a-z 0-9 . _ : -`.
- `X-CF02-Timestamp`: ten-digit Unix time, accepted only within ±300 seconds.
- `X-CF02-Signature`: lowercase hexadecimal HMAC-SHA256.

## Canonical signature envelope

The sender signs the exact UTF-8 byte sequence below. Newline means the single LF byte `0x0A`.

```text
purpose + "\n" +
key_id + "\n" +
UPPERCASE_HTTP_METHOD + "\n" +
EXACT_WORDPRESS_REST_ROUTE + "\n" +
unix_timestamp + "\n" +
EXACT_RAW_REQUEST_BODY
```

The HMAC algorithm is SHA-256 and the secret is resolved by the approved key ID and endpoint purpose. A signature for one purpose, route, method or key ID is invalid for every other context.

## Body and replay limits

- Body must be a JSON object, not a list or scalar.
- Raw body limit: 262,144 bytes.
- Timestamp replay window: five minutes.
- Domain idempotency and terminal-state laws remain mandatory after cryptographic verification.
- Provider-controlled queue, priority, severity, authorization or native outcome authority is rejected; CF-02 and the native owner derive those facts under their canonical policies.

## Public-error boundary

Rejected provider calls receive a generic localized error plus an opaque trace ID. Detailed exception material is emitted only to the private diagnostic action `cf02_provider_request_failed`.
