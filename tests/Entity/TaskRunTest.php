<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Entity;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskRunTest extends TestCase
{
	use ClockSensitiveTrait;

	// region Helpers
	private function createLog () : TaskLog
	{
		// @phpstan-ignore-next-line 21torr.custom.task.suffix
		$task = new readonly class() extends Task {
			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData("Test");
			}
		};

		return new TaskLog($task, "my-uuid");
	}

	// endregion

	public function testInitialStateIsUnfinished () : void
	{
		$run = new TaskRun($this->createLog());

		self::assertFalse($run->isFinished);
		self::assertNull($run->success);
		self::assertNull($run->duration);
		self::assertNull($run->output);
		self::assertNull($run->finishedProperly);
	}

	public function testWaitTimeOfFirstRunIsMeasuredSinceQueueing () : void
	{
		self::mockTime("2026-06-16 12:00:00");
		$log = $this->createLog();
		self::mockTime("2026-06-16 12:00:03");
		$run = $log->createRun();

		self::assertEquals(3e9, $run->waitTime);
	}

	public function testWaitTimeOfRetryIsMeasuredSinceThePreviousRunFinished () : void
	{
		self::mockTime("2026-06-16 12:00:00");
		$log = $this->createLog();
		self::mockTime("2026-06-16 12:00:01");
		$firstRun = $log->createRun();
		self::mockTime("2026-06-16 12:00:05");
		$firstRun->finish(false, null);
		self::mockTime("2026-06-16 12:00:15");
		$retry = $log->createRun();

		self::assertEquals(1e9, $firstRun->waitTime);
		self::assertEquals(10e9, $retry->waitTime);
	}

	public function testFinishMarksAsSuccessful () : void
	{
		self::mockTime("2026-06-16 12:00:00");
		$run = new TaskRun($this->createLog());
		self::mockTime("2026-06-16 12:00:05");
		$run->finish(true, "some output");

		self::assertTrue($run->isFinished);
		self::assertTrue($run->success);
		self::assertSame("some output", $run->output);
		self::assertTrue($run->finishedProperly);
		self::assertEquals(5e9, $run->duration);
	}

	public function testFinishStoresThePeakMemoryUsage () : void
	{
		$run = new TaskRun($this->createLog());
		self::assertNull($run->memoryUsage);

		$run->finish(true, null);
		self::assertSame(memory_get_peak_usage(), $run->memoryUsage);
	}

	public function testFinishMarksAsFailure () : void
	{
		$run = new TaskRun($this->createLog());
		$run->finish(false, null);

		self::assertTrue($run->isFinished);
		self::assertFalse($run->success);
		self::assertNull($run->output);
		self::assertTrue($run->finishedProperly);
	}

	public function testAbortMarksAsNotFinishedProperly () : void
	{
		$run = new TaskRun($this->createLog());
		$run->abort(true, "aborted output");

		self::assertTrue($run->isFinished);
		self::assertTrue($run->success);
		self::assertSame("aborted output", $run->output);
		self::assertFalse($run->finishedProperly);
	}

	public function testDoubleFinalizationIsIgnored () : void
	{
		$run = new TaskRun($this->createLog());
		$run->finish(true, "first");
		$run->finish(false, "second");

		// second call must be ignored
		self::assertTrue($run->success);
		self::assertSame("first", $run->output);
	}

	public function testDoubleFinalizationLogsError () : void
	{
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method("error");

		$run = new TaskRun($this->createLog(), $logger);
		$run->finish(true, null);
		$run->finish(false, null);
	}

	public function testHasFinishedProperly () : void
	{
		$run = new TaskRun($this->createLog());
		self::assertFalse($run->hasFinishedProperly());

		$run->finish(true, null);
		self::assertTrue($run->hasFinishedProperly());
	}
}
