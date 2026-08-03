# Security Policy

Do not open a public issue containing credentials, tokens, identity evidence, patient information, payment information, private messages, production logs or exploit details.

Never commit passwords, OTPs, API keys, identity documents, clinical records, payment credentials, message bodies, private dossiers, database dumps, access logs or private incident runbooks.

The module is fail-closed, purpose-bound, least-privilege, version-aware and idempotent. Availability is not authorization. Every sensitive action must be revalidated against current native-owner state.
