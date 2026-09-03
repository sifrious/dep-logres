# Execution payload

`ExecutionEnvelope::requestPayload` contains the executable instruction and application-owned context for one immutable attempt. Logres validates the envelope's execution authority, then passes this payload unchanged to `RuntimeRequest`.

Logres does not interpret application planning records. A host may include stable plan, desired-state difference, architectural fragment, verification specification, and verification manifest references in the payload. Those references remain application policy. They do not grant execution authority and cannot replace the envelope's Run, Attempt, Lease, runner, workspace, repository, runtime, or authorization fields.

For the Burdgen and Prompt Harness integration, the smallest payload is:

```php
[
    'prompt' => 'the exact executable instruction',
    'planning' => [
        'plan_reference' => 'plan:example',
        'desired_state_difference_reference' => 'difference:example',
        'target_fragment_references' => ['fragment:example'],
        'verification_specification_reference' => 'verification:example',
        'verification_manifest_hash' => 'sha256 value',
    ],
]
```

Burdgen constructs and validates the `planning` value before dispatch. Prompt Harness passes it to the selected runtime adapter and records the returned execution evidence. Logres guarantees payload preservation but does not define this nested shape.

Changing the prompt, planning references, or verification hash requires a new immutable dispatch identity. Hosts must not mutate an accepted envelope in place.
