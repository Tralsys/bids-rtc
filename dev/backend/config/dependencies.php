<?php

declare(strict_types=1);

use BidsRtc\Backend\Controller;
use Psr\Container\ContainerInterface;

/**
 * DIコンテナの依存関係定義
 */
return [
  // Response Factory
  Psr\Http\Message\ResponseFactoryInterface::class => DI\factory([Slim\Factory\AppFactory::class, 'determineResponseFactory']),

  // Logger
  Psr\Log\LoggerInterface::class => DI\factory(function (ContainerInterface $c) {
    $logger = new Monolog\Logger($c->get('logger.name'));

    $handler = new Monolog\Handler\RotatingFileHandler(
      filename: $c->get('logger.path'),
      level: $c->get('logger.level'),
      filenameFormat: '{filename}.{date}.log',
    );

    $formatter = new Monolog\Formatter\LineFormatter(
      "[%datetime%] %channel%.%level_name%: %message% %extra%\n",
    );
    $handler->setFormatter($formatter);

    $logger->pushHandler($handler);
    $logger->pushProcessor(new Monolog\Processor\PsrLogMessageProcessor());
    $logger->pushProcessor(new Monolog\Processor\WebProcessor());
    $logger->pushProcessor(new Monolog\Processor\MemoryUsageProcessor());
    $logger->pushProcessor(new Monolog\Processor\IntrospectionProcessor());
    $logger->setTimezone(new DateTimeZone('UTC'));

    return $logger;
  }),

  // PDO
  PDO::class => DI\factory(function (ContainerInterface $c) {
    return new PDO(
      $c->get('pdo.dsn'),
      $c->get('pdo.username'),
      $c->get('pdo.password'),
      $c->get('pdo.options'),
    );
  }),

  // Firebase Factory
  Kreait\Firebase\Factory::class => DI\factory(function (ContainerInterface $c) {
    $factory = (new Kreait\Firebase\Factory())
      ->withAuthTokenCache(new Symfony\Component\Cache\Adapter\FilesystemAdapter(
        directory: $c->get('firebase.api_token_cache_dir'),
      ))
      ->withVerifierCache(new Symfony\Component\Cache\Adapter\FilesystemAdapter(
        directory: $c->get('firebase.auth.pubkey_cache_dir'),
      ))
      ->withProjectId($c->get('firebase.project_id'));

    // Use service account if file exists, otherwise use emulator mode
    $saFile = $c->get('firebase.sa_file');
    if ($saFile && file_exists($saFile)) {
      $factory = $factory->withServiceAccount($saFile);
    } elseif (getenv('FIREBASE_AUTH_EMULATOR_HOST')) {
      // For Firebase emulator, create a minimal fake service account
      $fakeServiceAccount = [
        'type' => 'service_account',
        'project_id' => $c->get('firebase.project_id') ?: 'demo-project',
        'private_key_id' => 'fake_key_id',
        'private_key' => '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC6U9z3sE2D0JD4\nFake==\n-----END PRIVATE KEY-----\n',
        'client_email' => 'fake@demo-project.iam.gserviceaccount.com',
        'client_id' => '123456789',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
      ];
      $factory = $factory->withServiceAccount($fakeServiceAccount);
    }

    return $factory->withHttpLogger($c->get(Psr\Log\LoggerInterface::class));
  }),

  // Firebase Auth
  Kreait\Firebase\Contract\Auth::class => DI\factory(function (ContainerInterface $c) {
    return $c->get(Kreait\Firebase\Factory::class)->createAuth();
  }),

  // JWT Configuration
  Lcobucci\JWT\Configuration::class => DI\factory(function (ContainerInterface $c) {
    return Lcobucci\JWT\Configuration::forAsymmetricSigner(
      signer: new Lcobucci\JWT\Signer\Rsa\Sha256(),
      signingKey: Lcobucci\JWT\Signer\Key\InMemory::file($c->get('my-auth.private_key')),
      verificationKey: Lcobucci\JWT\Signer\Key\InMemory::file($c->get('my-auth.public_key')),
    );
  }),

  // CORS Analyzer
  Neomerx\Cors\Contracts\AnalysisStrategyInterface::class => DI\factory(function (ContainerInterface $c) {
    $settings = new Neomerx\Cors\Strategies\Settings();
    $settings->setData($c->get('cors.settings'));
    return $settings;
  }),

  Neomerx\Cors\Contracts\AnalyzerInterface::class => DI\factory([Neomerx\Cors\Analyzer::class, 'instance']),

  // Middleware
  \BidsRtc\Backend\Middleware\AuthMiddleware::class => DI\autowire(),

  // Services
  \BidsRtc\Backend\Service\AppManagementService::class => DI\autowire(),
  \BidsRtc\Backend\Service\ClientManagementService::class => DI\autowire(),
];
