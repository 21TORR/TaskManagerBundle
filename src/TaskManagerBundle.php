<?php declare(strict_types=1);

namespace Torr\TaskManager;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Torr\BundleHelpers\Bundle\ConfigurableBundleExtension;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\DependencyInjection\AutoDetectFailureTransportsCompilerInterface;
use Torr\TaskManager\DependencyInjection\TaskManagerBundleConfiguration;

final class TaskManagerBundle extends Bundle
{
	/**
	 * @inheritDoc
	 */
	public function getContainerExtension () : ?ExtensionInterface
	{
		return new ConfigurableBundleExtension(
			$this,
			new TaskManagerBundleConfiguration(),
			static function (array $config, ContainerBuilder $container) : void
			{
				$container->getDefinition(BundleConfig::class)
					->setArgument('$sortedQueues', $config["queues"]);
			},
		);
	}

	/**
	 * @inheritDoc
	 */
	public function build (ContainerBuilder $container) : void
	{
		$container->addCompilerPass(new AutoDetectFailureTransportsCompilerInterface());
	}

	/**
	 * @inheritDoc
	 */
	public function getPath () : string
	{
		return \dirname(__DIR__);
	}

}
