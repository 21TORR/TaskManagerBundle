<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Task\Task;

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

		foreach ($container->getDefinitions() as $definition)
		{
			$taskFQCN = $definition->getClass();

			if (null !== $taskFQCN && str_starts_with($taskFQCN, "App\\"))
			{
				$reflection = $container->getReflectionClass($taskFQCN, false);

				if (null === $reflection || !$reflection->isSubclassOf(Task::class))
				{
					continue;
				}

				$taskClasses[] = $taskFQCN;

				// Ensure that our Task classes are removed from the container definitions. Depending on the way
				// the Tasks are registered (either via `AsMessage` attribute or via `messenger.yaml` config),
				// they'll be excluded automatically. This happens automatically when the `AsMessage` attribute is being used.
				// Here we're making sure that this also happens when using the YAML config file.
				$definition->addTag("container.excluded");
			}
		}

		$container->getDefinition(BundleConfig::class)
			->setArgument('$taskClasses', $taskClasses);
	}
}
