<?php declare(strict_types=1);

namespace Torr\TaskManager\Console;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ChainOutput implements OutputInterface
{
	private readonly ConsoleOutput $consoleOutput;
	private readonly BufferedOutput $bufferedOutput;

	/**
	 */
	public function __construct (
		int $verbosity = self::VERBOSITY_NORMAL,
		bool $decorated = true,
		?OutputFormatterInterface $formatter = null,
	)
	{
		$this->bufferedOutput = new BufferedOutput($verbosity, $decorated, $formatter);
		$this->consoleOutput = new ConsoleOutput($verbosity, $decorated, $formatter);
	}

	/**
	 * @inheritDoc
	 */
	public function write (iterable|string $messages, bool $newline = false, int $options = 0) : void
	{
		$this->bufferedOutput->write($messages, $newline, $options);
		$this->consoleOutput->write($messages, $newline, $options);
	}

	/**
	 * @inheritDoc
	 */
	public function writeln (iterable|string $messages, int $options = 0) : void
	{
		$this->bufferedOutput->writeln($messages, $options);
		$this->consoleOutput->writeln($messages, $options);
	}

	/**
	 * @inheritDoc
	 */
	public function setVerbosity (int $level) : void
	{
		$this->bufferedOutput->setVerbosity($level);
		$this->consoleOutput->setVerbosity($level);
	}

	/**
	 * @inheritDoc
	 */
	public function getVerbosity () : int
	{
		return $this->bufferedOutput->getVerbosity();
	}

	/**
	 * @inheritDoc
	 */
	public function isQuiet () : bool
	{
		return $this->bufferedOutput->isQuiet();
	}

	/**
	 * @inheritDoc
	 */
	public function isVerbose () : bool
	{
		return $this->bufferedOutput->isVerbose();
	}

	/**
	 * @inheritDoc
	 */
	public function isVeryVerbose () : bool
	{
		return $this->bufferedOutput->isVeryVerbose();
	}

	/**
	 * @inheritDoc
	 */
	public function isDebug () : bool
	{
		return $this->bufferedOutput->isDebug();
	}

	/**
	 * @inheritDoc
	 */
	public function setDecorated (bool $decorated) : void
	{
		$this->bufferedOutput->setDecorated(true);
		$this->consoleOutput->setDecorated(true);
	}

	/**
	 * @inheritDoc
	 */
	public function isDecorated () : bool
	{
		return $this->bufferedOutput->isDecorated();
	}

	public function setFormatter (OutputFormatterInterface $formatter) : void
	{
		$this->bufferedOutput->setFormatter($formatter);
		$this->consoleOutput->setFormatter($formatter);
	}

	/**
	 * @inheritDoc
	 */
	public function getFormatter () : OutputFormatterInterface
	{
		return $this->bufferedOutput->getFormatter();
	}

	/**
	 *
	 */
	public function getBufferedOutput () : string
	{
		return $this->bufferedOutput->fetch();
	}
}
