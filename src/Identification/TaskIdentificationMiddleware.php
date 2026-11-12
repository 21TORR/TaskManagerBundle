<?php declare(strict_types=1);

namespace Torr\TaskManager\Identification;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Messenger\RunCommandContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Torr\TaskManager\Director\ActiveRun;
use Torr\TaskManager\Director\ActiveRunRegistry;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Normalizer\TaskDetailsNormalizer;

/**
 * Creates the task log entry when a message is dispatched and tracks every try of handling it as a run.
 *
 * @final
 */
readonly class TaskIdentificationMiddleware implements MiddlewareInterface
{
	private const string BATCHED_OUTPUT = "The message was added to a batch. The result of the batch handler is not tracked.";

	/**
	 */
	public function __construct (
		private TaskLogModel $logModel,
		private TaskDetailsNormalizer $detailsNormalizer,
		#[Autowire(service: 'messenger.bus.default.messenger.handlers_locator')]
		private HandlersLocatorInterface $handlersLocator,
		#[Autowire(service: 'messenger.senders_locator')]
		private SendersLocatorInterface $sendersLocator,
		private ActiveRunRegistry $activeRuns,
		private LoggerInterface $logger,
	) {}

	/**
	 */
	public function handle (Envelope $envelope, StackInterface $stack) : Envelope
	{
		if (!$this->isHandledLocally($envelope))
		{
			return $stack->next()->handle($envelope, $stack);
		}

		$stamp = $envelope->last(TaskIdStamp::class);

		if (null === $stamp)
		{
			$stamp = new TaskIdStamp();
			$envelope = $envelope->with($stamp);
		}

		$message = $envelope->getMessage();

		$logEntry = $this->logModel->getLogForUuid($message, $stamp->taskId);
		$logEntry->setTaskDetails($this->detailsNormalizer->normalizeTaskDetails($envelope));

		// The message is only sent to a transport, so it is just queued. The run is created when it is actually handled.
		// This includes sync transports, as they dispatch the message again (and that dispatch then handles it).
		if (!$this->isHandledInThisPass($envelope))
		{
			$this->logModel->flush();

			return $stack->next()->handle($envelope, $stack);
		}

		// The message was already handled successfully, but is handled again (e.g. as it was redelivered by the transport).
		if ($logEntry->isSuccess())
		{
			$this->logger->warning("The task {taskId} is handled again, although it was already handled successfully. This run is not logged.", [
				"taskId" => $stamp->taskId,
				"type" => get_debug_type($message),
			]);
			$this->logModel->flush();

			return $stack->next()->handle($envelope, $stack);
		}

		$activeRun = new ActiveRun($this->logModel->createRunForTask($logEntry));
		$this->logModel->flush();

		// the envelope of a retry still contains the results of the handlers that succeeded in the previous tries
		$previousResults = \count($envelope->all(HandledStamp::class));
		$previousActiveRun = $this->activeRuns->get($message);
		$this->activeRuns->set($message, $activeRun);

		try
		{
			$envelope = $stack->next()->handle($envelope, $stack);
		}
		catch (\Throwable $exception)
		{
			$this->finalizeRun($activeRun, $envelope, $previousResults, $exception);

			throw $exception;
		}
		finally
		{
			$this->activeRuns->set($message, $previousActiveRun);
		}

		$this->finalizeRun($activeRun, $envelope, $previousResults, null);

		return $envelope;
	}

	/**
	 * Returns whether there is any handler for the message in this application.
	 */
	private function isHandledLocally (Envelope $envelope) : bool
	{
		foreach ($this->handlersLocator->getHandlers($envelope) as $handler)
		{
			return true;
		}

		return false;
	}

	/**
	 * Returns whether the handlers are called in this pass through the bus (instead of only sending the message
	 * to a transport).
	 */
	private function isHandledInThisPass (Envelope $envelope) : bool
	{
		// received messages are never sent again
		if (null !== $envelope->last(ReceivedStamp::class))
		{
			return true;
		}

		foreach ($this->sendersLocator->getSenders($envelope) as $sender)
		{
			return false;
		}

		return true;
	}

	/**
	 * Stores the result of the run.
	 *
	 * This must never fail, as otherwise it would hide the result of the handlers.
	 *
	 * @param int $previousResults the number of handler results, that were already in the envelope before this try
	 */
	private function finalizeRun (ActiveRun $activeRun, Envelope $envelope, int $previousResults, ?\Throwable $exception) : void
	{
		try
		{
			// the run might be detached, if the handler cleared the entity manager
			$run = $this->logModel->getManagedRun($activeRun->run);

			if (null === $run)
			{
				return;
			}

			$success = null === $exception && !$activeRun->hasFailed();
			$output = $this->buildOutput(
				$activeRun,
				$this->getResultsOfThisTry($envelope, $previousResults, $exception),
				$exception,
				null === $exception && null !== $envelope->last(NoAutoAckStamp::class),
			);

			if (null === $exception && $activeRun->wasFinishedProperly())
			{
				$run->finish($success, $output);
			}
			else
			{
				$run->abort($success, $output);
			}

			// update log with most up-to-date details
			$run->taskLog->setTaskDetails($this->detailsNormalizer->normalizeTaskDetails($envelope));
			$this->logModel->flush();
		}
		catch (\Throwable $finalizeException)
		{
			$this->logger->error("Failed to store the result of the task run: {message}", [
				"message" => $finalizeException->getMessage(),
				"exception" => $finalizeException,
			]);
		}
	}

	/**
	 * Returns the results of the handlers that were called in this try.
	 *
	 * @return list<HandledStamp>
	 */
	private function getResultsOfThisTry (Envelope $envelope, int $previousResults, ?\Throwable $exception) : array
	{
		// If handlers failed, the results of the successful handlers are only part of the envelope of the exception.
		// For every other exception, there are no results.
		if (null !== $exception)
		{
			if (!$exception instanceof HandlerFailedException)
			{
				return [];
			}

			$envelope = $exception->getEnvelope();
		}

		return \array_slice($envelope->all(HandledStamp::class), $previousResults);
	}

	/**
	 * @param list<HandledStamp> $handledStamps
	 */
	private function buildOutput (ActiveRun $activeRun, array $handledStamps, ?\Throwable $exception, bool $isBatched) : ?string
	{
		$handlerOutput = $activeRun->getOutput();
		$parts = [$handlerOutput];

		foreach ($handledStamps as $handledStamp)
		{
			$parts[] = $this->extractResultContent($handledStamp);
		}

		$parts[] = $exception?->getMessage();
		$parts[] = $isBatched ? self::BATCHED_OUTPUT : null;
		$parts = array_filter($parts, static fn (?string $part) => null !== $part && "" !== $part);

		return [] !== $parts
			? implode("\n\n", $parts)
			: $handlerOutput;
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
