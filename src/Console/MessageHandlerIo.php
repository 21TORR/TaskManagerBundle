<?php declare(strict_types=1);

namespace Torr\TaskManager\Console;

use Symfony\Component\Console\Input\ArrayInput;
use Torr\Cli\Console\Style\TorrStyle;

final class MessageHandlerIo extends TorrStyle
{
	private readonly ChainOutput $output;

	/**
	 */
	public function __construct ()
	{
		$this->output = new ChainOutput();

		parent::__construct(
			new ArrayInput([]),
			$this->output,
		);
	}

	/**
	 *
	 */
	public function getBufferedOutput () : string
	{
		return $this->output->getBufferedOutput();
	}
}
