<?php

declare(strict_types=1);
include_once __DIR__ . '/../ServerSocket/module.php';

class UDPSocket extends ServerSocketBase
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyInteger('Port', 0);
        $this->RegisterPropertyString('BindIP', '');
        $this->RegisterPropertyInteger('BindPort', 0);
        $this->RegisterPropertyBoolean('EnableBroadcast', false);
        $this->RegisterPropertyBoolean('EnableReuseAddress', false);
    }

    public function PushPacket(string $Text, string $ClientIP, int $ClientPort)
    {
        //The kernel prefers USCK_RX_Packet over SSCK_RX_Packet over VIO_RX_String
        $this->SendFirstImplementedToChildren([
            [
                'DataID'     => '{9082C662-7864-D5CA-863F-53999200D897}',
                'Buffer'     => $Text,
                'ClientIP'   => $ClientIP,
                'ClientPort' => $ClientPort,
                'Broadcast'  => $this->ReadPropertyBoolean('EnableBroadcast') ? 1 /* Local */ : 0 /* None */
            ],
            [
                'DataID'     => '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}',
                'Type'       => 0 /* Data */,
                'Buffer'     => $Text,
                'ClientIP'   => $ClientIP,
                'ClientPort' => $ClientPort
            ],
            [
                'DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}',
                'Buffer' => $Text
            ]
        ]);
    }
}
