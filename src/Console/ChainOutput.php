<?php declare(strict_types=1);

namespace Torr\TaskManager\Console;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ChainOutput implements OutputInterface
{
	private OutputInterface $mainOutput;
	private BufferedOutput $bufferedOutput;

	/**
	 */
	public function __construct (
		int $verbosity = self::VERBOSITY_NORMAL,
		bool $decorated = true,
		?OutputFormatterInterface $formatter = null,
		?OutputInterface $mainOutput = null,
	)
	{
		$this->bufferedOutput = new BufferedOutput($verbosity, $decorated, $formatter);

		$this->mainOutput = $mainOutput ?? new ConsoleOutput();
		$this->mainOutput->setDecorated($decorated);
		$this->mainOutput->setVerbosity($verbosity);

		if (null !== $formatter)
		{
			$this->mainOutput->setFormatter($formatter);
		}
	}

	/**
	 *
	 */
	#[\Override]
	public function write (iterable|string $messages, bool $newline = false, int $options = 0) : void
	{
		$this->bufferedOutput->write($messages, $newline, $options);
		$this->mainOutput->write($messages, $newline, $options);
	}

	/**
	 *
	 */
	#[\Override]
	public function writeln (iterable|string $messages, int $options = 0) : void
	{
		$this->bufferedOutput->writeln($messages, $options);
		$this->mainOutput->writeln($messages, $options);
	}

	/**
	 *
	 */
	#[\Override]
	public function setVerbosity (int $level) : void
	{
		$this->bufferedOutput->setVerbosity($level);
		$this->mainOutput->setVerbosity($level);
	}

	/**
	 *
	 */
	#[\Override]
	public function getVerbosity () : int
	{
		return $this->bufferedOutput->getVerbosity();
	}

	/**
	 *
	 */
	#[\Override]
	public function isQuiet () : bool
	{
		return $this->bufferedOutput->isQuiet();
	}

	/**
	 *
	 */
	#[\Override]
	public function isVerbose () : bool
	{
		return $this->bufferedOutput->isVerbose();
	}

	/**
	 *
	 */
	#[\Override]
	public function isVeryVerbose () : bool
	{
		return $this->bufferedOutput->isVeryVerbose();
	}

	/**
	 *
	 */
	#[\Override]
	public function isDebug () : bool
	{
		return $this->bufferedOutput->isDebug();
	}

	/**
	 *
	 */
	public function isSilent() : bool
	{
		return $this->bufferedOutput->isSilent();
	}

	/**
	 *
	 */
	#[\Override]
	public function setDecorated (bool $decorated) : void
	{
		$this->bufferedOutput->setDecorated($decorated);
		$this->mainOutput->setDecorated($decorated);
	}

	/**
	 *
	 */
	#[\Override]
	public function isDecorated () : bool
	{
		return $this->bufferedOutput->isDecorated();
	}

	/**
	 *
	 */
	public function setFormatter (OutputFormatterInterface $formatter) : void
	{
		$this->bufferedOutput->setFormatter($formatter);
		$this->mainOutput->setFormatter($formatter);
	}

	/**
	 *
	 */
	#[\Override]
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
