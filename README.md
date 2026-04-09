# maduser/agent

`maduser/agent` is the workflow-backed agent runtime built on top of the lower-level
LLM client and workflow packages.

It sits above:

- `maduser/llm-client`
- `maduser/argon-workflows`

And below product-specific applications such as Companion AI.

## Intent

Agent should own:

- the public `Agent` entrypoint
- workflow-backed execution
- predefined simple workflows
- tool orchestration at the runtime level

It should not own:

- Laravel-specific wiring
- product-specific rules
- domain/business policies for one application

## Quality Gates

Use:

```bash
composer check
```

This runs:

- PHPUnit
- Psalm
- PHPCS


This package now ships with workflow-backed agent execution and two predefined workflows: `default` and `chat-with-summary`.
