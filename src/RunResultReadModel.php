<?php
declare(strict_types=1);
namespace Sifrious\Logres;
final readonly class RunResultReadModel
{
    /** @param array<string, list<array{reference: string, observed_at: string}>> $evidence */
    public function __construct(
        public string $status,
        public ?string $providerClaim,
        public ?string $observedOutcome,
        public array $evidence,
        public string $stdout,
        public string $stderr,
        public ?int $exitCode,
        public ?string $reason,
        public string $verificationStatus,
        public string $finalizationStatus,
    ) {}

    public static function fromResult(RunResult $result): self
    {
        $evidence = [];
        foreach ($result->evidence as $item) {
            $evidence[$item->kind][] = [
                'reference' => $item->reference,
                'observed_at' => $item->observedAt,
            ];
        }
        ksort($evidence);

        return new self(
            status: $result->status->value,
            providerClaim: $result->agentClaim,
            observedOutcome: $result->observedOutcome,
            evidence: $evidence,
            stdout: $result->stdout,
            stderr: $result->stderr,
            exitCode: $result->exitCode,
            reason: $result->reason,
            verificationStatus: $result->verificationStatus->value,
            finalizationStatus: $result->finalizationStatus->value,
        );
    }
}
