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
					->info("The max age of log entries, before they are automatically cleaned.")
					->defaultValue(28)
				->end()
			->end();

		return $treeBuilder;
	}
}
