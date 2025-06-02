<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 *
 */
final class TaskManagerBundleConfiguration implements ConfigurationInterface
{
	/**
	 * @inheritDoc
	 */
	public function getConfigTreeBuilder () : TreeBuilder
	{
		$treeBuilder = new TreeBuilder("task_manager");

		$treeBuilder->getRootNode()
			->children()
				->arrayNode("queues")
					->info("The list of queues to inspect. This list should be sorted by descending priority.")
					->scalarPrototype()->end()
				->end()
				->integerNode("log_ttl")
					->setDeprecated("21torr/task-manager", "2.2.0", "Use the `log.ttl` configuration instead.")
					->info("The max age of log entries, before they are automatically cleaned.")
					->defaultValue(28)
				->end()
				->arrayNode("log")
					->addDefaultsIfNotSet()
					->children()
						->integerNode("ttl")
							->defaultValue(28)
							->info("The max age of log entries, before they are automatically cleaned.")
						->end()
						->integerNode("max_entries")
							->defaultValue(1000)
							->info("The max number of log entries, before they are automatically cleaned.")
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}
