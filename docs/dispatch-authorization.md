# Dispatch authorization

## Safety boundary

A selected target is not yet authorized to execute. `DispatchAuthorizationPolicy` requires a typed `ExecutionGrant` and factual host context before a local Run may enter `awaiting_acknowledgement`.

The policy checks:

- the Run has not already dispatched;
- grant actor and selected target identity;
- one unambiguous canonical repository identity from a remote, never a current directory;
- selected and granted workspace authority;
- an explicit normalized POSIX path contained by a non-root workspace grant;
- selected, requested, and granted environment;
- selected and granted runtime;
- every permission frozen from the task prompt;
- grant issue and expiry time;
- target observation freshness.

Target capabilities remain separate from authority. A capable target with no matching permission grant is denied. Manual target selection passes through this same dispatch policy.

## Workflow

1. The host discovers one canonical repository identity and maps the authenticated caller's bounded grant.
2. Logres returns an allowed decision with `DispatchAuthorizationSnapshot` or explicit failures.
3. `Run::authorized` freezes the snapshot after verifying it against immutable Run provenance.
4. `Run::awaitingAcknowledgement` rejects every Run without that snapshot.
5. The host persists the authorized Run before invoking the provider.

Failures include missing or ambiguous repository identity, repository mismatch, missing workspace path, path escape, actor or target mismatch, environment or runtime mismatch, missing permissions, stale grant, stale target observation, and an already-dispatched Run.

## Host boundary

The host owns authenticated identity mapping, repository remote discovery, filesystem observation, grant persistence, and provider invocation. It must not infer authority from a local path, target capability, default environment, or credential availability.
