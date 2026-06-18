<?php declare(strict_types=1);

namespace Torr\TaskManager\Identification;

use Symfony\Component\Console\Messenger\RunCommandContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Normalizer\TaskDetailsNormalizer;
use Torr\TaskManager\Task\Task;

/**
 * @final
 */
readonly class TaskIdentificationMiddleware implements MiddlewareInterface
{
	/**
	 */
	public function __construct (
		private TaskIdentifier $taskIdentifier,
		private TaskLogModel $logModel,
		private TaskDetailsNormalizer $detailsNormalizer,
	) {}

	/**
	 */
	public function handle (Envelope $envelope, StackInterface $stack) : Envelope
	{
		$stamp = $envelope->last(TaskIdStamp::class);

		if (null === $stamp)
		{
			$stamp = new TaskIdStamp();
			$envelope = $envelope->with($stamp);
		}

		$this->taskIdentifier->setUuid($envelope->getMessage(), $stamp->taskId);

		// create task run before
		$logEntry = $this->logModel->getLogForUuid($envelope->getMessage(), $stamp->taskId);
		$logEntry->setTaskDetails($this->detailsNormalizer->normalizeTaskDetails($envelope));
		$this->logModel->flush();

		try
		{
			// push message through the stack
			$envelope = $stack->next()->handle($envelope, $stack);
		}
		catch (ExceptionInterface $e)
		{
			$run = $this->getTaskRun($logEntry, $envelope->getMessage());

			if (null !== $run)
			{
				$run->abort(false, $e->getMessage());
				$this->logModel->flush();
			}

			throw $e;
		}

		$handledStamp = $envelope->last(HandledStamp::class);

		if (null !== $handledStamp)
		{
			$run = $this->getTaskRun($logEntry, $envelope->getMessage());

			if (null !== $run)
			{
				$run->abort(
					success: true,
					output: $this->extractResultContent($handledStamp),
				);
			}
		}

		// update log with most up-to-date details
		$logEntry->setTaskDetails($this->detailsNormalizer->normalizeTaskDetails($envelope));
		$this->logModel->flush();

		return $envelope;
	}

	/**
	 *
	 */
	private function getTaskRun (TaskLog $log, object $message) : ?TaskRun
	{
		// if the message is not a task, no task director will be called.
		// so we can create a run here
		if (!$message instanceof Task)
		{
			return $this->logModel->createRunForTask($log);
		}

		return $log->getLastUnfinishedRun();
	}

	/**
	 */
	private function extractResultContent (HandledStamp $handledStamp) : ?string
	{
		$result = $handledStamp->getResult();

		if (\is_string($result))
		{
			return $result;
		}

		if ($result instanceof RunCommandContext)
		{
			return $result->output;
		}

		return null;
	}
}
