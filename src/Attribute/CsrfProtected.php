<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * @author depthbomb
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class CsrfProtected
{
    /** @var list<string> */
    public readonly array $methods;

    /**
     * @param list<string> $methods Empty to validate every HTTP method.
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly ?string $tokenField = '_csrf_token',
        public readonly ?string $queryParameter = 'token',
        public readonly ?string $header = 'X-Csrf-Token',
        array $methods = [],
    )
    {
        if ($tokenField === '' || $queryParameter === '' || $header === '')
        {
            throw new InvalidArgumentException('Token source names must be non-empty strings or null.');
        }

        if ($methods === [])
        {
            $this->methods = [];
            return;
        }

        $normalizedMethods = [];
        foreach ($methods as $method)
        {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $method) !== 1)
            {
                throw new InvalidArgumentException('Each HTTP method must be a non-empty HTTP token. Supply multiple methods as separate array entries.');
            }

            $normalizedMethods[] = strtoupper($method);
        }

        $this->methods = array_values(array_unique($normalizedMethods));
    }
}
