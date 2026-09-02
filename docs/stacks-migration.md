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
