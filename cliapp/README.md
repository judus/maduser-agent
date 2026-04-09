# alix cliapp

This is a standalone CLI harness for manually probing the package API.

It is intentionally not part of `src/`.

Current state:
- neutral namespace
- package-specific probe commands for one-shot and interactive chat
- lightweight service wiring in `AppServices`

Use it to add short-lived probe commands while shaping the public API.
