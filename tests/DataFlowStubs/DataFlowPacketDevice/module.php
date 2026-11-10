<?php

declare(strict_types=1);
class DataFlowPacketDevice extends IPSModule
{
    public $received = [];

    public function ReceiveData($JSONString)
    {
        $this->received[] = json_decode($JSONString, true);
        return '';
    }

    public function Send(string $JSONString)
    {
        return $this->SendDataToParent($JSONString);
    }
}
