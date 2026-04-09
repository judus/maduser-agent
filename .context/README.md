# maduser/agent Context

This folder is the working context area for `maduser/agent`.

Use it for:

- package architecture notes
- extraction plans from `alix-cli-app`
- runtime/workflow decisions
- quality and release notes

This package should become the workflow-backed agent runtime.

That means:

- it depends on lower-level reusable packages
- it exposes a stable `Agent` abstraction
- it does not contain Laravel or product-specific business logic
