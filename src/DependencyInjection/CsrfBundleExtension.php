<?php namespace Depthbomb\CsrfBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Depthbomb\CsrfBundle\EventListener\CsrfProtectedAttributeListener;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * @inheritDoc
 * @author depthbomb
 * @since 1.0.0
 */
class CsrfBundleExtension extends ConfigurableExtension implements ConfigurationInterface
{
    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->register('depthbomb_csrf_bundle.verify_route', CsrfProtectedAttributeListener::class)
            ->setArguments([new Reference(CsrfTokenManagerInterface::class)])
            ->addTag('kernel.event_subscriber');
    }

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('depthbomb_csrf_bundle');
    }
}
