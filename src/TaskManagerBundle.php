<?php declare(strict_types=1);

namespace Torr\TaskManager;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Torr\TaskManager\DependencyInjection\AutoDetectFailureTransportsCompilerPass;
use Torr\TaskManager\DependencyInjection\FindTaskClassesCompilerPass;
use Torr\TaskManager\DependencyInjection\TaskManagerBundleExtension;
use Torr\TaskManager\Task\Task;

final class TaskManagerBundle extends Bundle
{
	/**
	 * @inheritDoc
	 */
	public function getContainerExtension () : ExtensionInterface
	{
		return new TaskManagerBundleExtension($this, "task_manager");
	}

	/**
	 * @inheritDoc
	 */
	public function build (ContainerBuilder $container) : void
	{
		$container
			->addCompilerPass(new AutoDetectFailureTransportsCompilerPass())
			->addCompilerPass(new FindTaskClassesCompilerPass());

		$container->registerForAutoconfiguration(Task::class)
			->addTag("task-manager.task");
	}

	/**
	 * @inheritDoc
	 */
	public function getPath () : string
	{
		return \dirname(__DIR__);
	}
}
