<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class HookTestModule extends IPSModuleStrict
{
    public function callRegisterHook(string $HookPath): bool
    {
        return $this->RegisterHook($HookPath);
    }

    public function callRegisterOAuth(string $OAuthPath): bool
    {
        return $this->RegisterOAuth($OAuthPath);
    }

    protected function getTime(): int
    {
        return 0;
    }
}

class ModuleStrictHookTest extends TestCase
{
    private HookTestModule $module;

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();

        $id = IPS\ObjectManager::registerObject(OBJECTTYPE_INSTANCE);
        IPS\InstanceManager::createInstance($id, [
            'ModuleID'   => '{9A1A2B6D-35C2-4F49-9E7E-2C6A3F2D5B10}',
            'ModuleName' => 'HookTestModule',
            'ModuleType' => MODULETYPE_DEVICE,
            'Class'      => HookTestModule::class
        ]);
        $this->module = IPS\InstanceManager::getInstanceInterface($id);
    }

    public function testRegisterHookReturnsTrue(): void
    {
        $this->assertTrue($this->module->callRegisterHook('/hook/test'));
    }

    public function testRegisterOAuthReturnsTrue(): void
    {
        $this->assertTrue($this->module->callRegisterOAuth('test'));
    }
}
