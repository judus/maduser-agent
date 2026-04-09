# maduser/agent Architecture

## Purpose

`maduser/agent` is the generic agentic runtime layer.

It should unify the reusable workflow-backed runtime concerns currently spread
across:

- `alix-cli-app/src/Agent`
- `alix-cli-app/src/Alix`
- `llm-client/src/Agent.php`
- `llm-client/src/AgentResponse.php`
- `llm-client/src/Context/*`
- `llm-client/src/Tooling/ToolExecutionPipeline.php`

## Boundaries

### In scope

- public `Agent` entrypoint
- workflow-backed execution
- runtime context objects
- predefined simple workflows
- orchestration of LLM calls and real tool execution

### Out of scope

- provider/client transport internals
- Laravel service providers and jobs
- product-specific prompts, state, scorecards, and business rules

## Dependency Direction

Expected package stack:

1. `maduser/argon-workflows`
2. `maduser/llm-client`
3. `maduser/agent`
4. application/product packages

`maduser/agent` should depend on lower layers, never the other way around.

## Extraction Direction

First extraction target:

- package metadata and quality gates
- package-local docs
- minimal source/test skeleton

Second extraction target:

- move agent runtime classes out of `llm-client`
- define one or two built-in simple workflows
- keep `alix-cli-app` as a consumer of the new package


## Current runtime slice

`maduser/agent` now owns a workflow-backed `Agent` built on `maduser/argon-workflows`.
The package currently ships with one built-in default workflow:

- `default`: bootstrapping -> thinking -> processing -> storing -> summarizing

Where:

- `bootstrapping` loads conversation state and appends the new user turn
- `thinking` performs the LLM call and handles inline tool-call loops
- `processing` turns the raw LLM response into the assistant message/state mutation
- `storing` persists the current conversation state
- `summarizing` compresses old history into summary state when thresholds are exceeded
The current conversation state seam is:

- `ConversationState`
- `ConversationStateRepositoryInterface`
- `InMemoryConversationStateRepository`

That keeps workflow handlers repository-driven without forcing a framework or container.
