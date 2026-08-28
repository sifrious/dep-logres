# Logres Package Public API

The package is pre-release. These names define the current consumer surface; semantic-version guarantees begin with the first tagged release.

## Execution model

- `ExecutionRequest`
- `ExecutionRequestId`
- `ExecutionContext`
- `ExecutionAttachment`
- `ExecutionConstraints`
- `ExecutionPermissions`
- `RequesterIdentity`
- `DeliveryChannel`
- `RequestRelationship`
- `SubmitExecutionRequest`
- `ExecutionRequestSubmission`
- `ExecutionRequestStore`
- `ExecutionRequestValidator`
- `ExecutionRequestFailure`
- `ExecutionRequestPersistenceFailure`
- `ExecutionRequestResult`
- `ExecutionRequestResultStatus`
- `ExecutionRequestReadModel`
- `Turn`
- `TurnContext`
- `EnvironmentSnapshot`
- `RunRequest`
- `RunStatus`
- `RunTransitionPolicy`
- `InvalidRunTransition`
- `RunResult`
- `TurnRunner`

## Harness substitution

- `HarnessInterface`
- `AbstractHarness`
- `HarnessRegistry`
- `HarnessCapability`
- `HarnessProbe`
- `HarnessHandle`
- `HarnessStatus`

Consumers depend on `HarnessInterface`. `AbstractHarness` is optional shared invariant plumbing; a non-CLI harness may implement the interface directly.

## Observation and records

- `ExecutionObserver`
- `ExecutionEvent`
- `ArtifactReference`

## Lifecycle composition

- `BeforeTurnHandler`
- `BeforeTurnPipeline`
- `AfterTurnHandler`
- `AfterTurnPipeline`
- `HumanGate`
- `NeedsInput`

## Skills and resources

- `SkillManifest`
- `InstalledSkill`
- `SkillCatalog`
- `SkillCatalogResult`
- `SkillConflict`
- `SkillDependencyGraph`
- `AlwaysOnSkillResolver`
- `PackageResourceResolver`

## Tools and authorization

- `ToolManifest`
- `ToolAuthorizationContext`
- `ToolAuthorizer`
