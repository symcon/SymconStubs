<?php

declare(strict_types=1);
include_once __DIR__ . '/ModuleStubs.php';

class IPSModulePublic extends IPSModule
{
    public function __call($name, $arguments)
    {
        if (!in_array($name, get_class_methods($this))) {
            throw "Method $name is not implemented";
        }
        return $this->{$name}(...$arguments);
    }
}

class IPSModuleStrict
{
    protected $InstanceID;
    private IPSModulePublic $module;
    private array $protectedMethods;

    public function __construct(int $InstanceID)
    {
        $this->module = new IPSModulePublic($InstanceID);
        $this->InstanceID = $InstanceID;
    }

    public function Create(): void
    {
        $this->module->Create();
    }

    public function Destroy(): void
    {
        $this->module->Destroy();
    }

    public function GetProperty(string $Name): mixed
    {
        return $this->module->GetProperty($Name);
    }

    public function SetProperty(string $Name, mixed $Value): void
    {
        $this->module->SetProperty($Name, $Value);
    }

    public function GetConfiguration(): string
    {
        return $this->module->GetConfiguration();
    }

    public function SetConfiguration(string $Configuration): void
    {
        $this->module->SetConfiguration($Configuration);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->module->MessageSink($TimeStamp, $SenderID, $Message, $Data);
    }

    public function HasChanges(): bool
    {
        return $this->module->HasChanges();
    }

    public function ResetChanges(): void
    {
        $this->module->ResetChanges();
    }

    public function ApplyChanges(): void
    {
        $this->module->ApplyChanges();
    }

    public function GetReceiveDataFilter(): string
    {
        return $this->module->GetReceiveDataFilter();
    }

    public function ReceiveData(string $JSONString): string
    {
        return $this->module->ReceiveData($JSONString);
    }

    public function ForwardData(string $JSONString): string
    {
        return $this->module->ForwardData($JSONString);
    }

    public function GetForwardDataFilter(): string
    {
        return $this->module->GetForwardDataFilter();
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->module->RequestAction($Ident, $Value);
    }

    public function GetConfigurationForm(): string
    {
        return $this->module->GetConfigurationForm();
    }

    public function GetConfigurationForParent(): string
    {
        return $this->module->GetConfigurationForParent();
    }

    public function GetCompatibleParents(): string
    {
        return $this->module->GetConfigurationForParent();
    }

    public function Translate(string $Text): string
    {
        return $this->module->Translate($Text);
    }

    public function GetReferenceList(): array
    {
        return $this->module->GetReferenceList();
    }

    public function getMessages() : array
    {
        return $this->module->getMessages();
    }

    public function SetVisualizationType(int $Type): void
    {
        $this->module->SetVisualizationType($Type);
    }

    protected function GetIDForIdent(string $Ident): int|false
    {
        return $this->module->GetIDForIdent($Ident);
    }

    protected function RegisterPropertyBoolean(string $Name, bool $DefaultValue): bool
    {
        $this->module->RegisterPropertyBoolean($Name, $DefaultValue);
        return true;
    }

    protected function RegisterPropertyInteger(string $Name, int $DefaultValue): bool
    {
        $this->module->RegisterPropertyInteger($Name, $DefaultValue);
        return true;
    }

    protected function RegisterPropertyFloat(string $Name, float $DefaultValue): bool
    {
        $this->module->RegisterPropertyFloat($Name, $DefaultValue);
        return true;
    }

    protected function RegisterPropertyString(string $Name, string $DefaultValue): bool
    {
        $this->module->RegisterPropertyString($Name, $DefaultValue);
        return true;
    }

    protected function RegisterAttributeBoolean(string $Name, bool $DefaultValue): bool
    {
        $this->module->RegisterAttributeBoolean($Name, $DefaultValue);
        return true;
    }

    protected function RegisterAttributeInteger(string $Name, int $DefaultValue): bool
    {
        $this->module->RegisterAttributeInteger($Name, $DefaultValue);
        return true;
    }

    protected function RegisterAttributeFloat(string $Name, float $DefaultValue): bool
    {
        $this->module->RegisterAttributeFloat($Name, $DefaultValue);
        return true;
    }

