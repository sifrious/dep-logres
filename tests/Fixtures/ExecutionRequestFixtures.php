<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\DeliveryChannel;
use Sifrious\Logres\ExecutionAttachment;
use Sifrious\Logres\ExecutionConstraints;
use Sifrious\Logres\ExecutionContext;
use Sifrious\Logres\ExecutionPermissions;
use Sifrious\Logres\ExecutionRequest;
use Sifrious\Logres\ExecutionRequestId;
use Sifrious\Logres\RequesterIdentity;

final class ExecutionRequestFixtures
{
    public static function accepted(): ExecutionRequest
    {
        return new ExecutionRequest(
            id: new ExecutionRequestId('request:accepted-fixture'),
            originalPrompt: " Preserve these bytes.\n",
            context: new ExecutionContext('project:atlas', 'repository:atlas-api'),
            desiredResult: 'A verified parser fix.',
            attachments: [new ExecutionAttachment('artifact:parser-log', 'parser.log')],
            constraints: new ExecutionConstraints(900, ['/workspace/atlas-api']),
            permissions: new ExecutionPermissions(true, true, false),
            requester: new RequesterIdentity('user:fixture', 'Fixture User'),
            channel: DeliveryChannel::Web,
        );
    }

    public static function rejected(): ExecutionRequest
    {
        return new ExecutionRequest(
            id: new ExecutionRequestId('request:rejected-fixture'),
            originalPrompt: 'Run it.',
            context: new ExecutionContext(null, null),
            desiredResult: '',
            attachments: [new ExecutionAttachment('https://example.test/private', '')],
            constraints: new ExecutionConstraints(0, ['/workspace/atlas-api']),
            permissions: new ExecutionPermissions(false, false, true),
            requester: new RequesterIdentity('', ''),
            channel: DeliveryChannel::Web,
        );
    }
}
