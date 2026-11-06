<?php

declare(strict_types=1);

namespace Depthbomb\CsrfBundle\Tests\Fixtures;

use Depthbomb\CsrfBundle\CsrfBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class TestKernel extends Kernel
{
    private string $temporaryDirectory;

    public function __construct(private readonly bool $stateless = false)
    {
        parent::__construct($stateless ? 'stateless' : 'test', true);
        $this->temporaryDirectory = sys_get_temp_dir().'/csrf-bundle-'.bin2hex(random_bytes(8));
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new CsrfBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $config = [
                'secret' => 'csrf-bundle-test-secret',
                'http_method_override' => false,
                'csrf_protection' => $this->stateless ? ['stateless_token_ids' => ['action']] : true,
                'session' => $this->stateless ? false : ['storage_factory_id' => 'session.storage.factory.mock_file'],
            ];

            if (Kernel::VERSION_ID < 80000) {
                $config['handle_all_throwables'] = true;
            }

            $container->loadFromExtension('framework', $config);
            $container->register('logger', \Psr\Log\NullLogger::class);
            $container->setAlias('test.csrf_token_manager', CsrfTokenManagerInterface::class)->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return $this->temporaryDirectory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->temporaryDirectory.'/log';
    }
}
