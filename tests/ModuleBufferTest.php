<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class ModuleBufferTest extends TestCase
{
    private const DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A20}';

    private $module;

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/SanitizationStubs/library.json');
        parent::setUp();

        $this->module = IPS\InstanceManager::getInstanceInterface(IPS_CreateInstance(self::DEVICE));
    }

    public function testIntegerBufferIsStoredAsString(): void
    {
        $this->setBuffer('KeepAlive', 1760990000);

        $this->assertSame('1760990000', $this->getBuffer('KeepAlive'));
    }

    public function testBooleanBufferIsStoredAsText(): void
    {
        $this->setBuffer('Enabled', true);
        $this->setBuffer('Disabled', false);

        //The kernel does not use the conversion of PHP, which results in '1' and an empty string
        $this->assertSame('True', $this->getBuffer('Enabled'));
        $this->assertSame('False', $this->getBuffer('Disabled'));
    }

    public function testNullBufferIsRemoved(): void
    {
        $this->setBuffer('Value', 'Text');
        $this->setBuffer('Value', null);

        $this->assertSame('', $this->getBuffer('Value'));
    }

    public function testBinaryBufferIsAccepted(): void
    {
        $this->setBuffer('Binary', "\xFF\x00\x01");

        $this->assertSame("\xFF\x00\x01", $this->getBuffer('Binary'));
    }

    public function testArrayBufferIsRejected(): void
    {
        $this->expectExceptionMessage('Cannot auto-convert value for parameter Data (Type does not match)');
        $this->setBuffer('Values', [1, 2, 3]);
    }

    private function setBuffer(string $name, $data): void
    {
        $setBuffer = new ReflectionMethod($this->module, 'SetBuffer');
        $setBuffer->setAccessible(true);
        $setBuffer->invoke($this->module, $name, $data);
    }

    private function getBuffer(string $name): string
    {
        $getBuffer = new ReflectionMethod($this->module, 'GetBuffer');
        $getBuffer->setAccessible(true);
        return $getBuffer->invoke($this->module, $name);
    }
}
