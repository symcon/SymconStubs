<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class ObjectManagerTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();
    }

    public function testDeletedVariableNoLongerExists(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        $this->assertTrue(IPS_ObjectExists($id));

        IPS_DeleteVariable($id);

        $this->assertFalse(IPS_VariableExists($id));
        $this->assertFalse(IPS_ObjectExists($id));
        $this->assertNotContains($id, IPS_GetObjectList());
    }

    public function testDeletedCategoryNoLongerExists(): void
    {
        $id = IPS_CreateCategory();
        IPS_DeleteCategory($id);

        $this->assertFalse(IPS_ObjectExists($id));
    }

    public function testDeletedScriptNoLongerExists(): void
    {
        $id = IPS_CreateScript(SCRIPTTYPE_PHP);
        IPS_DeleteScript($id, true);

        $this->assertFalse(IPS_ObjectExists($id));
    }
}
