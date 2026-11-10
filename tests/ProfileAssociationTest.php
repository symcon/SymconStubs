<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class ProfileAssociationTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();

        IPS_CreateVariableProfile('Test.String', VARIABLETYPE_STRING);
        IPS_SetVariableProfileAssociation('Test.String', 'a', 'A', '', -1);
        IPS_SetVariableProfileAssociation('Test.String', 'b', 'B', '', -1);
        IPS_SetVariableProfileAssociation('Test.String', 'c', 'C', '', -1);
    }

    public function testDeletingStringAssociationKeepsList(): void
    {
        IPS_SetVariableProfileAssociation('Test.String', 'b', '', '', -1);

        $associations = IPS_GetVariableProfile('Test.String')['Associations'];
        $this->assertSame([0, 1], array_keys($associations));
        $this->assertSame(['a', 'c'], array_column($associations, 'Value'));
    }

    public function testFormattingFindsAssociationAfterDeletion(): void
    {
        IPS_SetVariableProfileAssociation('Test.String', 'b', '', '', -1);

        $id = IPS_CreateVariable(VARIABLETYPE_STRING);
        IPS_SetVariableCustomProfile($id, 'Test.String');
        SetValue($id, 'c');

        $this->assertSame('C', GetValueFormatted($id));
    }
}
