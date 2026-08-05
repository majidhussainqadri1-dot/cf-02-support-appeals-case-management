# Contributing

1. Work on a dedicated branch; do not commit runtime work directly to `main`.
2. Link every change to one or more `CF02-FR-*` requirement IDs.
3. Preserve canonical ownership; direct writes to another module's data are prohibited.
4. Test happy paths, authorization denial, replay/duplicate requests, stale versions, dependency failure and rollback behavior.
5. Run PHP syntax checks and `php -d zend.assertions=1 tests/run.php`.
6. Complete review and correction, then a separate fresh/adversarial review and correction before release.
7. Keep secrets and private operational material out of this public repository.
8. Do not describe code as staging-accepted, live or operational without evidence.
