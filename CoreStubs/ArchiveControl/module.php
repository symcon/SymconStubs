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

    public function AddLoggedValues(int $VariableID, array $NewData)
    {
        usort($NewData, function ($a, $b)
        {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        $ArchivedData = $this->GetVariableData($VariableID);
        if ((count($ArchivedData['Data']) > 0) && (count($NewData) > 0) &&
                ($NewData[0]['TimeStamp'] < $ArchivedData['Data'][count($ArchivedData['Data']) - 1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add values before the newest');
        }

        foreach ($NewData as $dataset) {
            $ArchivedData['Data'][] = $dataset;
        }
        $this->SetVariableData($VariableID, $ArchivedData);
    }

    public function ChangeVariableID(int $OldVariableID, int $NewVariableID)
    {
        throw new Exception('Not implemented');
    }

    public function DeleteVariableData(int $VariableID, int $StartTime, int $EndTime)
    {
        throw new Exception('Not implemented');
    }

    public function GetAggregatedValues(int $VariableID, int $AggregationLevel, int $StartTime, $EndTime, $Limit)
    {
        throw new Exception('Not implemented');
    }

    public function GetAggregationType(int $VariableID)
    {
        throw new Exception('Not implemented');
    }

    public function GetAggregationVariables(bool $DatabaseRequest)
    {
        throw new Exception('Not implemented');
    }

    public function GetGraphStatus(int $VariableID)
    {
        throw new Exception('Not implemented');
    }

    public function GetLoggedValues(int $VariableID, int $StartTime, int $EndTime, int $Limit = 10000)
    {
        if ($Limit > 10000 || $Limit == 0) {
            $Limit = 10000;
        }
        $ArchivedData = array_reverse($this->GetVariableData($VariableID)['Data']);
        $return = [];
        foreach ($ArchivedData as $data) {
            if (count($return) < $Limit) {
                if (($data['TimeStamp'] >= $StartTime) && ($data['TimeStamp'] <= $EndTime)) {
                    $return[] = $data;
                }
            } else {
                return $return;
            }
        }
        return $return;
    }

    public function GetLoggingStatus(int $VariableID)
    {
        return $this->GetVariableData($VariableID)['Logged'];
    }

    public function ReAggregateVariable(int $VariableID)
    {
        throw new Exception('Not implemented');
    }

    public function SetAggregationType(int $VariableID, int $AggregationType)
    {
        throw new Exception('Not implemented');
    }

    public function SetGraphStatus(int $VariableID)
    {
        throw new Exception('Not implemented');
    }

    public function SetLoggingStatus(int $VariableID, bool $Active)
    {
        $data = $this->GetVariableData($VariableID);
        $data['Logged'] = $Active;
        $this->SetVariableData($VariableID, $data);
    }
}
