<?php declare(strict_types=1);

namespace Torr\TaskManager\Schedule;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Torr\TaskManager\Log\Task\CleanOutdatedLogsTask;

#[AsSchedule]
final readonly class TaskManagerSchedule implements ScheduleProviderInterface
{
	/**
	 *
	 */
	public function getSchedule () : Schedule
	{
		return (new Schedule())
			->with(
				RecurringMessage::cron(
					"#daily",
					new CleanOutdatedLogsTask(),
				),
			);
	}
}
