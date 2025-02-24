<?php

namespace dev_t0r\bids_rtc\signaling;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

class UtcClock implements ClockInterface
{
	public function now(): DateTimeImmutable
	{
		return new DateTimeImmutable("now", Utils::getUTC());
	}
}
