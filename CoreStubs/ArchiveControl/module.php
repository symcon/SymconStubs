<?php

declare(strict_types=1);

class ArchiveControl extends IPSModule
{
    private $Archive = [];

    private function GetVariableData($VariableID)
    {
        if (!isset($this->Archive[$VariableID])) {
            $this->Archive[$VariableID] = [
                'Logged' => false,
                'Data'   => []
            ];
        }

        return $this->Archive[$VariableID];
    }

    private function SetVariableData($VariableID, $Data)
    {
        $this->Archive[$VariableID] = $Data;
    }

    public function SetLoggingStatus(int $VariableID, bool $Active)
    {
        $data = $this->GetVariableData($VariableID);
        $data['Logged'] = $Active;
        $this->SetVariableData($VariableID, $data);
    }

    public function GetLoggingStatus(int $VariableID)
    {
        return $this->GetVariableData($VariableID)['Logged'];
    }

    public function AddLoggedValues(int $VariableID, array $NewData)
    {
        usort($NewData, function($a, $b){
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        $ArchivedData = $this->GetVariableData($VariableID);
        if ((sizeof($ArchivedData['Data']) > 0) && (sizeof($NewData) > 0) &&
                ($NewData[0]['TimeStamp'] < $ArchivedData['Data'][sizeof($ArchivedData['Data'])-1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add values before the newest');
        }
        
        foreach ($NewData as $dataset) {
            $ArchivedData['Data'][] = $dataset;
        }
        $this->SetVariableData($VariableID, $ArchivedData);
    }

    public function GetLoggedValues(int $VariableID, int $StartTime, int $EndTime, int $Limit = 10000)
    {
        if ($Limit > 10000 || $Limit == 0) {
            $Limit = 10000;
        }
        $ArchivedData = $this->GetVariableData($VariableID);
        $return = [];
        foreach ($ArchivedData['Data'] as $data) {
            if (count($return) < $Limit) {
                if (($data['TimeStamp'] >= $StartTime) && ($data['TimeStamp'] <= $EndTime)) {
                    $return[] = $data;
                }
            } else {
                return array_reverse($return);
            }
        }
        return array_reverse($return);
    }
}
