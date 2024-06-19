<?php declare(strict_types=1);

namespace Torr\TaskManager\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Log\Task\CleanOutdatedLogsTask;

final readonly class TaskIntegrationListener
{
	#[AsEventListener]
	public function onRegisterTasks (RegisterTasksEvent $event) : void
	{
		$event->register(new CleanOutdatedLogsTask());
	}
}
