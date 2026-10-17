<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';
include_once __DIR__ . '/../Validator.php';

use PHPUnit\Framework\TestCase;

class DocumentationAlignmentTest extends TestCase
{
    private const DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A20}';
    private const STRICT_DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A30}';

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/SanitizationStubs/library.json');
        parent::setUp();
    }

    /* Return values */
    public function testSettersReturnTrue(): void
    {
        $id = IPS_CreateCategory();
        $this->assertTrue(IPS_SetName($id, 'Name'));
        $this->assertTrue(IPS_SetIdent($id, 'Ident'));
        $this->assertTrue(IPS_SetPosition($id, 1));
        $this->assertTrue(IPS_SetHidden($id, true));
        $this->assertTrue(IPS_SetParent($id, IPS_CreateCategory()));

        $this->assertTrue(IPS_CreateVariableProfile('Profile', VARIABLETYPE_INTEGER));
        $this->assertTrue(IPS_SetVariableProfileValues('Profile', 0, 10, 1));
        $this->assertTrue(IPS_SetVariableProfileAssociation('Profile', 1, 'One', '', -1));
        $this->assertTrue(IPS_DeleteVariableProfile('Profile'));

        $variable = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        $this->assertTrue(IPS_SetVariableCustomPresentation($variable, []));
        $this->assertTrue(IPS_DeleteVariable($variable));
    }

    public function testInstanceFunctionsReturnTrue(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->assertTrue(IPS_SetProperty($id, 'Port', 81));
        $this->assertTrue(IPS_ApplyChanges($id));
        $this->assertTrue(IPS_RequestAction($id, 'Value', 1));

        //Like in the kernel, the children of an instance need to be deleted first
        $this->assertTrue(IPS_DeleteVariable(IPS_GetObjectIDByIdent('Value', $id)));
        $this->assertTrue(IPS_DeleteInstance($id));
    }

    /* Keys */
    public function testCategoryContainsID(): void
    {
        $id = IPS_CreateCategory();

        $this->assertSame(['CategoryID' => $id], IPS_GetCategory($id));
    }

    public function testInstanceContainsDocumentedKeys(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        $instance = IPS_GetInstance($id);

        foreach (['InstanceID', 'ConnectionID', 'InstanceStatus', 'InstanceSupportsSearching', 'InstanceIsSearching', 'InstanceVisualizationType', 'InstanceChanged', 'ModuleInfo'] as $key) {
            $this->assertArrayHasKey($key, $instance);
        }
        $this->assertSame(INSTANCE_VISUALIZATION_TYPE_NONE, $instance['InstanceVisualizationType']);
    }

    public function testSetVisualizationTypeIsVisibleInInstance(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        $this->assertTrue(SANDEV_CallSetVisualizationType($id, INSTANCE_VISUALIZATION_TYPE_HTML));
        $this->assertSame(INSTANCE_VISUALIZATION_TYPE_HTML, IPS_GetInstance($id)['InstanceVisualizationType']);

        $strict = IPS_CreateInstance(self::STRICT_DEVICE);
        $this->assertTrue(SANSTR_CallSetVisualizationType($strict, INSTANCE_VISUALIZATION_TYPE_FORM));
        $this->assertSame(INSTANCE_VISUALIZATION_TYPE_FORM, IPS_GetInstance($strict)['InstanceVisualizationType']);
    }

    public function testScriptContainsDocumentedKeys(): void
    {
        $id = IPS_CreateScript(SCRIPTTYPE_PHP);
        IPS_SetScriptContent($id, '<?php echo 1;');
        $script = IPS_GetScript($id);

        $this->assertSame(13, $script['ScriptSize']);
        $this->assertSame(strtoupper(hash('crc32b', '<?php echo 1;')), $script['ScriptCRC']);
        $this->assertArrayHasKey('ScriptUpdated', $script);
    }

    public function testModuleContainsDocumentedKeys(): void
    {
        $module = IPS_GetModule(self::DEVICE);

        foreach (['ModuleID', 'ModuleName', 'ModuleType', 'Vendor', 'Aliases', 'ParentRequirements', 'ChildRequirements', 'Implemented', 'LibraryID', 'Prefix', 'URL', 'Limit', 'Translation'] as $key) {
            $this->assertArrayHasKey($key, $module);
        }
        $this->assertArrayNotHasKey('Class', $module);
    }

    /* Media */
    public function testMediaFunctions(): void
    {
        $id = IPS_CreateMedia(MEDIATYPE_DOCUMENT);
        IPS_SetName($id, 'Manual');
        IPS_SetMediaFile($id, 'media/manual.txt', false);
        IPS_SetMediaContent($id, base64_encode('Hello'));

        $media = IPS_GetMedia($id);
        $this->assertSame($id, $media['MediaID']);
        $this->assertSame('media/manual.txt', $media['MediaFile']);
        $this->assertTrue($media['MediaIsAvailable']);
        $this->assertSame(5, $media['MediaSize']);
        $this->assertSame(base64_encode('Hello'), IPS_GetMediaContent($id));

        $this->assertSame([$id], IPS_GetMediaList());
        $this->assertSame([$id], IPS_GetMediaListByType(MEDIATYPE_DOCUMENT));
        $this->assertSame([], IPS_GetMediaListByType(MEDIATYPE_IMAGE));
        $this->assertSame($id, IPS_GetMediaIDByFile('media/manual.txt'));
        $this->assertSame($id, IPS_GetMediaIDByName('Manual', 0));
        $this->assertFalse(@IPS_GetMediaIDByFile('media/missing.txt'));

        IPS_SetMediaCached($id, true);
        $this->assertTrue(IPS_GetMedia($id)['MediaIsCached']);
    }

    public function testMediaContentWithoutFile(): void
    {
        $id = IPS_CreateMedia(MEDIATYPE_IMAGE);

        $this->expectExceptionMessage('File # does not exist');
        IPS_GetMediaContent($id);
    }

    /* IPSModule */
    public function testRegisterScript(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        $sid = SANDEV_CallRegisterScript($id, 'Script', 'Script', '<?php echo 1;');

        $this->assertTrue(IPS_ScriptExists($sid));
        $this->assertSame($id, IPS_GetParent($sid));
        $this->assertTrue(IPS_GetObject($sid)['ObjectIsReadOnly']);
        $this->assertSame('<?php echo 1;', IPS_GetScriptContent($sid));

        //Registering again keeps the script and updates the content
        $this->assertSame($sid, SANDEV_CallRegisterScript($id, 'Script', 'Script', '<?php echo 2;'));
        $this->assertSame('<?php echo 2;', IPS_GetScriptContent($sid));

        $strict = IPS_CreateInstance(self::STRICT_DEVICE);
        $this->assertTrue(SANSTR_CallRegisterScript($strict, 'Script', 'Script'));
        $this->assertFalse(SANSTR_CallRegisterScript($strict, 'Script', 'Script'));
        $this->assertSame("<?php\n\n//Autogenerated script", IPS_GetScriptContent(IPS_GetObjectIDByIdent('Script', $strict)));
    }

    public function testTimerMethodsHaveDocumentedSignatures(): void
    {
        $this->assertSame(3, (new ReflectionMethod('IPSModule', 'RegisterTimer'))->getNumberOfParameters());
        $this->assertSame(2, (new ReflectionMethod('IPSModule', 'SetTimerInterval'))->getNumberOfParameters());
        $this->assertSame(1, (new ReflectionMethod('IPSModule', 'GetTimerInterval'))->getNumberOfParameters());
    }

    public function testParentFunctionsAreOnlyAvailableForIPSModule(): void
    {
        foreach (['ConnectParent', 'RequireParent', 'ForceParent'] as $method) {
            $this->assertTrue(method_exists('IPSModule', $method));
            $this->assertFalse(method_exists('IPSModuleStrict', $method));
        }
        $this->assertTrue(method_exists('IPSModuleStrict', 'UnregisterOAuth'));
    }

    /* Constants */
    public function testDocumentedConstantsAreDefined(): void
    {
        $constants = [
            'MODULETYPE_VISUALIZATION'         => 6,
            'SCRIPTTYPE_FLOW'                  => 1,
            'SCRIPTTYPE_IPSWORKFLOW'           => 2,
            'MEDIATYPE_IPSVIEW'                => 0,
            'IS_STANDBY'                       => 106,
            'IM_REMOVEATTRIBUTE'               => 10509,
            'SM_UPDATE'                        => 10705,
            'INSTANCE_VISUALIZATION_TYPE_FORM' => 3,
            'DEBUG_FORMAT_BINARY'              => 1,
            'EVENT_CONDITION_OPERATION_OR'     => 1,
            'EVENT_DAY_ALL'                    => 127,
            'COLOR_TRANSPARENT'                => -1,
            'VARIABLE_ACTION_DISABLED'         => 1,
            'AM_FORMRELOAD'                    => 11504,
            'LP_UPDATESUBSCRIPTION'            => 11602
        ];
        foreach ($constants as $name => $value) {
            $this->assertSame($value, constant($name), $name);
        }
    }

    /* Validator */
    public function testValidatorRules(): void
    {
        $validator = new TestCaseSymconValidation('validator');
        $guid = new ReflectionMethod($validator, 'isValidGUID');
        $url = new ReflectionMethod($validator, 'isValidURL');

        $this->assertTrue($guid->invoke($validator, '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A20}'));
        $this->assertFalse($guid->invoke($validator, '5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A20'));
        $this->assertFalse($guid->invoke($validator, '{5c0e3e64-7a5b-4c55-9d7c-0b0f2e8a1a20}'));

        $this->assertTrue($url->invoke($validator, ''));
        $this->assertTrue($url->invoke($validator, 'https://www.symcon.de/'));
        $this->assertFalse($url->invoke($validator, 'www.symcon.de'));
    }
}
