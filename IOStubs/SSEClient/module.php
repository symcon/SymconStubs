<?php

declare(strict_types=1);
include_once __DIR__ . '/../VirtualIO/module.php';

class SSECLient extends VirtualIO
{
    public function Create()
    {
        //The SSE Client of the kernel does not have the Open property of the Virtual IO
        IPSModule::Create();

        $this->RegisterPropertyBoolean('Active', false);
        $this->RegisterPropertyString('URL', '');
        $this->RegisterPropertyString('Headers', '[]');
        $this->RegisterPropertyBoolean('VerifyHost', true);
        $this->RegisterPropertyBoolean('VerifyPeer', true);
    }
}