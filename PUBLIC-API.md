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
- `TaskId`
- `TaskPlanId`
- `TaskStatus`
- `TaskAction`
- `TaskReadiness`
- `TaskStartAuthority`
- `TranslatedTask`
- `TaskPlan`
- `TaskPlanFailure`
- `TaskPlanValidator`
- `TaskPlanningStatus`
- `TaskPlanningResult`
- `TaskPlanner`
- `DeterministicTaskPlanner`
- `TaskPlanReadModel`
- `TaskPlanStore`
- `InvalidTaskTransition`
- `TaskPromptId`
- `TaskPromptContextSource`
- `TaskPromptSkill`
- `TaskPromptTool`
- `TaskPromptPrerequisiteOutput`
- `TaskPromptResultContract`
- `TaskPromptReportingContract`
- `TaskPromptCompilationInput`
- `TaskPrompt`
- `TaskPromptCompiler`
- `TaskPromptStore`
- `TaskPromptReadModel`
- `ExecutionTargetId`
- `ExecutionTargetRequirements`
- `ExecutionTargetCandidate`
- `ExecutionTargetAuthorization`
- `ExecutionTargetSelection`
- `ExecutionTargetSelector`
- `ExecutionTargetCatalog`
- `ExecutionTargetStore`
- `ExecutionTargetReadModel`
- `ExecutionTargetCatalogReadModel`
- `TargetAvailability`
- `TargetHealth`
- `TargetSelectionReason`
- `TargetSelectionStatus`
- `TargetSelectionFailure`
- `TargetSelectionResult`
- `RepositoryIdentity`
- `WorkspaceAuthority`
- `WorkspacePath`
- `ExecutionGrant`
- `DispatchAuthorizationSnapshot`
- `DispatchAuthorizationFailure`
- `DispatchAuthorizationDecision`
- `DispatchAuthorizationPolicy`
- `RunId`
- `RunProvenance`
- `Run`
- `RunStore`
- `RunIdentityConflict`
- `RunIdentityReadModel`
- `RunnerLeaseStatus`
- `RunnerLease`
- `RunnerLeaseStore`
- `StaleRunnerLease`
- `RunExecutionRecord`
- `RunExecutionRecordStore`
- `RunExecutionReadModel`
- `ProviderExecutionId`
- `ProviderAcknowledgement`
- `ProviderBindingStatus`
- `ProviderBindingOutcome`
- `ProviderBindingFailure`
- `ProviderBindingResult`
- `ProviderExecutionBinder`
- `ProviderLookupStatus`
- `ProviderExecutionLookupResult`
- `ProviderExecutionLookup`
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
