<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\TaskPrompt;
use Sifrious\Logres\TaskPromptCompiler;
use Sifrious\Logres\TaskPromptId;
use Sifrious\Logres\TaskPromptReadModel;
use Sifrious\Logres\Tests\Fixtures\InMemoryTaskPromptStore;
use Sifrious\Logres\Tests\Fixtures\TaskPromptFixtures;

final class TaskPromptTest extends TestCase
{
    #[Test]
    public function identical_declared_inputs_compile_to_identical_prompt_bytes(): void
    {
        $compiler = new TaskPromptCompiler;

        $first = $compiler->compile(TaskPromptFixtures::input());
        $second = $compiler->compile(TaskPromptFixtures::input());

        self::assertSame($first->compiledPrompt, $second->compiledPrompt);
        self::assertSame($first->inputHash, $second->inputHash);
        self::assertSame($first->provenanceHash, $second->provenanceHash);
        self::assertSame('prompt:task:inspect:v1', $first->id->value);
        self::assertStringContainsString('Build the smallest coherent capability.', $first->compiledPrompt);
    }

    #[Test]
    public function unchanged_regeneration_returns_the_frozen_existing_version(): void
    {
        $compiler = new TaskPromptCompiler;
        $first = $compiler->compile(TaskPromptFixtures::input());

        self::assertSame($first, $compiler->compile(TaskPromptFixtures::input(), $first));
    }

    #[Test]
    public function one_changed_declared_input_creates_a_new_linked_version(): void
    {
        $compiler = new TaskPromptCompiler;
        $first = $compiler->compile(TaskPromptFixtures::input());
        $firstBytes = $first->compiledPrompt;
        $second = $compiler->compile(TaskPromptFixtures::input(' Use semantic HTML.'), $first);

        self::assertSame(2, $second->version);
        self::assertSame('prompt:task:inspect:v2', $second->id->value);
        self::assertSame($first->id->value, $second->previousPromptId?->value);
        self::assertNotSame($first->compiledPrompt, $second->compiledPrompt);
        self::assertNotSame($first->inputHash, $second->inputHash);
        self::assertSame($firstBytes, $first->compiledPrompt);
    }

    #[Test]
    public function store_and_read_model_preserve_every_version_and_disclosure_section(): void
    {
        $compiler = new TaskPromptCompiler;
        $store = new InMemoryTaskPromptStore;
        $first = $compiler->compile(TaskPromptFixtures::input());
        $second = $compiler->compile(TaskPromptFixtures::input(' Use semantic HTML.'), $first);
        $store->save($first);
        $store->save($second);
        $model = TaskPromptReadModel::fromVersions($store->versionsForTask($first->taskId));

        self::assertSame($second, $store->latestForTask($first->taskId));
        self::assertSame($first, $store->find($first->id));
        self::assertSame(2, $model->version);
        self::assertCount(2, $model->versions);
        self::assertSame('file:AGENTS.md', $model->contextSources[0]['id']);
        self::assertSame('landing-research', $model->skills[0]['id']);
        self::assertSame('filesystem', $model->tools[0]['id']);
        self::assertSame(['filesystem:read'], $model->allowedOperations);
        self::assertSame(['summary', 'artifacts', 'evidence'], $model->resultContract['required_fields']);
        self::assertSame('request_input', $model->reportingContract['input_request_method']);
    }

    #[Test]
    public function prompt_identity_version_and_lineage_must_agree(): void
    {
        $compiled = (new TaskPromptCompiler)->compile(TaskPromptFixtures::input());

        $this->expectException(\InvalidArgumentException::class);

        new TaskPrompt(
            id: new TaskPromptId('prompt:task:inspect:v2'),
            taskId: $compiled->taskId,
            requestId: $compiled->requestId,
            version: 2,
            compilerVersion: $compiled->compilerVersion,
            compiledPrompt: $compiled->compiledPrompt,
            inputHash: $compiled->inputHash,
            provenanceHash: $compiled->provenanceHash,
            input: $compiled->input,
        );
    }
}
