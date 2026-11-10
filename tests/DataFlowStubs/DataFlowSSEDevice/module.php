<?php

declare(strict_types=1);
class DataFlowSSEDevice extends IPSModule
{
    public $received = [];

    public function ReceiveData($JSONString)
    {
        $this->received[] = json_decode($JSONString, true);
        return '';
    }
}
