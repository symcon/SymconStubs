<?php

declare(strict_types=1);

class IPSModule
{
    protected $InstanceID;

    private $properties = [];
    private $attributes = [];
    private $references = [];

    //Persisted values which are not registered (anymore), e.g. before Migrate transformed them
    private $unknownConfiguration = [];
    private $unknownAttributes = [];

    private $stubTimeSource = null;
    private $timers = [];

    private $buffer = [];

    private $receiveDataFilter = '';
    private $forwardDataFilter = '';

    private $messages = [];
    private $messageRegistrations = [];

    private $visualizationType = 0 /* None */;

    public function __construct($InstanceID)
    {
        $this->InstanceID = $InstanceID;
    }

    public function Create()
    {
        //Has to be overwritten by implementing module
    }

    public function Destroy()
    {
        //Has to be overwritten by implementing module
    }

    public function Migrate($JSONData)
    {
        //Has to be overwritten by implementing module
        return '';
    }

    public function GetPersistence()
    {
        // This helper is only available in Stub, not in PHP-SDK
        // It builds the persistence like the kernel passes it to Migrate
        $configuration = [];
        foreach ($this->properties as $name => $property) {
            $configuration[$name] = $property['Current'];
        }

        $attributes = [];
        foreach ($this->attributes as $name => $attribute) {
            $attributes[$name] = $attribute['Current'];
        }

        return [
            'attributes'    => array_merge($attributes, $this->unknownAttributes),
            'configuration' => array_merge($configuration, $this->unknownConfiguration)
        ];
    }

    public function ApplyPersistence(array $Persistence)
    {
        // This helper is only available in Stub, not in PHP-SDK
        // It mimics the kernel persisting the result of Migrate. Values for registered
        // properties/attributes are applied directly, unknown values are kept separately
        // and reappear in GetPersistence, like unregistered values in a settings file
        $this->unknownConfiguration = [];
        foreach ($Persistence['configuration'] ?? [] as $name => $value) {
            if (isset($this->properties[$name])) {
                $this->properties[$name]['Current'] = $value;
                $this->properties[$name]['Pending'] = $value;
            } else {
                $this->unknownConfiguration[$name] = $value;
            }
        }

        $this->unknownAttributes = [];
        foreach ($Persistence['attributes'] ?? [] as $name => $value) {
            if (isset($this->attributes[$name])) {
                $this->attributes[$name]['Current'] = $value;
            } else {
                $this->unknownAttributes[$name] = $value;
            }
        }
    }

