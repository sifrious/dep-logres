<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HumanInputRecord
{
    /** @param list<HumanInputEvent> $events */
    public function __construct(
        public HumanInputQuestion $question,
        public array $events,
        public ?HumanInputResponse $response = null,
        public ?HumanInputResolution $resolution = null,
        public ?DateTimeImmutable $resolvedAt = null,
    ) {
        if (($this->resolution === null) !== ($this->resolvedAt === null)) {
            throw new InvalidArgumentException('A human-input resolution and resolution time must be recorded together.');
        }
        if (($this->resolution === HumanInputResolution::Answered) !== ($this->response !== null)) {
            throw new InvalidArgumentException('Only an answered human-input record carries a response.');
        }
    }

    public static function open(HumanInputQuestion $question): self
    {
        return new self($question, [
            new HumanInputEvent('question:'.$question->id, 'requested', $question->requestedAt),
        ]);
    }

    public function isOutstanding(): bool
    {
        return $this->resolution === null;
    }

    public function deliver(string $deliveryId, string $channel, DateTimeImmutable $deliveredAt): self
    {
        if (trim($deliveryId) === '' || trim($channel) === '') {
            throw new InvalidArgumentException('Human-input delivery requires identity and channel.');
        }
        foreach ($this->events as $event) {
            if ($event->operationId === $deliveryId) {
                if ($event->type === 'delivered' && $event->channel === $channel) {
                    return $this;
                }
                throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InputQuestionConflict, 'A delivery identity cannot be reused with different evidence.');
            }
        }
        return new self(
            $this->question,
            [...$this->events, new HumanInputEvent($deliveryId, 'delivered', $deliveredAt, channel: $channel)],
            $this->response,
            $this->resolution,
            $this->resolvedAt,
        );
    }

    public function answer(HumanInputResponse $response): self
    {
        return new self(
            $this->question,
            [...$this->events, new HumanInputEvent($response->id, 'answered', $response->respondedAt, $response->responderId)],
            $response,
            HumanInputResolution::Answered,
            $response->respondedAt,
        );
    }

    public function close(string $operationId, HumanInputResolution $resolution, DateTimeImmutable $resolvedAt, ?string $actorId = null): self
    {
        return new self(
            $this->question,
            [...$this->events, new HumanInputEvent($operationId, $resolution->value, $resolvedAt, $actorId)],
            resolution: $resolution,
            resolvedAt: $resolvedAt,
        );
    }
}
