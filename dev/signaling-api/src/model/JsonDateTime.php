<?php

namespace dev_t0r\bids_rtc\signaling\model;

final class JsonDateTime extends \DateTime implements \JsonSerializable
{
	public function __construct(\DateTime $dateTime)
	{
		parent::__construct($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
	}

	public function jsonSerialize()
	{
		return $this->format('Y-m-d\TH:i:s.u\Z');
	}
}
