# MME-10: Harness execution events and feedback

This package now models provider execution-event ingestion as a provider-neutral kernel.

## Scope implemented in this change

- Preserve the original provider envelope (`ProviderExecutionEventEnvelope`).
- Normalize provider event names into the `ExecutionEventType` taxonomy.
- Maintain stable event identity and deduplicate at-least-once delivery.
- Track ordering semantics:
  - reordered events
  - missing-sequence gaps
  - late events after a terminal disposition
- Guard run/task/attempt/invocation/provider-execution association to detect forged events.
- Attach typed references for tool, command, file, test, artifact, and input events.
- Provide presentation-neutral timeline projection (`ExecutionTimelineReadModel`).
- Project Run disposition from normalized terminal events.

## Normalized execution-event taxonomy

- `target_accepted`
- `agent_started`
- `agent_message`
- `progress`
- `tool_invoked`
- `tool_completed`
- `command_executed`
- `file_changed`
- `test_started`
- `test_completed`
- `artifact_produced`
- `warning`
- `input_requested`
- `task_completed`
- `task_failed`
- `task_timed_out`
- `task_cancelled`

## Out of scope

- Live provider callback/stream receiver transport.
- Provider auth and replay protections on real transport.
- UI/view rendering from provider events.
- Cloud/local runner ticket branches parked in the MME epic.
