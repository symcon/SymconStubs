<?php

declare(strict_types=1);
include_once __DIR__ . '/../VirtualIO/module.php';

//The packet handling is part of the Virtual IO, like in the kernel
class ServerSocketBase extends VirtualIO
{
    public function PushPacket(string $Text, string $ClientIP, int $ClientPort)
    {
        //Unlike the Virtual IO, the Server Socket does not offer USCK_RX_Packet
        $this->SendDataToChildren(json_encode([
            'DataID'     => '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}',
            'Type'       => 0 /* Data */,
            'Buffer'     => utf8_encode($Text),
            'ClientIP'   => $ClientIP,
            'ClientPort' => $ClientPort
        ]));
    }
}

class ServerSocket extends ServerSocketBase
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Port', '');
        $this->RegisterPropertyInteger('Limit', 0);
    }
}
