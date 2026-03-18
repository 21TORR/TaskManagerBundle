<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Entity;

use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Exception\Log\InvalidLogActionException;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskLogTest extends TestCase
{
	// region Helpers

	// @phpstan-ignore-next-line 21torr.custom.task.suffix
	private function createTask () : Task
	{
		return new readonly class() extends Task {
			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData("Test");
			}
		};
	}

	private function createLog () : TaskLog
	{
		return new TaskLog($this->createTask());
	}

	// endregion

	public function testInitialStateHasNoRuns () : void
	{
		$log = $this->createLog();

		self::assertTrue($log->runs->isEmpty());
		self::assertFalse($log->isSuccess());
		self::assertNull($log->getStatus());
		self::assertNull($log->getLastUnfinishedRun());
		self::assertTrue($log->isFinished());
	}

	public function testCreateRunAddsRun () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();

		self::assertCount(1, $log->runs);
		self::assertSame($run, $log->runs->first());
	}

	public function testIsFinishedReturnsFalseWithUnfinishedRun () : void
	{
		$log = $this->createLog();
		$log->createRun();

		self::assertFalse($log->isFinished());
		self::assertNotNull($log->getLastUnfinishedRun());
	}

	public function testIsFinishedReturnsTrueAfterRunFinishes () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(true, null);

		self::assertTrue($log->isFinished());
		self::assertNull($log->getLastUnfinishedRun());
	}

	public function testIsSuccessReturnsTrueAfterSuccessfulRun () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(true, null);

		self::assertTrue($log->isSuccess());
	}

	public function testIsSuccessReturnsFalseAfterFailedRun () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(false, null);

		self::assertFalse($log->isSuccess());
	}

	public function testGetStatusNullWithNoFinishedRuns () : void
	{
		$log = $this->createLog();
		$log->createRun(); // unfinished

		self::assertNull($log->getStatus());
	}

	public function testGetStatusTrueAfterSuccess () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(true, null);

		self::assertTrue($log->getStatus());
	}

	public function testGetStatusFalseAfterFailure () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(false, null);

		self::assertFalse($log->getStatus());
	}

	public function testGetStatusReturnsTrueOnceAnyRunSucceeds () : void
	{
		$log = $this->createLog();
		$run1 = $log->createRun();
		$run1->finish(false, null);

		// second run succeeds — status should be true
		$run2 = $log->createRun();
		$run2->finish(true, null);

		self::assertTrue($log->getStatus());
	}

	public function testCreateRunThrowsWhenAlreadySuccessful () : void
	{
		$log = $this->createLog();
		$run = $log->createRun();
		$run->finish(true, null);

		$this->expectException(InvalidLogActionException::class);
		$log->createRun();
	}

	public function testGetTotalDurationSumsAllRuns () : void
	{
		$log = $this->createLog();

		$run1 = $log->createRun();
		$run1->finish(false, null);

		$run2 = $log->createRun();
		$run2->finish(true, null);

		$total = $log->getTotalDuration();

		self::assertGreaterThan(0, $total);
		self::assertEqualsWithDelta(
			($run1->duration ?? 0) + ($run2->duration ?? 0),
			$total,
			0.001,
		);
	}

	public function testTaskDetailsAccessors () : void
	{
		$log = $this->createLog();
		$log->setTaskDetails(["label" => "My Label", "transport" => "async", "handledBy" => "MyHandler"]);

		self::assertSame("My Label", $log->getTaskLabel());
		self::assertSame("async", $log->getTransport());
		self::assertSame("MyHandler", $log->getHandledBy());
	}

	public function testTaskDetailsDefaultsToNull () : void
	{
		$log = $this->createLog();

		self::assertNull($log->getTaskLabel());
		self::assertNull($log->getTransport());
		self::assertNull($log->getHandledBy());
	}

	public function testTaskIdMatchesTaskUlid () : void
	{
		$task = $this->createTask();
		$log = new TaskLog($task);

		self::assertSame($task->ulid, $log->taskId);
	}

	public function testTaskClassMatchesTaskClass () : void
	{
		$task = $this->createTask();
		$log = new TaskLog($task);

		self::assertSame($task::class, $log->taskClass);
	}
}
