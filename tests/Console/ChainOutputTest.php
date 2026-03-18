<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Torr\TaskManager\Console\ChainOutput;

/**
 * @internal
 */
final class ChainOutputTest extends TestCase
{
	public function testWriteIsBuffered () : void
	{
		$output = new ChainOutput();
		$output->write("hello");

		self::assertSame("hello", $output->getBufferedOutput());
	}

	public function testWritelnIsBuffered () : void
	{
		$output = new ChainOutput();
		$output->writeln("hello");

		self::assertSame("hello\n", $output->getBufferedOutput());
	}

	public function testBufferedOutputIsConsumedOnFetch () : void
	{
		$output = new ChainOutput();
		$output->write("hello");

		$output->getBufferedOutput();

		self::assertSame("", $output->getBufferedOutput());
	}

	public function testSetAndGetVerbosity () : void
	{
		$output = new ChainOutput();
		$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

		self::assertSame(OutputInterface::VERBOSITY_VERBOSE, $output->getVerbosity());
		self::assertTrue($output->isVerbose());
		self::assertFalse($output->isVeryVerbose());
		self::assertFalse($output->isDebug());
		self::assertFalse($output->isQuiet());
	}

	public function testSetDecoratedForwardsValue () : void
	{
		$output = new ChainOutput(decorated: true);
		self::assertTrue($output->isDecorated());

		$output->setDecorated(false);
		self::assertFalse($output->isDecorated());

		$output->setDecorated(true);
		self::assertTrue($output->isDecorated());
	}

	public function testConstructorDecoratedDefault () : void
	{
		$output = new ChainOutput(decorated: false);
		self::assertFalse($output->isDecorated());
	}

	public function testVerbosityLevels () : void
	{
		$output = new ChainOutput(verbosity: OutputInterface::VERBOSITY_DEBUG);
		self::assertTrue($output->isDebug());
		self::assertTrue($output->isVeryVerbose());
		self::assertTrue($output->isVerbose());

		$output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
		self::assertTrue($output->isQuiet());
	}
}
