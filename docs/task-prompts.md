# Task prompt compilation

## Problem

An executing agent needs one inspectable envelope that says exactly what it received and why. Reconstructing that envelope from mutable host state after dispatch would make outcomes irreproducible and conceal changes in instructions, context, tools, or permissions.

## First useful outcome

One canonical translated task can be compiled twice from identical declared inputs with byte-identical output. Changing one source creates version two linked to version one while preserving the first prompt.

## Workflow

1. A host resolves concrete repository, workspace, and instruction content into package context-source values.
2. The host supplies the accepted request, canonical task, prerequisite outputs, selected skills and tools, allowed operations, and result and reporting contracts.
3. Logres canonicalizes the complete input and computes its SHA-256 identity.
4. Identical input under the same compiler returns the existing frozen version.
5. Changed input creates the next immutable version with previous-version lineage.
6. A host store appends the version and renders the package disclosure read model.

## Ownership boundary

Logres owns compilation inputs, context/source manifests, skill/tool selections, operation and permission disclosure, result and reporting contracts, deterministic bytes, compiler and input identities, provenance, lineage, persistence contract, and read model. Hosts resolve concrete content and credentials, persist versions through an adapter, and render the read model.

## Constraints

- Compilation has no framework or provider dependency.
- Source identifiers are stable logical references rather than required machine paths.
- Context content participates in the input hash and frozen prompt bytes.
- Version numbers advance only when declared input or compiler version changes.
- Dispatch, credential resolution, target selection, and task output production remain later capabilities.

## Failures

- Request and task identities must match.
- Project instructions and allowed operations must be explicit.
- Version advancement cannot cross task lineages.
- Empty disclosure contracts are rejected at construction.

## Acceptance evidence

- The same fixture compiles independently to identical bytes and hashes.
- Unchanged regeneration returns the existing object and version.
- A one-source content change creates a linked second version.
- The store fixture and read model retain and disclose both versions.