    protected function RegisterAttributeString(string $Name, string $DefaultValue): bool
    {
        $this->module->RegisterAttributeString($Name, $DefaultValue);
        return true;
    }

    protected function RegisterOnceTimer(string $Ident, string $ScriptText): bool
    {
        $this->module->RegisterOnceTimer($Ident, $ScriptText);
        return true;
    }

    protected function RegisterTimer(string $Ident, int $Milliseconds, string $ScriptText): bool
    {
        $this->module->RegisterTimer($Ident, $Milliseconds, $ScriptText, $this->getTime());
        return true;
    }

    protected function SetTimerInterval(string $Ident, int $Milliseconds): bool
    {
        $this->module->SetTimerInterval($Ident, $Milliseconds, $this->getTime());
        return true;
    }

    protected function GetTimerInterval(string $Ident): int
    {
        return $this->module->GetTimerInterval($Ident, $this->getTime());
    }

    protected function RegisterScript(string $Ident, string $Name, string $Content = '', int $Position = 0): bool
    {
        $scriptExists = (@$this->module->GetIDForIdent($Ident) !== false);
        $this->module->RegisterScript($Ident, $Name, $Content, $Position);
        return !$scriptExists;
    }

    protected function RegisterVariableBoolean(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $variableExists = (@$this->module->GetIDForIdent($Ident) !== false);
        $this->module->RegisterVariableBoolean($Ident, $Name, $ProfileOrPresentation, $Position);
        return !$variableExists;
    }

    protected function RegisterVariableInteger(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $variableExists = (@$this->module->GetIDForIdent($Ident) !== null);
        $this->module->RegisterVariableInteger($Ident, $Name, $ProfileOrPresentation, $Position);
        return !$variableExists;
    }

    protected function RegisterVariableFloat(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $variableExists = (@$this->module->GetIDForIdent($Ident) !== null);
        $this->module->RegisterVariableFloat($Ident, $Name, $ProfileOrPresentation, $Position);
        return !$variableExists;
    }

    protected function RegisterVariableString(string $Ident, string $Name, string|array $ProfileOrPresentation = '', int $Position = 0): bool
    {
        $variableExists = (@$this->module->GetIDForIdent($Ident) !== null);
        $this->module->RegisterVariableString($Ident, $Name, $ProfileOrPresentation, $Position);
        return !$variableExists;
    }

    protected function UnregisterVariable(string $Ident): bool
    {
        $this->module->UnregisterVariable($Ident);
        return true;
    }

    protected function MaintainVariable(string $Ident, string $Name, int $Type, string|array $ProfileOrPresentation, int $Position, bool $Keep): bool
    {
        $this->module->MaintainVariable($Ident, $Name, $Type, $ProfileOrPresentation, $Position, $Keep);
        return true;
    }

    protected function EnableAction(string $Ident): bool
    {
        $this->module->EnableAction($Ident);
        return true;
    }

    protected function DisableAction(string $Ident): bool
    {
        $this->module->DisableAction($Ident);
        return true;
    }

    protected function MaintainAction(string $Ident, bool $Keep): bool
    {
        $this->module->MaintainAction($Ident, $Keep);
        return true;
    }

    protected function ReadPropertyBoolean(string $Name): bool
    {
        return $this->module->ReadPropertyBoolean($Name);
    }

    protected function ReadPropertyInteger(string $Name): int
    {
        return $this->module->ReadPropertyInteger($Name);
    }

    protected function ReadPropertyFloat(string $Name): float
    {
        return $this->module->ReadPropertyFloat($Name);
    }

    protected function ReadPropertyString(string $Name): string
    {
        return $this->module->ReadPropertyString($Name);
    }

    protected function ReadAttributeBoolean(string $Name): bool
    {
        return $this->module->ReadAttributeBoolean($Name);
    }

    protected function ReadAttributeInteger(string $Name): int
    {
        return $this->module->ReadAttributeInteger($Name);
    }

    protected function ReadAttributeFloat(string $Name): float
    {
        return $this->module->ReadAttributeFloat($Name);
    }

