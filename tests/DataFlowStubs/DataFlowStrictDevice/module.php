<?php

declare(strict_types=1);
class DataFlowStrictDevice extends IPSModuleStrict
{
    public $received = [];

    public function ReceiveData(string $JSONString): string
    {
        $this->received[] = json_decode($JSONString, true);
        return '';
    }

    public function Send(string $JSONString): string
    {
        return $this->SendDataToParent($JSONString);
    }
}
