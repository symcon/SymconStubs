<?php

declare(strict_types=1);

class ArchiveControl extends IPSModule
{
    private $Archive = [];

    private function GetVariableData($VariableID)
    {
        if (empty($this->Archive[$VariableID])) {
            $this->Archive[$VariableID] = [
                'Logged'             => false,
                'Values'             => [],
                'AggregatedValues'   => [
                    0 /* Hourly */  => [],
                    1 /* Daily */   => [],
                    2 /* Weekly */  => [],
                    3 /* Monthly */ => [],
                    4 /* Yearly */  => [],
                    5 /* 5-Mintue */=> [],
                    6 /* 1-Mintue */=> []
                ]
            ];
        }
        return $this->Archive[$VariableID];
    }

    private function SetVariableData($VariableID, $Data)
    {
        $this->Archive[$VariableID] = $Data;
    }

    public function StubsAddAggregatedValues(int $VariableID, int $AggregationSpan, array $AggregationData)
    {
        if (!$this->GetLoggingStatus($VariableID)) {
            throw new Exception('Adding aggregated data requires active logging');
        }
        usort($AggregationData, function ($a, $b)
        {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        $ArchivedData = $this->GetVariableData($VariableID);
        $AggregatedArchiveData = $ArchivedData['AggregatedValues'][$AggregationSpan];
        if ((count($AggregatedArchiveData) > 0) && (count($AggregationData) > 0) &&
                ($AggregationData[0]['TimeStamp'] < $AggregatedArchiveData[count($AggregatedArchiveData) - 1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add aggregated values before the newest');
        }
        $ArchivedData['AggregatedValues'][$AggregationSpan] = array_merge($AggregatedArchiveData, $AggregationData);
        $this->SetVariableData($VariableID, $ArchivedData);
    }

    public function AddLoggedValues(int $VariableID, array $NewData)
    {
        if (!$this->GetLoggingStatus($VariableID)) {
            throw new Exception('Adding logged data requires active logging');
        }
        usort($NewData, function ($a, $b)
        {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        $ArchivedData = $this->GetVariableData($VariableID);
        if ((count($ArchivedData['Values']) > 0) && (count($NewData) > 0) &&
                ($NewData[0]['TimeStamp'] < $ArchivedData['Values'][count($ArchivedData['Values']) - 1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add values before the newest');
        }

        foreach ($NewData as $dataset) {
            $ArchivedData['Values'][] = $dataset;
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

    public function GetAggregatedValues(int $VariableID, int $AggregationSpan, int $StartTime, int $EndTime, int $Limit)
    {
        if (empty($this->Archive[$VariableID]['AggregatedValues'])) {
            throw new Exception('Aggregated data has to be added through the function AC_StubsAddAggregatedValues()');
        }
        if ($Limit > 10000 || $Limit == 0) {
            $Limit = 10000;
        }
        $ArchivedData = $this->GetVariableData($VariableID);
        $AggregatedArchiveData = $ArchivedData['AggregatedValues'][$AggregationSpan];
        $return = [];
        foreach (array_reverse($AggregatedArchiveData) as $data) {
            if (count($return) < $Limit) {
                if (($data['TimeStamp'] >= $StartTime) && (($data['TimeStamp'] + $data['Duration'] - 1) <= $EndTime)) {
                    $return[] = $data;
                }
            } else {
                return $return;
            }
        }
        return $return;
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
        $ArchivedData = array_reverse($this->GetVariableData($VariableID)['Values']);
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
