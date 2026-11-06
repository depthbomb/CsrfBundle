<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Depthbomb\CsrfBundle\DependencyInjection\CsrfBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * @inheritDoc
 * @author depthbomb
 * @since 1.0.0
 */
final class CsrfBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface)
        {
            $this->extension = new CsrfBundleExtension();
        }

        return $this->extension;
    }
}
