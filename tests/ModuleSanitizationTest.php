<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class ModuleSanitizationTest extends TestCase
{
    private const SPLITTER = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A10}';
    private const DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A20}';
    private const STRICT_DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A30}';
    private const OTHER_DEVICE = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1A40}';

    private const TX = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1AA1}';
    private const RX = '{5C0E3E64-7A5B-4C55-9D7C-0B0F2E8A1AB1}';

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/SanitizationStubs/library.json');
        parent::setUp();
    }

    /* Configuration */
    public function testSetPropertyConvertsToPropertyType(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        IPS_SetProperty($id, 'Port', '8080');
        IPS_SetProperty($id, 'Factor', 2);
        IPS_SetProperty($id, 'Active', 'True');
        IPS_SetProperty($id, 'Host', false);
        IPS_ApplyChanges($id);

        $this->assertSame(8080, IPS_GetProperty($id, 'Port'));
        $this->assertSame(2.0, IPS_GetProperty($id, 'Factor'));
        $this->assertTrue(IPS_GetProperty($id, 'Active'));
        $this->assertSame('False', IPS_GetProperty($id, 'Host'));
        $this->assertSame(8080, SANDEV_CallReadPropertyInteger($id, 'Port'));
    }

    public function testDefaultValuesAreConverted(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->assertSame(1.0, SANDEV_CallReadPropertyFloat($id, 'Factor'));
    }

    public function testSetPropertyRejectsUnconvertibleValues(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Cannot set property Port: stod');
        IPS_SetProperty($id, 'Port', 'abc');
    }

    public function testSetConfigurationRejectsUnknownProperties(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Property Prot not found');
        IPS_SetConfiguration($id, json_encode(['Prot' => 8080]));
    }

    public function testSetConfigurationRejectsInvalidConfigurations(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        foreach (['{invalid' => 'Configuration is invalid.', '[1]' => 'Configuration is not of Type Object.', '{"Host":["a"]}' => 'Unsupported type for property Host in configuration'] as $configuration => $message) {
            try {
                IPS_SetConfiguration($id, $configuration);
                $this->fail('Setting the configuration must fail');
            } catch (Exception $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    /* Registration */
    public function testPropertiesCanOnlyBeRegisteredInCreate(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Property registration is only possible in the Create method');
        SANDEV_CallRegisterPropertyInteger($id, 'Late', 0);
    }

    public function testAttributesCanOnlyBeRegisteredInCreate(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Attribute registration is only possible in the Create method');
        SANDEV_CallRegisterAttributeInteger($id, 'Late', 0);
    }

    public function testTimersCanOnlyBeRegisteredInCreate(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Timer registration is only possible in the Create method');
        SANDEV_CallRegisterTimer($id, 'Late', 1000);
    }

    public function testInstanceIsActiveAfterCreation(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->assertSame(IS_ACTIVE, IPS_GetInstance($id)['InstanceStatus']);
    }

    /* Timers */
    public function testGetTimerIntervalReturnsInterval(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->assertSame(0, SANDEV_CallGetTimerInterval($id, 'Update'));
        SANDEV_CallSetTimerInterval($id, 'Update', 5000);
        $this->assertSame(5000, SANDEV_CallGetTimerInterval($id, 'Update'));
    }

    public function testTimerIntervalMustNotBeNegative(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Timer interval must not be negative!');
        SANDEV_CallSetTimerInterval($id, 'Update', -1);
    }

    public function testUnknownTimer(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Timer Missing does not exist');
        SANDEV_CallSetTimerInterval($id, 'Missing', 1000);
    }

    /* Status / Log / Buffer */
    public function testSetStatusOnlyAcceptsValidCodes(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        SANDEV_CallSetStatus($id, IS_INACTIVE);
        SANDEV_CallSetStatus($id, 201);

        $this->expectExceptionMessage('Only Active, Inactive, StandBy and Error codes are allowed');
        SANDEV_CallSetStatus($id, IS_CREATING);
    }

    public function testLogMessageRejectsUnsupportedTypes(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        SANDEV_CallLogMessage($id, 'Fine', KL_MESSAGE);

        $this->expectExceptionMessage('Unsupported message type');
        SANDEV_CallLogMessage($id, 'Not allowed', KL_CUSTOM);
    }

    public function testEmptyBufferIsRemoved(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        SANDEV_CallSetBuffer($id, 'Data', 'abc');
        $this->assertSame(['Data'], SANDEV_CallGetBufferList($id));

        SANDEV_CallSetBuffer($id, 'Data', '');
        $this->assertSame([], SANDEV_CallGetBufferList($id));
    }

    /* Variables / Actions */
    public function testEnableActionOnUnknownIdentReturnsFalse(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);

        $this->assertFalse(SANDEV_CallEnableAction($id, 'Missing'));
        $this->assertTrue(SANDEV_CallEnableAction($id, 'Value'));
    }

    public function testRegisterVariableOnIdentOfOtherObjectBailsOut(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        $category = IPS_CreateCategory();
        IPS_SetParent($category, $id);
        IPS_SetIdent($category, 'Used');

        $this->assertSame($category, SANDEV_CallRegisterVariableInteger($id, 'Used', 'Used'));
        $this->assertTrue(IPS_CategoryExists($category));
    }

    public function testLegacyVariablesAreNotReadOnly(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        $vid = IPS_GetObjectIDByIdent('Value', $id);

        $this->assertFalse(IPS_GetObject($vid)['ObjectIsReadOnly']);
        SetValue($vid, 5);
        $this->assertSame(5, GetValue($vid));
    }

    public function testModuleSetValueConverts(): void
    {
        $id = IPS_CreateInstance(self::DEVICE);
        SANDEV_CallSetValue($id, 'Value', '42');

        $this->assertSame(42, GetValue(IPS_GetObjectIDByIdent('Value', $id)));
    }

    public function testStrictVariablesAreReadOnly(): void
    {
        $id = IPS_CreateInstance(self::STRICT_DEVICE);
        $vid = IPS_GetObjectIDByIdent('Value', $id);
        $this->assertTrue(IPS_GetObject($vid)['ObjectIsReadOnly']);

        //The module itself is still allowed to change the value
        $this->assertTrue(SANSTR_CallSetValue($id, 'Value', 7));
        $this->assertSame(7, GetValue($vid));

        $this->expectExceptionMessage('Variable is marked as read-only and cannot be changed');
        SetValue($vid, 8);
    }

    public function testStrictRegisterVariableReportsCreation(): void
    {
        $id = IPS_CreateInstance(self::STRICT_DEVICE);

        $this->assertFalse(SANSTR_CallRegisterVariableInteger($id, 'Value', 'Value'));
        $this->assertTrue(SANSTR_CallRegisterVariableInteger($id, 'Other', 'Other'));
        //A changed type replaces the variable, which counts as created
        $this->assertTrue(SANSTR_CallRegisterVariableString($id, 'Other', 'Other'));
        $this->assertTrue(SANSTR_CallUnregisterVariable($id, 'Other'));
        $this->assertFalse(SANSTR_CallUnregisterVariable($id, 'Other'));
    }

    /* Connections */
    public function testConnectCompatibleInstances(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $this->assertSame($splitter, IPS_GetInstance($device)['ConnectionID']);
    }

    public function testConnectRejectsIncompatibleDataflow(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $other = IPS_CreateInstance(self::OTHER_DEVICE);

        $this->expectExceptionMessage('Dataflow is incompatible');
        IPS_ConnectInstance($other, $splitter);
    }

    public function testConnectRejectsDeviceAsParent(): void
    {
        $first = IPS_CreateInstance(self::DEVICE);
        $second = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Device/Configuration/Discovery/Visualization/Splitter instances can only be connected to other Splitter or I/O instances');
        IPS_ConnectInstance($first, $second);
    }

    public function testConnectRejectsMissingParent(): void
    {
        $device = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('Instance #12345 does not exist');
        IPS_ConnectInstance($device, 12345);
    }

    public function testConnectRejectsSecondParent(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $this->expectExceptionMessage(sprintf('Instance #%d already has a parent', $device));
        IPS_ConnectInstance($device, $splitter);
    }

    public function testDisconnectRequiresParent(): void
    {
        $device = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage(sprintf('Instance #%d does not have a parent', $device));
        IPS_DisconnectInstance($device);
    }

    /* Data flow */
    public function testSendDataToParent(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $data = json_encode(['DataID' => self::TX, 'Buffer' => 'abc']);
        $this->assertSame('forwarded', SANDEV_CallSendDataToParent($device, $data));
        $this->assertSame([$data], SANSPL_GetForwarded($splitter));
    }

    public function testSendDataToParentRequiresParent(): void
    {
        $device = IPS_CreateInstance(self::DEVICE);

        $this->expectExceptionMessage('No parent instance was configured which could process our data');
        SANDEV_CallSendDataToParent($device, json_encode(['DataID' => self::TX]));
    }

    public function testSendDataToParentRequiresCompatibleDataID(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $this->expectExceptionMessage('Data is not compatible with parent');
        SANDEV_CallSendDataToParent($device, json_encode(['DataID' => self::RX]));
    }

    public function testSendDataRequiresValidPacket(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        foreach (['{invalid' => 'JSON parse error', '[1]' => 'JSON object is required', '{"Buffer":"abc"}' => 'Invalid DataID'] as $data => $message) {
            try {
                SANDEV_CallSendDataToParent($device, $data);
                $this->fail('Sending the data must fail');
            } catch (Exception $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public function testSendDataToChildrenSkipsIncompatibleChildren(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $this->assertSame(['device'], SANSPL_SendToChildren($splitter, json_encode(['DataID' => self::RX])));
        $this->assertSame([], SANSPL_SendToChildren($splitter, json_encode(['DataID' => self::TX])));
    }

    public function testStrictChildWithoutReceiveData(): void
    {
        $splitter = IPS_CreateInstance(self::SPLITTER);
        $device = IPS_CreateInstance(self::STRICT_DEVICE);
        IPS_ConnectInstance($device, $splitter);

        $this->assertSame([], SANSPL_SendToChildren($splitter, json_encode(['DataID' => self::RX, 'Buffer' => 'abc'])));
    }
}