    public function GetProperty($Name)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        return $this->properties[$Name]['Current'];
    }

    public function SetProperty($Name, $Value)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        //The value is converted to the type of the property like the kernel does
        $this->properties[$Name]['Pending'] = IPS\ValueConverter::toPropertyType($Name, $Value, $this->properties[$Name]['Type']);
    }

    public function GetConfiguration()
    {
        $result = [];
        foreach ($this->properties as $name => $property) {
            $result[$name] = $property['Current'];
        }

        return json_encode($result);
    }

    public function SetConfiguration($Configuration)
    {
        $json = json_decode($Configuration);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Configuration is invalid.');
        }
        if (!is_object($json)) {
            throw new \Exception('Configuration is not of Type Object.');
        }
        foreach ($json as $name => $value) {
            if (!is_scalar($value)) {
                throw new \Exception(sprintf('Unsupported type for property %s in configuration', $name));
            }
            $this->SetProperty($name, $value);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //Has to be overwritten by implementing module
    }

    public function HasChanges()
    {
        foreach ($this->properties as &$property) {
            if ($property['Current'] != $property['Pending']) {
                return true;
            }
        }

        return false;
    }

    public function ResetChanges()
    {
        foreach ($this->properties as &$property) {
            $property['Pending'] = $property['Current'];
        }
    }

    public function ApplyChanges()
    {
        foreach ($this->properties as &$property) {
            $property['Current'] = $property['Pending'];
        }
    }

    public function GetReceiveDataFilter()
    {
        // This getter function is only availabe in Stub, not in PHP-SDK
        // It is required to utilize the ReceiveDataFilter
        return $this->receiveDataFilter;
    }

    public function ReceiveData($JSONString)
    {
        //Has to be overwritten by implementing module
    }

    public function ForwardData($JSONString)
    {
        //Has to be overwritten by implementing module
        return '';
    }

    public function GetForwardDataFilter()
    {
        // This getter function is only availabe in Stub, not in PHP-SDK
        // It is required to utilize the ForwardDataFilter
        return $this->forwardDataFilter;
    }

    public function RequestAction($Ident, $Value)
    {
        //Has to be overwritten by implementing module
    }

    public function GetCompatibleParents()
    {
        return '{}';
    }

    public function GetConfigurationForm()
    {
        return '{}';
    }

    public function GetConfigurationForParent()
    {
        return '{}';
    }

    public function Translate($Text)
    {
        return $Text;
    }

    public function GetReferenceList()
    {
        return $this->references;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function SetVisualizationType(int $Type)
    {
        $this->visualizationType = $Type;
    }

    public function SetStubTimeSource(callable $timeSource)
    {
        // This helper is only available in Stub, not in PHP-SDK
        // IPSModuleStrict uses it to route getTime back to the module under test
        $this->stubTimeSource = $timeSource;
    }

    protected function GetIDForIdent($Ident)
    {
        return IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
    }

    protected function RegisterPropertyBoolean($Name, $DefaultValue)
    {
        $this->RegisterProperty($Name, $DefaultValue, 0);
    }

    protected function RegisterPropertyInteger($Name, $DefaultValue)
    {
        $this->RegisterProperty($Name, $DefaultValue, 1);
    }

    protected function RegisterPropertyFloat($Name, $DefaultValue)
    {
        $this->RegisterProperty($Name, $DefaultValue, 2);
    }

    protected function RegisterPropertyString($Name, $DefaultValue)
    {
        $this->RegisterProperty($Name, $DefaultValue, 3);
    }

    protected function RegisterAttributeBoolean($Name, $DefaultValue)
    {
        $this->RegisterAttribute($Name, $DefaultValue, 0);
    }

    protected function RegisterAttributeInteger($Name, $DefaultValue)
    {
        $this->RegisterAttribute($Name, $DefaultValue, 1);
    }

    protected function RegisterAttributeFloat($Name, $DefaultValue)
    {
        $this->RegisterAttribute($Name, $DefaultValue, 2);
    }

    protected function RegisterAttributeString($Name, $DefaultValue)
    {
        $this->RegisterAttribute($Name, $DefaultValue, 3);
    }

    protected function RegisterOnceTimer(string $Ident, string $ScriptText)
    {
        IPS_RunScriptTextEx($ScriptText, ['TARGET' => $this->InstanceID]);
    }

    protected function RegisterTimer(string $Ident, int $Milliseconds, string $ScriptText, ?int $start = null)
    {
        if (!$this->IsCreating()) {
            throw new Exception('Timer registration is only possible in the Create method');
        }

        if (isset($this->timers[$Ident])) {
            throw new Exception(sprintf('Timer %s already exists', $Ident));
        }

        $this->timers[$Ident] = [
            'millis' => $Milliseconds,
            'start'  => $start ?? $this->getTime()
        ];
    }

    protected function SetTimerInterval(string $Ident, int $Milliseconds, ?int $start = null)
    {
        if ($Milliseconds < 0) {
            throw new Exception('Timer interval must not be negative!');
        }

        if (!isset($this->timers[$Ident])) {
            throw new Exception(sprintf('Timer %s does not exist', $Ident));
        }

        $this->timers[$Ident] = [
            'millis' => $Milliseconds,
            'start'  => $start ?? $this->getTime()
        ];
    }

    protected function GetTimerInterval(string $Ident, ?int $currentTime = null)
    {
        if (!isset($this->timers[$Ident])) {
            throw new Exception(sprintf('Timer %s does not exist', $Ident));
        }

        //Like the kernel, this returns the configured interval and not the remaining time
        return $this->timers[$Ident]['millis'];
    }

    protected function RegisterScript($Ident, $Name, $Content = '', $Position = 0)
    {
        //How and why do we want to test this?
        return 0;
    }

    protected function RegisterVariableBoolean($Ident, $Name, $ProfileOrPresentation = '', $Position = 0)
    {
        return $this->RegisterVariable($Ident, $Name, 0, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableInteger($Ident, $Name, $ProfileOrPresentation = '', $Position = 0)
    {
        return $this->RegisterVariable($Ident, $Name, 1, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableFloat($Ident, $Name, $ProfileOrPresentation = '', $Position = 0)
    {
        return $this->RegisterVariable($Ident, $Name, 2, $ProfileOrPresentation, $Position);
    }

    protected function RegisterVariableString($Ident, $Name, $ProfileOrPresentation = '', $Position = 0)
    {
        return $this->RegisterVariable($Ident, $Name, 3, $ProfileOrPresentation, $Position);
    }

    protected function UnregisterVariable($Ident)
    {
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);
        if (($vid === false) || !IPS_VariableExists($vid)) {
            return false;
        } //bail out
        IPS_DeleteVariable($vid);
        return true;
    }

    protected function MaintainVariable($Ident, $Name, $Type, $Profile, $Position, $Keep)
    {
        if ($Keep) {
            return $this->RegisterVariable($Ident, $Name, $Type, $Profile, $Position);
        } else {
            return $this->UnregisterVariable($Ident);
        }
    }

    protected function EnableAction($Ident)
    {
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);
        if (($vid === false) || !IPS_VariableExists($vid)) {
            return false;
        }

        IPS\VariableManager::setVariableAction($vid, $this->InstanceID);
        return true;
    }

    protected function DisableAction($Ident)
    {
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);
        if (($vid === false) || !IPS_VariableExists($vid)) {
            return false;
        }

        IPS\VariableManager::setVariableAction($vid, 0);
        return true;
    }

    protected function MaintainAction($Ident, $Keep)
    {
        if ($Keep) {
            return $this->EnableAction($Ident);
        } else {
            return $this->DisableAction($Ident);
        }
    }

    protected function ReadPropertyBoolean($Name)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        if ($this->properties[$Name]['Type'] != 0) {
            throw new Exception(sprintf('Property %s is not of type Boolean', $Name));
        }

        return $this->properties[$Name]['Current'];
    }

    protected function ReadPropertyInteger($Name)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        if ($this->properties[$Name]['Type'] != 1) {
            throw new Exception(sprintf('Property %s is not of type Integer', $Name));
        }

        return $this->properties[$Name]['Current'];
    }

    protected function ReadPropertyFloat($Name)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        if ($this->properties[$Name]['Type'] != 2) {
            throw new Exception(sprintf('Property %s is not of type Float', $Name));
        }

        return $this->properties[$Name]['Current'];
    }

    protected function ReadPropertyString($Name)
    {
        if (!isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s not found', $Name));
        }

        if ($this->properties[$Name]['Type'] != 3) {
            throw new Exception(sprintf('Property %s is not of type String', $Name));
        }

        return $this->properties[$Name]['Current'];
    }

    protected function ReadAttributeBoolean($Name)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 0) {
            throw new Exception(sprintf('Attribute %s is not of type Boolean', $Name));
        }

        return $this->attributes[$Name]['Current'];
    }

    protected function ReadAttributeInteger($Name)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 1) {
            throw new Exception(sprintf('Attribute %s is not of type Integer', $Name));
        }

        return $this->attributes[$Name]['Current'];
    }

    protected function ReadAttributeFloat($Name)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 2) {
            throw new Exception(sprintf('Attribute %s is not of type Float', $Name));
        }

        return $this->attributes[$Name]['Current'];
    }

    protected function ReadAttributeString($Name)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 3) {
            throw new Exception(sprintf('Attribute %s is not of type String', $Name));
        }

        return $this->attributes[$Name]['Current'];
    }

    protected function WriteAttributeBoolean($Name, bool $Value)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 0) {
            throw new Exception(sprintf('Attribute %s is not of type Boolean', $Name));
        }

        $this->attributes[$Name]['Current'] = $Value;
    }

    protected function WriteAttributeInteger($Name, int $Value)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 1) {
            throw new Exception(sprintf('Attribute %s is not of type Integer', $Name));
        }

        $this->attributes[$Name]['Current'] = $Value;
    }

    protected function WriteAttributeFloat($Name, float $Value)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 2) {
            throw new Exception(sprintf('Attribute %s is not of type Float', $Name));
        }

        $this->attributes[$Name]['Current'] = $Value;
    }

    protected function WriteAttributeString($Name, string $Value)
    {
        if (!isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s not found', $Name));
        }

        if ($this->attributes[$Name]['Type'] != 3) {
            throw new Exception(sprintf('Attribute %s is not of type String', $Name));
        }

        $this->attributes[$Name]['Current'] = $Value;
    }

    protected function SendDataToParent($Data)
    {
        $dataID = $this->GetDataFlowDataID($Data);

        $connectionID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($connectionID == 0) {
            throw new Exception('No parent instance was configured which could process our data');
        }
        if (!IPS_InstanceExists($connectionID)) {
            throw new Exception(sprintf('Cannot find instance #%d which should process our data', $this->InstanceID));
        }
        if (!in_array($dataID, IPS_GetModule(IPS_GetInstance($connectionID)['ModuleInfo']['ModuleID'])['Implemented'])) {
            throw new Exception('Data is not compatible with parent');
        }

        $interface = IPS\InstanceManager::getInstanceInterface($connectionID);
        $Data = $this->TranslateDataFlowBuffer($Data, $interface);
        // check if forwardDataFilter applys
        if (preg_match('/' . $interface->GetForwardDataFilter() . '/', $Data) == 1) {
            return $interface->ForwardData($Data);
        }
        return '';
    }

    protected function SendDataToChildren($Data)
    {
        $dataID = $this->GetDataFlowDataID($Data);

        $ids = IPS_GetInstanceList();
        $return = [];
        foreach ($ids as $id) {
            if (IPS_GetInstance($id)['ConnectionID'] == $this->InstanceID) {
                //Children only receive data, which they implement
                if (!in_array($dataID, IPS_GetModule(IPS_GetInstance($id)['ModuleInfo']['ModuleID'])['Implemented'])) {
                    continue;
                }
                $interface = IPS\InstanceManager::getInstanceInterface($id);
                $childData = $this->TranslateDataFlowBuffer($Data, $interface);
                // check if receiveDataFilter applys
                if (preg_match('/' . $interface->GetReceiveDataFilter() . '/', $childData) == 1) {
                    $singleReturn = $interface->ReceiveData($childData);
                    if ($singleReturn != '') {
                        $return[] = $singleReturn;
                    }
                }
            }
        }
        return $return;
    }

    protected function ConnectParent($ModuleID)
    {
        if (IPS_GetInstance($this->InstanceID)['ConnectionID'] == 0) {
            $ids = IPS_GetInstanceListByModuleID($ModuleID);
            if (count($ids) > 0) {
                IPS_ConnectInstance($this->InstanceID, $ids[0]);
                return;
            }

            //Let this function create our parent
            $this->RequireParent($ModuleID);
        }
    }

    protected function RequireParent($ModuleID)
    {
        if (IPS_GetInstance($this->InstanceID)['ConnectionID'] == 0) {
            $id = IPS_CreateInstance($ModuleID);
            $module = IPS_GetModule($ModuleID);
            IPS_SetName($id, $module['ModuleName']);
            IPS_ConnectInstance($this->InstanceID, $id);
        }
    }

    protected function ForceParent($ModuleID)
    {
        //Cleanup parent, if not correct
        $connectionID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($connectionID != 0) {
            $instance = IPS_GetInstance($connectionID);
            if ($instance['ModuleInfo']['ModuleID'] != $ModuleID) {
                IPS_DisconnectInstance($this->InstanceID);

                //Only clean up instance, if no other instance is connected
                $ids = IPS_GetInstanceList();
                $inUse = false;
                foreach ($ids as $id) {
                    if (IPS_GetInstance($id)['ConnectionID'] == $connectionID) {
                        $inUse = true;
                        break;
                    }
                }
                if (!$inUse) {
                    IPS_DeleteInstance($connectionID);
                }
            }
        }

        //Let this function create our parent
        $this->RequireParent($ModuleID);
    }

    protected function SetStatus($Status)
    {
        if (($Status != 102 /* IS_ACTIVE */) && ($Status != 104 /* IS_INACTIVE */) && ($Status != 106 /* IS_STANDBY */) && ($Status < 200 /* IS_EBASE */)) {
            throw new Exception('Only Active, Inactive, StandBy and Error codes are allowed');
        }

        IPS\InstanceManager::setStatus($this->InstanceID, $Status);
    }

    protected function GetStatus()
    {
        return IPS\InstanceManager::getStatus($this->InstanceID);
    }

    protected function SetSummary($Summary)
    {
        IPS\ObjectManager::setSummary($this->InstanceID, $Summary);
    }

    protected function SetBuffer($Name, $Data)
    {
        if ($Data === '') {
            unset($this->buffer[$Name]);
            return;
        }

        if (strlen($Data) > 512 * 1024) {
            $Data = substr($Data, -512 * 1024);
        }
        $this->buffer[$Name] = $Data;

        //The kernel clears all buffers if their size goes over the hard limit of 1024kB
        $totalSize = 0;
        foreach ($this->buffer as $name => $value) {
            $totalSize += strlen($name) + strlen($value);
        }
        if ($totalSize > 1024 * 1024) {
            $this->buffer = [];
        }
    }

    protected function GetBufferList()
    {
        return array_keys($this->buffer);
    }

    protected function GetBuffer($Name)
    {
        if (isset($this->buffer[$Name])) {
            return $this->buffer[$Name];
        } else {
            return '';
        }
    }

    protected function SendDebug($Message, $Data, $Format)
    {
        IPS_SendDebug($this->InstanceID, $Message, $Data, $Format);
    }

    protected function GetMessageList()
    {
        return $this->messageRegistrations;
    }

    protected function RegisterMessage($SenderID, $Message)
    {
        if (!in_array($Message, $this->messageRegistrations[$SenderID] ?? [])) {
            $this->messageRegistrations[$SenderID][] = $Message;
        }
    }

    protected function UnregisterMessage($SenderID, $Message)
    {
        if (!isset($this->messageRegistrations[$SenderID])) {
            return;
        }

        $this->messageRegistrations[$SenderID] = array_values(array_diff($this->messageRegistrations[$SenderID], [$Message]));
        if (count($this->messageRegistrations[$SenderID]) == 0) {
            unset($this->messageRegistrations[$SenderID]);
        }
    }

    protected function SetReceiveDataFilter($RequiredRegexMatch)
    {
        $this->receiveDataFilter = $RequiredRegexMatch;
    }

    protected function SetForwardDataFilter($RequiredRegexMatch)
    {
        $this->forwardDataFilter = $RequiredRegexMatch;
    }

    protected function RegisterReference(int $ID)
    {
        if (!in_array($ID, $this->references)) {
            $this->references[] = $ID;
        }
    }

    protected function UnregisterReference(int $ID)
    {
        if (in_array($ID, $this->references)) {
            array_splice($this->references, array_search($ID, $this->references), 1);
        }
    }

    protected function ReloadForm()
    {
    }

    protected function UpdateFormField($Field, $Parameter, $Value)
    {
    }

    protected function UpdateVisualizationValue($Value)
    {
        $this->postMessage(10541, [$Value]);
    }

    protected function GetValue(string $Ident)
    {
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);
        if ($vid === false) {
            throw new Exception(sprintf('Object with ident %s could not be found', $Ident));
        }

        return GetValue($vid);
    }

    protected function SetValue(string $Ident, $Value)
    {
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);
        if ($vid === false) {
            throw new Exception(sprintf('Object with ident %s could not be found', $Ident));
        }

        //The kernel writes directly, so variables marked as read-only can be changed by their module
        IPS\VariableManager::writeVariable($vid, $Value);
        return true;
    }

    protected function LogMessage($Message, $Type)
    {
        if (!in_array($Type, [KL_MESSAGE, KL_NOTIFY, KL_WARNING, KL_ERROR, KL_DEBUG])) {
            throw new Exception('Unsupported message type');
        }

        IPS\LogServer::logMessage(strval($this->InstanceID), $Message, $Type);
    }

    protected function HasActiveParent()
    {
        $instanceID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($instanceID == 0) {
            return false;
        }
        while (true) {
            if ($instanceID == 0) {
                break;
            }
            if (IPS_GetInstance($instanceID)['InstanceStatus'] != IS_ACTIVE) {
                return false;
            }
            $instanceID = IPS_GetInstance($instanceID)['ConnectionID'];
        }
        return IPS\InstanceManager::getStatus(IPS_GetInstance($this->InstanceID)['ConnectionID']) == IS_ACTIVE;
    }

    protected function getTime()
    {
        if ($this->stubTimeSource !== null) {
            return ($this->stubTimeSource)();
        }

        throw new Exception('getTime needs to be implemented by module under test');
    }

    protected function postMessage($Message, $Data)
    {
        $this->messages[] = [
            'TimeStamp' => 0,
            'SenderID'  => $this->InstanceID,
            'Message'   => $Message,
            'Data'      => $Data
        ];
    }

    //Mimics the kernel validating the data before it is sent through the data flow
    private function GetDataFlowDataID($Data)
    {
        $json = json_decode($Data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON parse error');
        }
        if (!is_object($json)) {
            throw new Exception('JSON object is required');
        }
        if (!isset($json->DataID) || !is_string($json->DataID)) {
            throw new Exception('Invalid DataID');
        }

        return $json->DataID;
    }

    private function IsCreating()
    {
        return IPS_GetInstance($this->InstanceID)['InstanceStatus'] == IS_CREATING;
    }

    //Mimics the kernel translating the Buffer between the data flow encodings:
    //IPSModule uses UTF-8 (utf8_encode/utf8_decode), IPSModuleStrict uses HEX (bin2hex/hex2bin)
    private function TranslateDataFlowBuffer($Data, $Receiver)
    {
        $senderStrict = IPS\InstanceManager::getInstanceInterface($this->InstanceID) instanceof IPSModuleStrict;
        $receiverStrict = $Receiver instanceof IPSModuleStrict;

        if ($senderStrict == $receiverStrict) {
            return $Data;
        }

        $json = json_decode($Data, true);
        if (!is_array($json) || !isset($json['Buffer']) || !is_string($json['Buffer'])) {
            return $Data;
        }

        $binary = $senderStrict ? hex2bin($json['Buffer']) : utf8_decode($json['Buffer']);
        $json['Buffer'] = $receiverStrict ? bin2hex($binary) : utf8_encode($binary);

        return json_encode($json);
    }

    private function RegisterProperty($Name, $DefaultValue, $Type)
    {
        if (!$this->IsCreating()) {
            throw new Exception('Property registration is only possible in the Create method');
        }

        if (isset($this->properties[$Name])) {
            throw new Exception(sprintf('Property %s is already registered', $Name));
        }

        $DefaultValue = IPS\ValueConverter::toVariableType($DefaultValue, $Type, 'DefaultValue');
        $this->properties[$Name] = [
            'Type'    => $Type,
            'Default' => $DefaultValue,
            'Current' => $DefaultValue,
            'Pending' => $DefaultValue
        ];
    }

    private function RegisterAttribute($Name, $DefaultValue, $Type)
    {
        if (!$this->IsCreating()) {
            throw new Exception('Attribute registration is only possible in the Create method');
        }

        if (isset($this->attributes[$Name])) {
            throw new Exception(sprintf('Attribute %s is already registered', $Name));
        }

        $DefaultValue = IPS\ValueConverter::toVariableType($DefaultValue, $Type, 'DefaultValue');
        $this->attributes[$Name] = [
            'Type'    => $Type,
            'Default' => $DefaultValue,
            'Current' => $DefaultValue
        ];
    }

    private function RegisterVariable($Ident, $Name, $Type, $ProfileOrPresentation, $Position)
    {
        if (is_string($ProfileOrPresentation) && ($ProfileOrPresentation !== '')) {
            //prefer system profiles
            if ($ProfileOrPresentation[0] != '~') {
                if (IPS_VariableProfileExists('~' . $ProfileOrPresentation)) {
                    $ProfileOrPresentation = '~' . $ProfileOrPresentation;
                }
            }

            if (!IPS_VariableProfileExists($ProfileOrPresentation)) {
                throw new Exception('Profile with name ' . $ProfileOrPresentation . ' does not exist');
            }

            //make typecheck
            if (IPS_GetVariableProfile($ProfileOrPresentation)['ProfileType'] != $Type) {
                throw new Exception('Profile with name ' . $ProfileOrPresentation . ' is not of type ' . $Type);
            }
        }

        //search for already available variables with proper ident
        $vid = IPS\ObjectManager::findObjectIDByIdent($Ident, $this->InstanceID);

        //properly update variableID
        if ($vid === false) {
            $vid = 0;
        }

        //we have a variable with the proper ident. check if it fits
        if ($vid > 0) {
            //check if we really have a variable. the kernel bails out without an error
            if (!IPS_VariableExists($vid)) {
                return $vid;
            }
            //check for type mismatch
            if (IPS_GetVariable($vid)['VariableType'] != $Type) {
                //mismatch detected. delete this one. we will create a new below
                IPS_DeleteVariable($vid);
                //this will ensure, that a new one is created
                $vid = 0;
            }
        }

        //we need to create one
        if ($vid == 0) {
            $vid = IPS_CreateVariable($Type);

            //configure it
            IPS_SetParent($vid, $this->InstanceID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $Name);
            IPS_SetPosition($vid, $Position);

            //Variables of IPSModuleStrict can only be changed with $this->SetValue. IPSModule clears the flag for backwards compatibility
            if (IPS\InstanceManager::getInstanceInterface($this->InstanceID) instanceof IPSModuleStrict) {
                IPS\ObjectManager::setReadOnly($vid, true);
            }
        }

        //update variable profile. profiles may be changed in module development.
        //this update does not affect any custom profile choices
        if (is_array($ProfileOrPresentation)) {
            IPS\VariableManager::setVariablePresentation($vid, $ProfileOrPresentation);
        }
        else {
            IPS\VariableManager::setVariableProfile($vid, $ProfileOrPresentation);
        }

        return $vid;
    }
}
