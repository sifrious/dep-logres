# Owned Diff

This package starts from the smallest Composer library shape: one PSR-4 namespace, one test suite, and no runtime dependency beyond PHP.

## `phpunit/phpunit ^12.5` — 2026-08-27, LOG-008

SEAM: borrowed — maintained by Sebastian Bergmann and PHPUnit contributors; 25 installed packages, 24 transitive.

PAYS WHEN: package behavior is verified without authoring a test runner or coupling tests to the Laravel application.

CHARGES WHEN: the 24-package development tree produces upgrade or supply-chain churn, or PHPUnit raises its PHP floor beyond the package support window; removal is confined to the package test suite.

TRIGGER: the package must prove its public contracts independently of Laravel before the application consumes them.

Signals: 12.5.33 was released in August 2026; the repository reports active 12.x and 13.x release lines, about 20,000 stars, and 24 open issues. The transitive count is justified because a maintained, independent test runner replaces an authored runner and its usage is confined to `tests/`.

## `phpstan/phpstan ^2.2` — 2026-08-27, LOG-040

SEAM: borrowed — maintained by Ondřej Mirtes, Markus Staab, Vincent Langlet, and contributors; one installed package and zero transitive packages.

PAYS WHEN: package contracts receive static type and control-flow checks without coupling quality gates to the Laravel application or authoring an analyzer.

CHARGES WHEN: analysis configuration is weakened to suppress real defects or a major release makes current source patterns invalid; removal is confined to one Composer script and one configuration file.

TRIGGER: Package v0.1 requires an independent static-analysis gate after its public execution contracts became executable.

Signals: 2.2.9 was released on 2026-08-22; Packagist reports active 2.1 and 2.2 lines, about 390 million installs, three listed maintainers, and zero runtime dependencies beyond PHP.

## `HarnessInterface`, `AbstractHarness`, and `HarnessRegistry` — 2026-08-27, LOG-019–LOG-021

SEAM: authored substitution boundary — consumers depend on `HarnessInterface`; `AbstractHarness` owns only stable identity, capability access, request targeting, and handle provenance shared by two concrete fixture harnesses.

PAYS WHEN: Codex, Claude, Amp, Grok, and non-CLI harnesses remain selectable by stable ID while shared invariant checks have one implementation.

CHARGES WHEN: the abstract class accumulates provider transport or lifecycle policy that one adapter must override, or registry identity becomes coupled to display configuration.

TRIGGER: two passing fixture harnesses required the same identity, capability, request-target, and handle-provenance behavior before any provider adapter was introduced.

## before-turn and after-turn pipelines — 2026-08-27, LOG-025–LOG-030

SEAM: authored composition boundary — small handlers transform provider-neutral context before execution and validate or transform a terminal result afterward.

PAYS WHEN: multiple installed skills and invariant checks compose in declared order without entering provider adapters or the Laravel application.

CHARGES WHEN: handlers gain infrastructure access, ordering becomes implicit, or the pipelines become a second dependency-injection container.

TRIGGER: two fixture handlers in each lifecycle phase must run once in exact order around the same TurnRunner acceptance path.

## `LoopComposition` — 2026-09-04, MME-2273

SEAM: authored composition boundary — a side-effect-free provider-neutral value joins existing deliberation, plan, task, handoff, external work, Run, result, verification, and evidence objects without replacing their owners.

PAYS WHEN: native PHP and later MCP/application adapters can inspect the same causal Loop, make evidence-gated determinations, and re-enter the owning task without inventing identity or lifecycle state.

CHARGES WHEN: the projection starts mutating package aggregates, serializing MCP resources, invoking providers, authoring clarification, or persisting a generic Loop record; those are explicit removal/refactoring triggers.

TRIGGER: MME-2273 requires zero-, one-, and multi-ticket planner-to-validation paths to share one replay-safe domain composition before MME-2272 defines its MCP representation.