    protected function ReadAttributeString(string $Name): string
    {
        return $this->module->ReadAttributeString($Name);
    }

    protected function WriteAttributeBoolean(string $Name, bool $Value): bool
    {
        $this->module->WriteAttributeBoolean($Name, $Value);
        return true;
    }

    protected function WriteAttributeInteger(string $Name, int $Value): bool
    {
        $this->module->WriteAttributeInteger($Name, $Value);
        return true;
    }

    protected function WriteAttributeFloat(string $Name, float $Value): bool
    {
        $this->module->WriteAttributeFloat($Name, $Value);
        return true;
    }

    protected function WriteAttributeString(string $Name, string $Value): bool
    {
        $this->module->WriteAttributeString($Name, $Value);
        return true;
    }

    protected function SendDataToParent(string $Data): string
    {
        return $this->module->SendDataToParent($Data) ?? '';
    }

    protected function SendDataToChildren(string $Data): array
    {
        return $this->module->SendDataToChildren($Data);
    }

    protected function ConnectParent(string $ModuleID): bool
    {
        $this->module->ConnectParent($ModuleID);
        return true;
    }

    protected function RequireParent(string $ModuleID): bool
    {
        $this->module->RequireParent($ModuleID);
        return true;
    }

    protected function ForceParent(string $ModuleID): bool
    {
        $this->module->ForceParent($MduleID);
        return true;
    }

    protected function SetStatus(int $Status): bool
    {
        $this->module->SetStatus($Status);
        return true;
    }

    protected function GetStatus(): int
    {
        return $this->module->GetStatus();
    }

    protected function SetSummary(string $Summary): bool
    {
        $this->module->SetSummary($Summary);
        return true;
    }

    protected function SetBuffer(string $Name, string $Data): bool
    {
        $this->module->SetBuffer($Name, $Data);
        return true;
    }

    protected function GetBufferList(): array
    {
        return $this->module->GetBufferList($Name, $Data);
    }

    protected function GetBuffer(string $Name): string
    {
        return $this->module->GetBuffer($Name);
    }

    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        $this->module->SendDebug($Message, $Data, $Format);
        return true;
    }

    protected function GetMessageList(): array
    {
        return $this->module->GetMessageList();
    }

    protected function RegisterMessage(int $SenderID, int $Message): bool
    {
        $this->module->RegisterMessage($SenderID, $Message);
        return true;
    }

    protected function UnregisterMessage(int $SenderID, int $Message): bool
    {
        $this->module->UnregisterMessage($SenderID, $Message);
        return true;
    }

    protected function SetReceiveDataFilter(string $RequiredRegexMatch): bool
    {
        $this->module->SetReceiveDataFilter($RequiredRegexMatch);
        return true;
    }

    protected function SetForwardDataFilter(string $RequiredRegexMatch): bool
    {
        $this->module->SetForwardDataFilter($RequiredRegexMatch);
        return true;
    }

    protected function RegisterReference(int $ID): bool
    {
        $this->module->RegisterReference($ID);
        return true;
    }

    protected function UnregisterReference(int $ID): bool
    {
        $this->module->UnregisterReference($ID);
        return true;
    }

    protected function ReloadForm(): bool
    {
        $this->module->ReloadForm();
        return true;
    }

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        $this->module->UpdateFormField($Field, $Parameter, $Value);
        return true;
    }

    protected function UpdateVisualizationValue(mixed $Value)
    {
        $this->module->UpdateVisualizationValue($Value);
        return true;
    }

    protected function GetValue(string $Ident): mixed
    {
        return $this->module->GetValue($Ident);
    }

    protected function SetValue(string $Ident, mixed $Value): bool
    {
        return $this->module->SetValue($Ident, $Value);
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->module->LogMessage($Message, $Type);
        return true;
    }

    protected function HasActiveParent(): bool
    {
        return $this->module->HasActiveParent();
    }

    protected function RegisterHook(string $HookPath): bool
    {
    }

    protected function RegisterOAuth(string $OAuthPath): bool
    {
    }

    protected function getTime(): int
    {
        return $this->module->getTime();
    }
}
