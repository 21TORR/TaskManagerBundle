<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Log;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Log\LogCleaner;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class LogCleanerTest extends TestCase
{
	// region Helpers

	private function createTask () : Task
	{
		// @phpstan-ignore-next-line 21torr.custom.task.suffix
		return new readonly class() extends Task {
			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData("Test");
			}
		};
	}

	/**
	 * Creates a QueryBuilder stub where all fluent methods return self
	 * and getQuery() returns the given query.
	 *
	 * @param Query<object> $query
	 */
	private function createQueryBuilderStub (Query $query) : QueryBuilder
	{
		$qb = self::createStub(QueryBuilder::class);

		foreach (["select", "from", "leftJoin", "where", "andWhere", "setParameter", "addOrderBy", "setFirstResult", "setMaxResults", "delete"] as $method)
		{
			$qb->method($method)->willReturnSelf();
		}

		$qb->method("getQuery")->willReturn($query);

		return $qb;
	}

	/**
	 * @return Query<object>
	 */
	private function createCutoffQuery (?TaskLog $cutoffEntry) : Query
	{
		$query = self::createStub(Query::class);
		$query->method("getResult")->willReturn(null !== $cutoffEntry ? [$cutoffEntry] : []);

		return $query;
	}

	/**
	 * @return Query<object>
	 */
	private function createFetchQuery (array $ids) : Query
	{
		$rows = array_map(static fn (int $id) => ["id" => $id], $ids);

		$query = self::createStub(Query::class);
		$query->method("getArrayResult")->willReturn($rows);

		return $query;
	}

	/**
	 * Creates a fetch QueryBuilder stub that captures the oldestTimestamp parameter.
	 */
	private function createCapturingFetchQb (mixed &$capturedOldestTimestamp) : QueryBuilder
	{
		$fetchQb = self::createStub(QueryBuilder::class);

		foreach (["select", "from", "leftJoin", "where", "andWhere", "addOrderBy", "setFirstResult", "setMaxResults", "delete"] as $method)
		{
			$fetchQb->method($method)->willReturnSelf();
		}

		$fetchQb->method("setParameter")
			->willReturnCallback(
				static function (string $key, mixed $value) use ($fetchQb, &$capturedOldestTimestamp) : QueryBuilder
				{
					if ("oldestTimestamp" === $key)
					{
						$capturedOldestTimestamp = $value;
					}

					return $fetchQb;
				},
			);

		$fetchQb->method("getQuery")->willReturn($this->createFetchQuery([]));

		return $fetchQb;
	}

	// endregion

	public function testGetMaxLogEntryAge () : void
	{
		$cleaner = new LogCleaner(30, 100, self::createStub(EntityManagerInterface::class), new MockClock());

		self::assertSame(30, $cleaner->getMaxLogEntryAge());
	}

	public function testGetMaxLogEntryNumber () : void
	{
		$cleaner = new LogCleaner(30, 100, self::createStub(EntityManagerInterface::class), new MockClock());

		self::assertSame(100, $cleaner->getMaxLogEntryNumber());
	}

	public function testCleanLogEntriesReturnsZeroWhenNothingToDelete () : void
	{
		$em = $this->createMock(EntityManagerInterface::class);
		// Only 2 QueryBuilders: getCutoffEntry + fetchIdsToDelete — no delete queries
		$em->expects(self::exactly(2))
			->method("createQueryBuilder")
			->willReturnOnConsecutiveCalls(
				$this->createQueryBuilderStub($this->createCutoffQuery(null)),
				$this->createQueryBuilderStub($this->createFetchQuery([])),
			);

		$cleaner = new LogCleaner(30, 100, $em, new MockClock());

		self::assertSame(0, $cleaner->cleanLogEntries());
	}

	public function testCleanLogEntriesReturnsCountAndRunsDeletes () : void
	{
		$deleteQuery = self::createStub(Query::class);

		$em = $this->createMock(EntityManagerInterface::class);
		// 4 QueryBuilders: getCutoffEntry + fetchIdsToDelete + deleteRuns + deleteTasks
		$em->expects(self::exactly(4))
			->method("createQueryBuilder")
			->willReturnOnConsecutiveCalls(
				$this->createQueryBuilderStub($this->createCutoffQuery(null)),
				$this->createQueryBuilderStub($this->createFetchQuery([1, 2, 3])),
				$this->createQueryBuilderStub($deleteQuery),
				$this->createQueryBuilderStub($deleteQuery),
			);

		$cleaner = new LogCleaner(30, 100, $em, new MockClock());

		self::assertSame(3, $cleaner->cleanLogEntries());
	}

	public function testCutoffEntryOverridesTtlWhenNewer () : void
	{
		// Use the real clock time as MockClock: TTL purge date = now - 30 days.
		// The TaskLog created below has timeQueued ≈ now (real system clock),
		// which is newer than TTL purge date, so the cutoff entry should override.
		$clock = new MockClock();
		$cutoffEntry = new TaskLog($this->createTask());

		$capturedOldestTimestamp = null;

		$em = self::createStub(EntityManagerInterface::class);
		$em->method("createQueryBuilder")
			->willReturnOnConsecutiveCalls(
				$this->createQueryBuilderStub($this->createCutoffQuery($cutoffEntry)),
				$this->createCapturingFetchQb($capturedOldestTimestamp),
			);

		$cleaner = new LogCleaner(30, 100, $em, $clock);
		$cleaner->cleanLogEntries();

		self::assertEquals($cutoffEntry->timeQueued, $capturedOldestTimestamp);
	}

	public function testTtlPurgeDateUsedWhenNoCutoffEntry () : void
	{
		$clock = new MockClock("2024-06-15 12:00:00");
		$expectedPurgeDate = $clock->now()->sub(new \DateInterval("P30D"));

		$capturedOldestTimestamp = null;

		$em = self::createStub(EntityManagerInterface::class);
		$em->method("createQueryBuilder")
			->willReturnOnConsecutiveCalls(
				$this->createQueryBuilderStub($this->createCutoffQuery(null)),
				$this->createCapturingFetchQb($capturedOldestTimestamp),
			);

		$cleaner = new LogCleaner(30, 100, $em, $clock);
		$cleaner->cleanLogEntries();

		self::assertNotNull($capturedOldestTimestamp);
		self::assertInstanceOf(\DateTimeInterface::class, $capturedOldestTimestamp);
		self::assertEqualsWithDelta(
			$expectedPurgeDate->getTimestamp(),
			$capturedOldestTimestamp->getTimestamp(),
			1,
		);
	}
}
