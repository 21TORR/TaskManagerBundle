<?php declare(strict_types=1);

namespace Torr\TaskManager\Worker;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\Clock\now;

/**
 * Keeps track of the running messenger workers.
 *
 * Every worker regularly writes a heartbeat (see `WorkerHeartbeatListener`), which is stored in the app cache.
 * Workers that stopped properly are removed directly, workers that crashed are removed once their heartbeat is outdated.
 *
 * Every worker is stored in its own cache entry, so that workers never overwrite each other's data. The only shared
 * entry is the index of all worker ids. It is updated without locking, so concurrent updates can lose an id. This is
 * self-healing though: every heartbeat checks that the worker is still in the index and re-adds it if not.
 *
 * As the data is stored in the app cache, only workers that share the same cache are visible.
 */
final readonly class WorkerRegistry
{
	private const string INDEX_KEY = "torr.task_manager.worker_ids";
	private const string WORKER_KEY_PATTERN = "torr.task_manager.worker.%s";

	/**
	 * Idle workers send a heartbeat after every loop iteration (throttled), so they are
	 * considered gone if they haven't reported in this time.
	 */
	public const int IDLE_TIMEOUT_SECONDS = 120;

	/**
	 * Busy workers can't send heartbeats while handling a (long-running) message, so they are only
	 * removed after this (much longer) timeout.
	 */
	public const int BUSY_TIMEOUT_SECONDS = 86_400;

	/**
	 */
	public function __construct (
		#[Autowire(service: "cache.app")]
		private CacheItemPoolInterface $cache,
	) {}

	/**
	 * Returns all running workers, sorted by host and start time.
	 *
	 * @return list<WorkerInfo>
	 */
	public function getWorkers () : array
	{
		$keys = array_map(
			$this->getWorkerKey(...),
			$this->loadIndex(),
		);

		$workers = [];

		foreach ($this->cache->getItems($keys) as $item)
		{
			$worker = $item->isHit() ? $item->get() : null;

			if ($worker instanceof WorkerInfo && $this->isAlive($worker))
			{
				$workers[] = $worker;
			}
		}

		usort(
			$workers,
			static fn (WorkerInfo $left, WorkerInfo $right) => [$left->hostname, $left->startedAt] <=> [$right->hostname, $right->startedAt],
		);

		return $workers;
	}

	/**
	 * Stores (or updates) the given worker.
	 */
	public function save (WorkerInfo $worker) : void
	{
		// the entry expires on its own, if the worker stops sending heartbeats (e.g. because it crashed)
		$item = $this->cache->getItem($this->getWorkerKey($worker->id));
		$item->set($worker);
		$item->expiresAfter($worker->isBusy ? self::BUSY_TIMEOUT_SECONDS : self::IDLE_TIMEOUT_SECONDS);
		$this->cache->save($item);

		// the entry is written before the index, so that the index never points to a worker that doesn't exist yet
		$ids = $this->loadIndex();

		if (!\in_array($worker->id, $ids, true))
		{
			$ids[] = $worker->id;
			$this->saveIndex($ids);
		}
	}

	/**
	 * Removes the worker with the given id
	 */
	public function remove (string $workerId) : void
	{
		$this->cache->deleteItem($this->getWorkerKey($workerId));

		$this->saveIndex(array_values(array_filter(
			$this->loadIndex(),
			static fn (string $id) => $id !== $workerId,
		)));
	}

	/**
	 * @return list<string>
	 */
	private function loadIndex () : array
	{
		$value = $this->cache->getItem(self::INDEX_KEY)->get();

		if (!\is_array($value))
		{
			return [];
		}

		return array_values(array_filter($value, \is_string(...)));
	}

	/**
	 * Stores the index. Ids of workers whose entry expired (= crashed workers) are pruned on the way.
	 *
	 * @param list<string> $ids
	 */
	private function saveIndex (array $ids) : void
	{
		$ids = array_values(array_filter(
			$ids,
			fn (string $id) => $this->cache->hasItem($this->getWorkerKey($id)),
		));

		$item = $this->cache->getItem(self::INDEX_KEY);
		$item->set($ids);
		$item->expiresAfter(self::BUSY_TIMEOUT_SECONDS);
		$this->cache->save($item);
	}

	/**
	 * Returns the cache key of the worker with the given id
	 */
	private function getWorkerKey (string $workerId) : string
	{
		return \sprintf(self::WORKER_KEY_PATTERN, $workerId);
	}

	/**
	 * Checks whether the worker is still running
	 */
	private function isAlive (WorkerInfo $worker) : bool
	{
		$timeout = $worker->isBusy
			? self::BUSY_TIMEOUT_SECONDS
			: self::IDLE_TIMEOUT_SECONDS;

		// the cache entry should already be expired, but not every cache adapter is exact about expiry
		if (now()->getTimestamp() - $worker->lastSeenAt->getTimestamp() > $timeout)
		{
			return false;
		}

		// if the worker runs on this host, we can directly check whether the process still exists
		if ($worker->hostname === gethostname() && \function_exists("posix_kill") && \function_exists("posix_get_last_error"))
		{
			// signal 0 doesn't send a signal, but only checks if the process exists.
			// EPERM (1) means that the process exists, but is owned by another user.
			return posix_kill($worker->pid, 0) || 1 === posix_get_last_error();
		}

		return true;
	}
}
