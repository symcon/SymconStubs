<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class LogServerTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();
    }

    public function testGlobalLogMessageIsRecorded(): void
    {
        $this->assertTrue(IPS_LogMessage('MyScript', 'Hello'));
        $this->assertSame([
            ['Message' => 'Hello', 'Type' => KL_MESSAGE]
        ], IPS\LogServer::getLogMessages('MyScript'));
        $this->assertSame([], IPS\LogServer::getLogMessages('Other'));
    }

    public function testModuleLogMessageIsRecordedPerInstance(): void
    {
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../CoreStubs/library.json');
        $id = IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}'); // Archive Control
        $module = IPS\InstanceManager::getInstanceInterface($id);

        $method = new ReflectionMethod($module, 'LogMessage');
        $method->setAccessible(true);
        $method->invoke($module, 'Something failed', KL_ERROR);
        $method->invoke($module, 'Recovered', KL_NOTIFY);

        $this->assertSame([
            ['Message' => 'Something failed', 'Type' => KL_ERROR],
            ['Message' => 'Recovered', 'Type' => KL_NOTIFY]
        ], IPS\LogServer::getLogMessages(strval($id)));
    }

    public function testResetClearsMessages(): void
    {
        IPS_LogMessage('MyScript', 'Hello');
        IPS\Kernel::reset();
        $this->assertSame([], IPS\LogServer::getLogMessages('MyScript'));
    }
}
