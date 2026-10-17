<?php

declare(strict_types=1);
class SanitizationStrictDevice extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterVariableInteger('Value', 'Value');
    }

    public function CallSetValue(string $Ident, int $Value): bool
    {
        return $this->SetValue($Ident, $Value);
    }

    public function CallRegisterVariableInteger(string $Ident, string $Name): bool
    {
        return $this->RegisterVariableInteger($Ident, $Name);
    }

    public function CallRegisterVariableString(string $Ident, string $Name): bool
    {
        return $this->RegisterVariableString($Ident, $Name);
    }

    public function CallUnregisterVariable(string $Ident): bool
    {
        return $this->UnregisterVariable($Ident);
    }

    public function CallRegisterScript(string $Ident, string $Name): bool
    {
        return $this->RegisterScript($Ident, $Name);
    }

    public function CallSetVisualizationType(int $Type): bool
    {
        return $this->SetVisualizationType($Type);
    }

    protected function getTime(): int
    {
        return 1000;
    }
}
