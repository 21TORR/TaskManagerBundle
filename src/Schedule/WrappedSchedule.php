<?php declare(strict_types=1);

namespace Torr\TaskManager\Schedule;

use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Torr\TaskManager\Task\DispatchAfterRunTask\DispatchAfterRunTask;
use Torr\TaskManager\Task\Task;

/**
 * Wrapper around Symfony's Schedule with sensible defaults
 *
 * @final
 *
 * @internal
 */
readonly class WrappedSchedule implements ScheduleProviderInterface
{
	/**
	 *
	 */
	private Schedule $schedule;

	/**
	 */
	public function __construct (
		CacheInterface $cache,
		LockInterface $lock,
	)
	{
		$this->schedule = new Schedule()
			->lock($lock)
			->stateful($cache)
			->processOnlyLastMissedRun(true);
	}

	/**
	 * Adds a cron task
	 */
	public function cron (
		string $cronExpression,
		Task $task,
		\DateTimeZone|string|null $timezone = null,
	) : static
	{
		$this->schedule->add(RecurringMessage::cron(
			$cronExpression,
			new DispatchAfterRunTask($task),
			$timezone,
		));

		return $this;
	}

	/**
	 * Adds a cron task
	 */
	public function every (
		string|int|\DateInterval $frequency,
		Task $task,
		string|\DateTimeImmutable|null $from = null,
		string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
	) : static
	{
		$this->schedule->add(RecurringMessage::every(
			$frequency,
			new DispatchAfterRunTask($task),
			$from,
			$until,
		));

		return $this;
	}

	/**
	 *
	 */
	#[\Override]
	public function getSchedule () : Schedule
	{
		dump("get internal schedule");
		return $this->schedule->getSchedule();
	}
}
