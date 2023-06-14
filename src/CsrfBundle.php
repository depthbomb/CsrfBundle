<?php namespace Depthbomb\CsrfBundle;

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
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null)
        {
            $this->extension = new CsrfBundleExtension();
        }

        return $this->extension;
    }
}
