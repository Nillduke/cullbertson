<?php

namespace Tests\Unit;

use App\Services\AdminMailTransport;
use PHPUnit\Framework\TestCase;

class AdminMailTransportTest extends TestCase
{
    public function test_ssl_connection_timeouts_are_distinct_from_certificate_and_login_errors(): void
    {
        $timeout=AdminMailTransport::safeError(new \RuntimeException('Connection could not be established with host "ssl://smtp.gmail.com:465": Connection timed out'));
        $this->assertStringContainsString('Could not connect',$timeout);
        $this->assertStringContainsString('certificate validation failed',AdminMailTransport::safeError(new \RuntimeException('Connection could not be established: SSL certificate verify failed')));
        $this->assertStringContainsString('authentication failed',AdminMailTransport::safeError(new \RuntimeException('535 Authentication failed')));
    }
}
