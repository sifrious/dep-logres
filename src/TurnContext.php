<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class TurnContext
{
    public function __construct(
        public array $identity,
        public EnvironmentSnapshot $environment,
        public array $instructions = [],
        public array $skills = [],
        public array $tools = [],
    ) {}

    public function withInstructions(array $instructions): self
    {
        return new self($this->identity, $this->environment, $instructions, $this->skills, $this->tools);
    }

    public function withSkills(array $skills): self
    {
        return new self($this->identity, $this->environment, $this->instructions, $skills, $this->tools);
    }

    public function withTools(array $tools): self
    {
        return new self($this->identity, $this->environment, $this->instructions, $this->skills, $tools);
    }
}
