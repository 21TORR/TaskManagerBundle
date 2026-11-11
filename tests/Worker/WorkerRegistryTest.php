<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Worker;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Torr\TaskManager\Worker\WorkerInfo;
use Torr\TaskManager\Worker\WorkerRegistry;

use function Symfony\Component\Clock\now;

/**
 * @internal
 */
final class WorkerRegistryTest extends TestCase
{
	use ClockSensitiveTrait;

	// region Helpers
	private function createWorker (
		string $id,
		bool $isBusy = false,
		string $hostname = "other-host",
		int $pid = 1,
	) : WorkerInfo
	{
		return new WorkerInfo(
			id: $id,
			hostname: $hostname,
			pid: $pid,
			transports: ["app"],
			queues: null,
			startedAt: now(),
			lastSeenAt: now(),
			isBusy: $isBusy,
		);
	}

	/**
	 * @return list<string>
	 */
	private function getIds (WorkerRegistry $registry) : array
	{
		return array_map(
			static fn (WorkerInfo $worker) => $worker->id,
			$registry->getWorkers(),
		);
	}
	// endregion

	public function testSaveAndRemove () : void
	{
		$registry = new WorkerRegistry(new ArrayAdapter());

		$registry->save($this->createWorker("a"));
		$registry->save($this->createWorker("b"));
		self::assertEqualsCanonicalizing(["a", "b"], $this->getIds($registry));

		$registry->remove("a");
		self::assertSame(["b"], $this->getIds($registry));
	}

	public function testUpdatingAWorkerKeepsASingleEntry () : void
	{
		$registry = new WorkerRegistry(new ArrayAdapter());

		$registry->save($this->createWorker("a"));
		$registry->save($this->createWorker("a", isBusy: true));

		$workers = $registry->getWorkers();
		self::assertCount(1, $workers);
		self::assertTrue($workers[0]->isBusy);
	}

	public function testWorkerLostFromTheIndexIsReAddedOnTheNextHeartbeat () : void
	{
		$cache = new ArrayAdapter();
		$registry = new WorkerRegistry($cache);

		$registry->save($this->createWorker("a"));
		// simulate a concurrent write that overwrote the index
		$cache->deleteItem("torr.task_manager.worker_ids");
		self::assertSame([], $this->getIds($registry));

		$registry->save($this->createWorker("a"));
		self::assertSame(["a"], $this->getIds($registry));
	}

	public function testOutdatedIdleWorkerIsIgnored () : void
	{
		self::mockTime("2026-06-16 12:00:00");
		$registry = new WorkerRegistry(new ArrayAdapter());
		$registry->save($this->createWorker("idle"));
		$registry->save($this->createWorker("busy", isBusy: true));

		self::mockTime("2026-06-16 12:10:00");
		self::assertSame(["busy"], $this->getIds($registry));
	}

	public function testWorkerOnThisHostWithoutProcessIsIgnored () : void
	{
		if (!\function_exists("posix_kill") || false === gethostname())
		{
			self::markTestSkipped("Requires the posix extension.");
		}

		$registry = new WorkerRegistry(new ArrayAdapter());
		$registry->save($this->createWorker("running", hostname: gethostname(), pid: (int) getmypid()));
		// PIDs are limited to 99999 / 4194304 on macOS / Linux
		$registry->save($this->createWorker("crashed", hostname: gethostname(), pid: 2_000_000_000));

		self::assertSame(["running"], $this->getIds($registry));
	}
}
