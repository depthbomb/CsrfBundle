<?php namespace Depthbomb\CsrfBundle\Attribute;

use Attribute;

/**
 * @author depthbomb
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class CsrfProtected
{
    public function __construct(public readonly string $tokenId) {}
}
