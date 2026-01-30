<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AbacusServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_null_logger_when_logging_disabled(): void
    {
        config()->set('abacus-api.request_logging.enabled', false);

        /* Clear the singleton to force re-resolution */
        $this->app->forgetInstance('abacus.logger');

        $logger = $this->app->make('abacus.logger');

        $this->assertInstanceOf(NullLogger::class, $logger);
    }

    #[Test]
    public function it_registers_real_logger_when_logging_enabled(): void
    {
        config()->set('abacus-api.request_logging.enabled', true);

        /* Clear the singleton to force re-resolution */
        $this->app->forgetInstance('abacus.logger');

        $logger = $this->app->make('abacus.logger');

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertNotInstanceOf(NullLogger::class, $logger);
    }

    #[Test]
    public function it_registers_logger_as_singleton(): void
    {
        $logger1 = $this->app->make('abacus.logger');
        $logger2 = $this->app->make('abacus.logger');

        $this->assertSame($logger1, $logger2);
    }
}
