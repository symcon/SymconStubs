<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class LibraryLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();
    }

    public function testFolderWithoutModuleJsonIsSkipped(): void
    {
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/ToolsFolderStubs/library.json');

        $this->assertTrue(IPS_ModuleExists('{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1B10}'));
        $this->assertSame(['{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1B10}'], IPS_GetLibraryModules('{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1B01}'));
    }
}
