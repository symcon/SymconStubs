<?php

declare(strict_types=1);
include_once __DIR__ . '/../VirtualIO/module.php';

class ModBusGateway extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // "GatewayMode" (0: "Modbus TCP", 1: "Modbus RTU", 2: "Modbus RTU over TCP", 3: "Modbus TCP over UDP", 4: "SymBox with RS485 (Modbus RTU)")
        $this->RegisterPropertyInteger('GatewayMode', 0);
        $this->RegisterPropertyInteger('DeviceID', 1);
        // "Swap LSW/MSW for 32Bit/64Bit values"
        $this->RegisterPropertyBoolean('SwapWords', true);
    }
}
