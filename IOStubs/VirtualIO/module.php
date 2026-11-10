<?php

declare(strict_types=1);
class VirtualIO extends IPSModule
{
    private $textQueue = [];
    private $packetQueue = [];
    private $responses = [];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('Open', false);
    }

    public function ForwardData($JSONString)
    {
        parent::ForwardData($JSONString);

        $data = json_decode($JSONString, true);

        if (!isset($data['DataID'])) {
            throw new Exception('Invalid Data packet received');
        }

        switch ($data['DataID']) {
            case '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}': /* VIO_TX_String */
                $this->textQueue[] = utf8_decode($data['Buffer']);
                return;

            case '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}': /* SSCK_TX_Packet */
                $this->packetQueue[] = [
                    'Type'       => isset($data['Type']) ? $data['Type'] : 0 /* Data */,
                    'Buffer'     => utf8_decode($data['Buffer']),
                    'ClientIP'   => $data['ClientIP'],
                    'ClientPort' => $data['ClientPort']
                ];
                return;

            case '{8E4D9B23-E0F2-1E05-41D8-C21EA53B8706}': /* USCK_TX_Packet */
                $this->packetQueue[] = [
                    'Type'       => 0 /* Data */,
                    'Buffer'     => utf8_decode($data['Buffer']),
                    'ClientIP'   => $data['ClientIP'],
                    'ClientPort' => $data['ClientPort'],
                    //The kernel still accepts the boolean, which was used before the broadcast modes were introduced
                    'Broadcast'  => intval($data['Broadcast'])
                ];
                return;

            case '{D4C1D08F-CD3B-494B-BE18-B36EF73B8F43}': /* HTTP_TX_Page */
                foreach ($this->responses as $response) {
                    if (($response['Method'] == '*') || ($response['Method'] == $data['RequestMethod'])) {
                        if (($response['Route'] == '*') || ($response['Route'] == $data['RequestURL'])) {
                            return $response['Data'];
                        }
                    }
                }
                throw new Exception('Requested Route could not be found!');
        }

        throw new Exception('Unsupported DataID received');
    }

    public function HasText()
    {
        return count($this->textQueue) > 0;
    }

    public function PeekText()
    {
        if (!$this->HasText()) {
            throw new Exception('There is not data available');
        }

        return $this->textQueue[0];
    }

    public function PopText()
    {
        $result = $this->PeekText();
        array_shift($this->textQueue);

        return $result;
    }

    public function PushText(string $Text)
    {
        $this->SendDataToChildren(json_encode([
            'DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}',
            'Buffer' => utf8_encode($Text)
        ]));
    }

    public function HasPacket()
    {
        return count($this->packetQueue) > 0;
    }

    public function PeekPacket()
    {
        if (!$this->HasPacket()) {
            throw new Exception('There is not data available');
        }

        return $this->packetQueue[0];
    }

    public function PopPacket()
    {
        $result = $this->PeekPacket();
        array_shift($this->packetQueue);

        return $result;
    }

    public function PushConnect(string $ClientIP, int $ClientPort)
    {
        $this->SendDataToChildren(json_encode([
            'DataID'     => '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}',
            'Type'       => 1 /* Connect */,
            'Buffer'     => '',
            'ClientIP'   => $ClientIP,
            'ClientPort' => $ClientPort
        ]));
    }

    public function PushDisconnect(string $ClientIP, int $ClientPort)
    {
        $this->SendDataToChildren(json_encode([
            'DataID'     => '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}',
            'Type'       => 2 /* Disconnect */,
            'Buffer'     => '',
            'ClientIP'   => $ClientIP,
            'ClientPort' => $ClientPort
        ]));
    }

    public function PushPacket(string $Text, string $ClientIP, int $ClientPort)
    {
        //The kernel prefers SSCK_RX_Packet over USCK_RX_Packet
        $this->SendFirstImplementedToChildren([
            [
                'DataID'     => '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}',
                'Type'       => 0 /* Data */,
                'Buffer'     => $Text,
                'ClientIP'   => $ClientIP,
                'ClientPort' => $ClientPort
            ],
            [
                'DataID'     => '{9082C662-7864-D5CA-863F-53999200D897}',
                'Buffer'     => $Text,
                'ClientIP'   => $ClientIP,
                'ClientPort' => $ClientPort,
                'Broadcast'  => 0 /* None */
            ]
        ]);
    }

    public function PushData(string $Event, string $Data, string $Retry, string $ID)
    {
        $this->SendDataToChildren(json_encode([
            'DataID' => '{5A709184-B602-D394-227F-207611A33BDF}',
            'Event'  => $Event,
            'Data'   => $Data,
            'Retry'  => $Retry,
            'ID'     => $ID
        ]));
    }

    public function HTTPResponse(string $Route, string $Method, string $Data)
    {
        $this->responses[] = [
            'Route'  => $Route,
            'Method' => $Method,
            'Data'   => $Data
        ];
    }

    //Mimics SendDataToChildrenEx of the kernel: Every child only receives the first packet it implements
    //The Buffer of the packets is binary and encoded for the receiving child
    protected function SendFirstImplementedToChildren(array $Packets)
    {
        foreach (IPS_GetInstanceList() as $id) {
            if (IPS_GetInstance($id)['ConnectionID'] != $this->InstanceID) {
                continue;
            }

            $implemented = IPS_GetModule(IPS_GetInstance($id)['ModuleInfo']['ModuleID'])['Implemented'];
            foreach ($Packets as $packet) {
                if (!in_array($packet['DataID'], $implemented)) {
                    continue;
                }

                $child = IPS\InstanceManager::getInstanceInterface($id);
                //IPSModule uses UTF-8 and IPSModuleStrict uses HEX for the Buffer
                $packet['Buffer'] = ($child instanceof IPSModuleStrict) ? bin2hex($packet['Buffer']) : utf8_encode($packet['Buffer']);
                $data = json_encode($packet);
                if (preg_match('/' . $child->GetReceiveDataFilter() . '/', $data) == 1) {
                    $child->ReceiveData($data);
                }
                break;
            }
        }
    }
}
