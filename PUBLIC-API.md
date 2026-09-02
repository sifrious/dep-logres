# Logres Package Public API

The package is pre-release. These names define the current consumer surface; semantic-version guarantees begin with the first tagged release.

## Execution model

- `ExecutionRequest`
- `DeliberationOrigin`
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
- `DirectTaskPlanner`
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
- `ExecutionTargetEvaluation`
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
- `PreDispatchValidationFailure`
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
- `ExecutionState`
- `ExecutionStateStore`
- `ExecutionStateService`
- `ExecutionStateReadModel`
- `ExecutionStateDetails`
- `ExecutionStateRejected`
- `ExecutionStateRejectionReason`
- `ExecutionAttempt`
- `AttemptId`
- `AttemptStatus`
- `ExecutionLease`
- `LeaseId`
- `LeaseToken`
- `LeaseStatus`
- `ExecutionNodeRef`
- `FailureClassification`
- `RecoveryAction`
- `RecoveryRecord`
- `RetryPolicy`
- `CancellationAuthorization`
- `CancellationIntent`
- `CancellationKind`
- `CancellationStatus`
- `RunResult`
- `RunResultStore`
- `RunResultHistorian`
- `RunResultReadModel`
- `VerificationStatus`
- `FinalizationStatus`
- `RunEvidence`
- `PreflightSnapshot`
- `PostflightReport`
- `PostflightResultAssembler`
- `TurnRunner`

## Runner boundary

- `RunnerIdentity`
- `PlatformIdentity`
- `CapabilitySnapshot`
- `RunnerAvailability`
- `CurrentWorkload`
- `RunnerDescriptor`
- `RunnerCompatibilityRequirements`
- `RunnerCompatibilityFailure`
- `RunnerCompatibility`
- `ExecutionEnvelope`
- `EnvelopeAuthenticator`
- `RunnerAuthorization`
- `RunnerWorkspace`
- `RunnerLifecycle`
- `ExecutionStateRunnerLifecycle`
- `RunnerRejectionReason`
- `RunnerAcceptance`
- `RuntimeRequest`
- `RuntimeResult`
- `RunnerRuntime`
- `RunnerRuntimeObserver`
- `RunnerEventType`
- `RunnerEvent`
- `RunnerEventSink`
- `RunnerTerminalStatus`
- `RunnerTerminalResult`
- `RunnerLocalStage`
- `RunnerLocalRecord`
- `RunnerLocalStateStore`
- `RunnerExecutionOutcome`
- `SequencedRunnerObserver`
- `ExecutionRunner`

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

- `InvariantPreflightPhase`
- `InvariantBeforeTurnHandler`
- `InvariantPreflight`
- `InvariantFinalizationPhase`
- `InvariantAfterTurnHandler`
- `InvariantFinalization`
- `RequiredVerificationOutcome`
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
