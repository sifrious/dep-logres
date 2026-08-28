# Task plans

## Problem

An execution request is too broad to dispatch safely. It must become small canonical work units with stable identity, explicit evidence, dependency order, readiness, and package-owned transitions that remain valid outside any queue or UI.

## First useful outcome

The deterministic planner translates one accepted execution request into four tasks: inspect and define can begin in parallel, implementation remains blocked by both, and verification remains blocked by implementation. A replaceable planner can produce another valid plan through the same contract.

## State model

- `planned`: persisted but not started; package readiness is either `ready` or `blocked`.
- `running`: started with explicit manual or automatic authority.
- `waiting_for_input`: paused until a caller chooses retry or cancel.
- `failed`: exposes retry, re-plan, and cancel actions.
- `succeeded`: terminal and satisfies dependents.
- `skipped`: terminal and satisfies dependents by explicit omission.
- `canceled`: terminal and does not satisfy dependents.

Only the package exposes available actions. A host may render or invoke them but does not derive them.

## Graph rules

- Task identities are unique within a plan.
- Every task references the plan's execution request.
- Dependencies resolve within the plan and cannot refer to the same task.
- The graph is acyclic.
- A task is ready only when every dependency succeeded or was skipped.
- Re-planning creates a new plan identity linked to the prior plan.

## Boundary

Logres owns task and plan values, deterministic planning results, validation, readiness, action availability, transitions, retry, skip behavior, concurrency declarations, human-input capability, and re-planning lineage. Hosts own persistence adapters, queues, agents, authorization integration, and presentation.

## Non-goals

Provider selection, prompt compilation, queue dispatch, process execution, acceptance verification, and external issue export remain later capabilities.
