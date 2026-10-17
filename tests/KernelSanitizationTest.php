<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class KernelSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        parent::setUp();
    }

    /* Object Manager */
    public function testRootListsChildren(): void
    {
        $id = IPS_CreateCategory();

        $this->assertSame([$id], IPS_GetChildrenIDs(0));
        $this->assertArrayNotHasKey('ChildrendIDs', IPS_GetObject(0));
        $this->assertTrue(IPS_HasChildren(0));
    }

    public function testIdentMustBeUniqueBelowRoot(): void
    {
        IPS_SetIdent(IPS_CreateCategory(), 'Same');

        $this->expectExceptionMessage('Ident must be unique for each category');
        IPS_SetIdent(IPS_CreateCategory(), 'Same');
    }

    public function testIdentRejectsInvalidCharacters(): void
    {
        $id = IPS_CreateCategory();
        IPS_SetIdent($id, 'Valid_Ident_1');
        $this->assertSame('Valid_Ident_1', IPS_GetObject($id)['ObjectIdent']);

        $this->expectExceptionMessage('Ident may contain only letters and numbers');
        IPS_SetIdent($id, 'foo bar-ä');
    }

    public function testSetParentRequiresExistingParent(): void
    {
        $id = IPS_CreateCategory();

        $this->expectExceptionMessage('Object #99999 does not exist');
        IPS_SetParent($id, 99999);
    }

    public function testSetParentRejectsSelf(): void
    {
        $id = IPS_CreateCategory();

        $this->expectExceptionMessage('Cannot assign self as a parent object');
        IPS_SetParent($id, $id);
    }

    public function testSetParentRejectsChild(): void
    {
        $parent = IPS_CreateCategory();
        $child = IPS_CreateCategory();
        IPS_SetParent($child, $parent);

        $this->expectExceptionMessage('Cannot assign a parent object to his child');
        IPS_SetParent($parent, $child);
    }

    public function testSetParentRejectsIdentCollision(): void
    {
        $parent = IPS_CreateCategory();
        $first = IPS_CreateCategory();
        IPS_SetIdent($first, 'Same');
        IPS_SetParent($first, $parent);
        $second = IPS_CreateCategory();
        IPS_SetIdent($second, 'Same');

        $this->expectExceptionMessage('Ident must be unique for each category');
        IPS_SetParent($second, $parent);
    }

    public function testSetParentUpdatesHasChildren(): void
    {
        $first = IPS_CreateCategory();
        $second = IPS_CreateCategory();
        $child = IPS_CreateCategory();

        IPS_SetParent($child, $first);
        $this->assertTrue(IPS_GetObject($first)['HasChildren']);

        IPS_SetParent($child, $second);
        $this->assertFalse(IPS_GetObject($first)['HasChildren']);
        $this->assertTrue(IPS_GetObject($second)['HasChildren']);

        IPS_DeleteCategory($child);
        $this->assertFalse(IPS_GetObject($second)['HasChildren']);
    }

    public function testGetObjectIDByName(): void
    {
        $id = IPS_CreateCategory();
        IPS_SetName($id, 'Living Room');

        $this->assertSame($id, IPS_GetObjectIDByName('Living Room', 0));
        $this->assertSame($id, IPS_GetCategoryIDByName('Living Room', 0));
        $this->assertFalse(@IPS_GetObjectIDByName('Kitchen', 0));
        $this->assertFalse(@IPS_GetVariableIDByName('Living Room', 0));
    }

    public function testIsChild(): void
    {
        $parent = IPS_CreateCategory();
        $child = IPS_CreateCategory();
        $grandChild = IPS_CreateCategory();
        IPS_SetParent($child, $parent);
        IPS_SetParent($grandChild, $child);

        $this->assertTrue(IPS_IsChild($child, $parent, false));
        $this->assertFalse(IPS_IsChild($grandChild, $parent, false));
        $this->assertTrue(IPS_IsChild($grandChild, $parent, true));
        $this->assertFalse(IPS_IsChild($parent, $child, true));
    }

    public function testIconMustNotContainSpaces(): void
    {
        $this->expectExceptionMessage('Icons must not contain spaces!');
        IPS_SetIcon(IPS_CreateCategory(), 'Light Bulb');
    }

    public function testDeleteWithChildrenKeepsObject(): void
    {
        $parent = IPS_CreateCategory();
        IPS_SetParent(IPS_CreateCategory(), $parent);

        try {
            IPS_DeleteCategory($parent);
            $this->fail('Deleting a category with children must fail');
        } catch (Exception $e) {
            $this->assertSame('Cannot delete category if it has child objects', $e->getMessage());
        }

        $this->assertTrue(IPS_CategoryExists($parent));
        $this->assertTrue(IPS_ObjectExists($parent));
    }

    public function testCreateRejectsInvalidTypes(): void
    {
        $objectCount = count(IPS_GetObjectList());

        foreach ([fn () => IPS_CreateVariable(4), fn () => IPS_CreateScript(3), fn () => IPS_CreateMedia(6)] as $create) {
            try {
                $create();
                $this->fail('Creating an object with an invalid type must fail');
            } catch (Exception $e) {
                $this->assertStringContainsString('must be between', $e->getMessage());
            }
        }

        $this->assertCount($objectCount, IPS_GetObjectList());
    }

    /* Variable Manager */
    public function testTypedSetValueRequiresMatchingType(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);

        $this->expectExceptionMessage('VariableType does not match');
        SetValueBoolean($id, true);
    }

    public function testTypedGetValueRequiresMatchingType(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);

        $this->expectExceptionMessage('VariableType does not match');
        GetValueString($id);
    }

    public function testSetValueConvertsLikeKernel(): void
    {
        $bool = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        $int = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        $float = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        $string = IPS_CreateVariable(VARIABLETYPE_STRING);

        SetValue($bool, 1);
        $this->assertTrue(GetValue($bool));
        SetValue($bool, 'false');
        $this->assertFalse(GetValue($bool));

        SetValue($int, 1.7);
        $this->assertSame(2, GetValue($int));
        SetValue($int, '12,5');
        $this->assertSame(13, GetValue($int));
        SetValue($int, null);
        $this->assertSame(0, GetValue($int));

        SetValue($float, '3,25');
        $this->assertSame(3.25, GetValue($float));
        SetValue($float, 5);
        $this->assertSame(5.0, GetValue($float));

        SetValue($string, true);
        $this->assertSame('True', GetValue($string));
        SetValue($string, 42);
        $this->assertSame('42', GetValue($string));
    }

    public function testSetValueRejectsUnconvertibleValues(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);

        $this->expectExceptionMessage('Cannot auto-convert value for parameter Value');
        SetValue($id, 'abc');
    }

    public function testSetValueRejectsArrays(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_STRING);

        $this->expectExceptionMessage('Cannot auto-convert value for parameter Value (Type does not match)');
        SetValue($id, []);
    }

    public function testFloatRejectsNaN(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_FLOAT);

        $this->expectExceptionMessage('NaN/INF values are not supported');
        SetValueFloat($id, NAN);
    }

    public function testStringRejectsInvalidUTF8(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_STRING);

        $this->expectExceptionMessage('Variable value must be UTF-8 encoded since Symcon 6.3!');
        SetValueString($id, "\xFF");
    }

    public function testStringRejectsTooLongValues(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_STRING);

        $this->expectExceptionMessage('Limit of 1024kb exceeded. Variable has not been written.');
        SetValueString($id, str_repeat('a', 1024 * 1024 + 1));
    }

    public function testReadOnlyVariableCannotBeChanged(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS\ObjectManager::setReadOnly($id, true);

        $this->expectExceptionMessage('Variable is marked as read-only and cannot be changed');
        SetValue($id, 5);
    }

    public function testCustomProfileMustExistAndMatchType(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);

        try {
            IPS_SetVariableCustomProfile($id, 'Missing');
            $this->fail('Setting a missing profile must fail');
        } catch (Exception $e) {
            $this->assertSame('Profile with name #Missing does not exist', $e->getMessage());
        }

        $this->expectExceptionMessage('Variable Type and Profile Type do not match!');
        IPS_SetVariableCustomProfile($id, '~Temperature');
    }

    public function testCustomActionMustBeExistingScript(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomAction($id, 1);
        IPS_SetVariableCustomAction($id, IPS_CreateScript(SCRIPTTYPE_PHP));

        $this->expectExceptionMessage('Script #12345 does not exist');
        IPS_SetVariableCustomAction($id, 12345);
    }

    public function testCustomPresentationIsValidated(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetVariableCustomPresentation($id, ['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER]);
        IPS_SetVariableCustomPresentation($id, []);

        try {
            IPS_SetVariableCustomPresentation($id, ['MIN' => 0]);
            $this->fail('A presentation without PRESENTATION key must fail');
        } catch (Exception $e) {
            $this->assertSame("Non-empty presentation format is missing 'PRESENTATION' key.", $e->getMessage());
        }

        $this->expectExceptionMessage("Presentation format contains invalid 'PRESENTATION' ID: {00000000-0000-0000-0000-000000000000}");
        IPS_SetVariableCustomPresentation($id, ['PRESENTATION' => '{00000000-0000-0000-0000-000000000000}']);
    }

    public function testVariablePresentationFallsBackToSystemPresentation(): void
    {
        $id = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS\VariableManager::setVariableProfile($id, '~Temperature');

        $this->assertSame(['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'PROFILE' => '~Temperature'], IPS\VariableManager::getVariablePresentation($id));

        IPS_SetVariableCustomPresentation($id, ['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER]);
        $this->assertSame(['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER], IPS\VariableManager::getVariablePresentation($id));
    }

    /* Profile Manager */
    public function testProfileNameIsValidated(): void
    {
        foreach (['' => 'Profilename cannot be empty', 'My Profile' => 'Profilename cannot have spaces', '~Custom' => 'Profilename cannot have special chars'] as $name => $message) {
            try {
                IPS_CreateVariableProfile(strval($name), VARIABLETYPE_INTEGER);
                $this->fail('Creating the profile must fail');
            } catch (Exception $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        IPS_CreateVariableProfile('My.Profile-1_a', VARIABLETYPE_INTEGER);
        $this->assertTrue(IPS_VariableProfileExists('My.Profile-1_a'));
    }

    public function testProfileCannotBeCreatedTwice(): void
    {
        IPS_CreateVariableProfile('Duplicate', VARIABLETYPE_INTEGER);

        $this->expectExceptionMessage('Profile with name #Duplicate already exists');
        IPS_CreateVariableProfile('Duplicate', VARIABLETYPE_FLOAT);
    }

    public function testProfileTypeIsValidated(): void
    {
        $this->expectExceptionMessage('ProfileType must be between 0 and 3');
        IPS_CreateVariableProfile('InvalidType', 4);
    }

    public function testSystemProfilesAreReadOnly(): void
    {
        $this->expectExceptionMessage('Profile #~Temperature has been marked as read only');
        IPS_SetVariableProfileDigits('~Temperature', 2);
    }

    public function testProfileValuesAreValidated(): void
    {
        IPS_CreateVariableProfile('Values', VARIABLETYPE_INTEGER);

        try {
            IPS_SetVariableProfileValues('Values', 10, 0, 1);
            $this->fail('Min > Max must fail');
        } catch (Exception $e) {
            $this->assertSame('Min value cannot be bigger than max value', $e->getMessage());
        }

        try {
            IPS_SetVariableProfileDigits('Values', 17);
            $this->fail('Too many digits must fail');
        } catch (Exception $e) {
            $this->assertSame('Digits must be between 0 and 16', $e->getMessage());
        }

        IPS_CreateVariableProfile('Text', VARIABLETYPE_STRING);
        $this->expectExceptionMessage('Cannot modify values for string profiles');
        IPS_SetVariableProfileValues('Text', 0, 10, 1);
    }

    public function testBooleanAssociationsCannotBeDeleted(): void
    {
        IPS_CreateVariableProfile('Switch', VARIABLETYPE_BOOLEAN);
        IPS_SetVariableProfileAssociation('Switch', true, 'On', '', -1);

        $this->expectExceptionMessage('Cannot delete association for boolean profiles');
        IPS_SetVariableProfileAssociation('Switch', true, '', '', -1);
    }

    public function testDeletingMissingAssociationFails(): void
    {
        IPS_CreateVariableProfile('Levels', VARIABLETYPE_INTEGER);

        $this->expectExceptionMessage('Cannot find association for deletion with value 5');
        IPS_SetVariableProfileAssociation('Levels', 5, '', '', -1);
    }

    public function testFloatAssociationsAreSorted(): void
    {
        IPS_CreateVariableProfile('Fractions', VARIABLETYPE_FLOAT);
        IPS_SetVariableProfileAssociation('Fractions', 0.5, 'Half', '', -1);
        IPS_SetVariableProfileAssociation('Fractions', 0.2, 'Fifth', '', -1);

        $this->assertSame([0.2, 0.5], array_column(IPS_GetVariableProfile('Fractions')['Associations'], 'Value'));
    }

    public function testProfileListByTypeReturnsNames(): void
    {
        $names = IPS_GetVariableProfileListByType(VARIABLETYPE_FLOAT);

        $this->assertContains('~Temperature', $names);
        $this->assertContainsOnly('string', $names);
    }

    /* Script Manager / Engine */
    public function testScriptFunctions(): void
    {
        $id = IPS_CreateScript(SCRIPTTYPE_PHP);
        IPS_SetScriptContent($id, '<?php echo "Hello";');

        $this->assertSame($id . '.ips.php', IPS_GetScriptFile($id));
        $this->assertSame('Hello', IPS_RunScriptWait($id));
        $this->assertSame('World', IPS_RunScriptTextWait('echo "World";'));
    }

    public function testScriptContentIsRemovedWithScript(): void
    {
        $id = IPS_CreateScript(SCRIPTTYPE_PHP);
        IPS_DeleteScript($id, true);

        $this->expectExceptionMessage(sprintf('Script #%d does not exist', $id));
        IPS_GetScriptContent($id);
    }

    /* Link Manager */
    public function testLinkTargetIsValidated(): void
    {
        $link = IPS_CreateLink();
        IPS_SetLinkTargetID($link, IPS_CreateCategory());

        try {
            IPS_SetLinkTargetID($link, 99999);
            $this->fail('Missing targets must fail');
        } catch (Exception $e) {
            $this->assertSame('Object #99999 does not exist', $e->getMessage());
        }

        $this->expectExceptionMessage('TargetID must not be another Link');
        IPS_SetLinkTargetID($link, IPS_CreateLink());
    }
}
