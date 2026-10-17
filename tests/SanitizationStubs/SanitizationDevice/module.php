<?php

declare(strict_types=1);
class SanitizationDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('Port', 80);
        $this->RegisterPropertyFloat('Factor', 1);
        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyString('Host', '');
        $this->RegisterAttributeInteger('Counter', 0);
        $this->RegisterTimer('Update', 0, 'SANDEV_Update($_IPS[\'TARGET\']);');
        $this->RegisterVariableInteger('Value', 'Value');
    }

    public function ReceiveData($JSONString)
    {
        return 'device';
    }

    public function Update()
    {
    }

    public function CallRegisterPropertyInteger(string $Name, int $DefaultValue)
    {
        $this->RegisterPropertyInteger($Name, $DefaultValue);
    }

    public function CallRegisterAttributeInteger(string $Name, int $DefaultValue)
    {
        $this->RegisterAttributeInteger($Name, $DefaultValue);
    }

    public function CallRegisterTimer(string $Ident, int $Milliseconds)
    {
        $this->RegisterTimer($Ident, $Milliseconds, '');
    }

    public function CallSetTimerInterval(string $Ident, int $Milliseconds)
    {
        $this->SetTimerInterval($Ident, $Milliseconds);
    }

    public function CallGetTimerInterval(string $Ident)
    {
        return $this->GetTimerInterval($Ident);
    }

    public function CallSetStatus(int $Status)
    {
        $this->SetStatus($Status);
    }

    public function CallLogMessage(string $Message, int $Type)
    {
        $this->LogMessage($Message, $Type);
    }

    public function CallSendDataToParent(string $Data)
    {
        return $this->SendDataToParent($Data);
    }

    public function CallSetBuffer(string $Name, string $Data)
    {
        $this->SetBuffer($Name, $Data);
    }

    public function CallGetBufferList()
    {
        return $this->GetBufferList();
    }

    public function CallEnableAction(string $Ident)
    {
        return $this->EnableAction($Ident);
    }

    public function CallSetValue(string $Ident, $Value)
    {
        return $this->SetValue($Ident, $Value);
    }

    public function CallRegisterVariableInteger(string $Ident, string $Name)
    {
        return $this->RegisterVariableInteger($Ident, $Name);
    }

    public function CallReadPropertyInteger(string $Name)
    {
        return $this->ReadPropertyInteger($Name);
    }

    public function CallReadPropertyFloat(string $Name)
    {
        return $this->ReadPropertyFloat($Name);
    }

    protected function getTime()
    {
        return 1000;
    }
}
