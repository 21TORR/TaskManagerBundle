<?php declare(strict_types=1);

namespace Torr\TaskManager\Schedule;

use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final
 *
 * @api
 */
readonly class TaskScheduler
{
	private const string LOCK_KEY = "task-manager.scheduler.lock";

	public function __construct (
		private CacheInterface $cache,
		private LockFactory $lockFactory,
	) {}

	/**
	 *
	 */
	public function createSchedule () : InternalTaskManagerSchedule
	{
		return new InternalTaskManagerSchedule(
			$this->cache,
			$this->lockFactory->createLock(self::LOCK_KEY),
		);
	}
}
