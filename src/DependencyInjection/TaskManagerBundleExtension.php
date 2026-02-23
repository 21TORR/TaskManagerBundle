<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Torr\BundleHelpers\Bundle\BundleExtension;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Log\LogCleaner;
use Torr\TaskManager\Log\Task\CleanOutdatedLogsTask;
use Torr\TaskManager\Task\DispatchAfterRunTask\DispatchAfterRunTask;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @final
 */
class TaskManagerBundleExtension extends BundleExtension implements PrependExtensionInterface
{
	/**
	 *
	 */
	#[\Override]
	public function load (array $configs, ContainerBuilder $container) : void
	{
		parent::load($configs, $container);

		$config = $this->processConfiguration(new TaskManagerBundleConfiguration(), $configs);

		$container->getDefinition(BundleConfig::class)
			->setArgument('$sortedQueues', $config["queues"]);

		$container->getDefinition(LogCleaner::class)
			->setArgument('$logTtlInDays', $config["log"]["ttl"])
			->setArgument('$logMaxEntries', $config["log"]["max_entries"]);
	}

	/**
	 *
	 */
	#[\Override]
	public function prepend (ContainerBuilder $container) : void
	{
		$container->prependExtensionConfig("framework", [
			// We only register the sync task, so that the own tasks are worked on right away
			"messenger" => [
				"transports" => [
					TransportsHelper::INTERNAL_TRANSPORT_NAME => [
						"dsn" => 'sync://',
					],
				],
				"routing" => [
					DispatchAfterRunTask::class => TransportsHelper::INTERNAL_TRANSPORT_NAME,
					CleanOutdatedLogsTask::class => "app",
				],
			],
		]);
	}
}
