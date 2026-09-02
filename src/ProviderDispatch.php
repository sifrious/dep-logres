<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ProviderDispatch
{
    public function dispatch(ProviderInvocationRequest $request): ProviderDispatchResult;
}
