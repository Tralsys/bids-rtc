<?php

namespace dev_t0r\bids_rtc\signaling\api;

use dev_t0r\bids_rtc\signaling\api\AbstractAPIInfoApi;
use dev_t0r\bids_rtc\signaling\model\ApiInfo;
use dev_t0r\bids_rtc\signaling\Utils;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ApiInfoApi extends AbstractAPIInfoApi
{
	private string $serverName = 'bids-rtc';
	private string $appVersion = '0.0.0';
	public function __construct(Container $container)
	{
		if ($container->has('app.name')) {
			$this->serverName = $container->get('app.name') ?? $this->serverName;
		}
		if ($container->has('app.version')) {
			$this->appVersion = $container->get('app.version') ?? $this->appVersion;
		}
	}

	public function getApiInfo(
		ServerRequestInterface $request,
		ResponseInterface $response,
	): ResponseInterface {
		$apiInfo = ApiInfo::createFromData([
			'server_name' => $this->serverName,
			'version' => $this->appVersion,
		]);

		return Utils::withJson($response, $apiInfo);
	}
}
