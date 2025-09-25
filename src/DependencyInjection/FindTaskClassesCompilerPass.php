<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Torr\TaskManager\Config\BundleConfig;

/**
 * Compiler pass, that collects all tasks from the container that are from the app
 * itself (= in the App\ namespace).
 *
 * @final
 */
readonly class FindTaskClassesCompilerPass implements CompilerPassInterface
{
	/**
	 *
	 */
	#[\Override]
	public function process (ContainerBuilder $container) : void
	{
		$taskClasses = [];

		foreach ($container->findTaggedServiceIds("task-manager.task") as $taskServiceId => $config)
		{
			$definition = $container->getDefinition($taskServiceId);
			$taskFQCN = $definition->getClass();

			if (null !== $taskFQCN && str_starts_with($taskFQCN, "App\\"))
			{
				$taskClasses[] = $taskFQCN;
			}

			// Remove task service definition, as these must never be a service
			// in the actual runtime. We just use the mechanism to collect all
			// tasks in the app.
			$container->removeDefinition($taskServiceId);
		}

		$container->getDefinition(BundleConfig::class)
			->setArgument('$taskClasses', $taskClasses);
	}
}
