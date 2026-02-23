<?php declare(strict_types=1);

namespace Torr\TaskManager\Schedule;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Torr\TaskManager\Log\Task\CleanOutdatedLogsTask;

#[AsSchedule("task_manager")]
final readonly class TaskManagerInternalSchedule implements ScheduleProviderInterface
{
	/**
	 */
	public function __construct (
		private TaskScheduler $scheduler,
	) {}

	/**
	 *
	 */
	public function getSchedule () : Schedule
	{
		return $this->scheduler->createSchedule()
			->every("15 minutes", new CleanOutdatedLogsTask())
			->getSchedule();
	}
}
