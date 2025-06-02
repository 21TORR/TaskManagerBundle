<?php declare(strict_types=1);

namespace Torr\TaskManager;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Torr\BundleHelpers\Bundle\ConfigurableBundleExtension;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\DependencyInjection\AutoDetectFailureTransportsCompilerInterface;
use Torr\TaskManager\DependencyInjection\TaskManagerBundleConfiguration;
use Torr\TaskManager\Log\LogCleaner;

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

				// if the new value was customized, use it. Otherwise keep using
				// the old value. If none is set, they use the same default, so everything
				// is fine.
				$logTtl = 28 !== $config["log"]["ttl"]
					? $config["log"]["ttl"]
					: $config["log_ttl"];

				$container->getDefinition(LogCleaner::class)
					->setArgument('$logTtlInDays', $logTtl)
					->setArgument('$logMaxEntries', $config["log"]["max_entries"]);
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
