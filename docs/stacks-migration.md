# Stacks identity migration

`StacksExecutionContext` is canonical for new dispatches. It carries the published
`stacks.workspace-reference.v1` identity and the immutable
`stacks.execution-provenance.v1` snapshot captured before dispatch.

The existing `WorkspaceAuthority`, `RepositoryIdentity`, and `WorkspacePath`
fields remain as a compatibility surface while persisted records and callers are
migrated. When a Stacks context is supplied, Logres requires those legacy values
to match it. Paths remain grant-checked execution evidence; they are never used
as workspace identity.

Historical records store `StacksExecutionContext::toArray()`. Read models render
that snapshot directly and do not resolve live Stacks state or access Stacks
SQLite. Moved paths, unavailable/deleted checkouts, advanced HEADs, and changed
remotes therefore do not rewrite what was true when the Run began.

New requests use `ExecutionIdentityResolver`, a port over the owning Stacks
registry. Resolution fails closed for zero or multiple matches, repository
authority mismatches, or when the observed path does not resolve to the selected
workspace. The frozen `logres.execution-identity.v1` record also includes the
requested base revision, observed starting revision, checkout/worktree
observation, capability-snapshot version, and selected execution target.
Resulting revision and diff identity are appended together to a copied immutable
snapshot.

Persisted records are classified as:

- `complete`: usable for dispatch.
- `legacy_stacks_v1`: has the earlier Stacks snapshot but lacks the complete
  approval/dispatch evidence; readable, but not dispatchable.
- `legacy_missing`: predates workspace provenance. Its workspace ID remains
  `null`; migration must not infer it from a path, repository, or current Stacks
  state.

Filesystem paths are retained only inside execution-time evidence and grant
checks. Canonical identity is derived from Stacks workspace, repository,
checkout, revision, capability, and target facts, never from a path.
