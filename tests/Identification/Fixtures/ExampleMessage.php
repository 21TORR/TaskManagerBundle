<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Identification\Fixtures;

final readonly class ExampleMessage
{
	public function __construct (
		public string $value = "example",
	) {}
}
