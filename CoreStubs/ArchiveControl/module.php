<?php

declare(strict_types=1);

class ArchiveControl extends IPSModule
{
    private $Archive = [];

    public function StubsAddAggregatedValues(int $VariableID, int $AggregationSpan, array $AggregationData)
    {
        if (!$this->GetLoggingStatus($VariableID)) {
            throw new Exception('Adding aggregated data requires active logging');
        }
        usort($AggregationData, function ($a, $b)
        {
            return $a['TimeStamp'] <=> $b['TimeStamp'];
        });
        $archivedData = $this->GetVariableData($VariableID);
        $aggregatedArchiveData = $archivedData['AggregatedValues'][$AggregationSpan];
        if ((count($aggregatedArchiveData) > 0) && (count($AggregationData) > 0) &&
                ($AggregationData[0]['TimeStamp'] < $aggregatedArchiveData[count($aggregatedArchiveData) - 1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add aggregated values before the newest');
        }
        $archivedData['AggregatedValues'][$AggregationSpan] = array_merge($aggregatedArchiveData, $AggregationData);
        $this->SetVariableData($VariableID, $archivedData);
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
        $archivedData = $this->GetVariableData($VariableID);
        $loggedArchiveData = $archivedData['Values'];
        if ((count($loggedArchiveData) > 0) && (count($NewData) > 0) &&
                ($NewData[0]['TimeStamp'] < $loggedArchiveData[count($loggedArchiveData) - 1]['TimeStamp'])) {
            throw new Exception('It is not yet possible to add values before the newest');
        }

        $archivedData['Values'] = array_merge($loggedArchiveData, $NewData);
        $this->SetVariableData($VariableID, $archivedData);
    }

    public function ChangeVariableID(int $OldVariableID, int $NewVariableID)
    {
        throw new Exception("'ChangeVariableID' is not yet implemented");
    }

    public function DeleteVariableData(int $VariableID, int $StartTime, int $EndTime)
    {
        $archivedData = $this->GetVariableData($VariableID);
        $Values = $archivedData['Values'];

        if ($StartTime === 0 && $EndTime === 0) {
            $this->SetLoggingStatus($VariableID, false);
        }

        //The array is empty, nothing to delete
        if (count($Values) === 0) {
            return 0;
        }

        //Get the first and last timestamp if it is necessary
        if (array_key_exists('TimeStamp', $Values)) {
            if ($EndTime === 0 || $EndTime < end($Values)['TimeStamp']) {
                $EndTime = end($Values)['TimeStamp'];
            }
            if ($StartTime === 0 || $StartTime > reset($Values)['TimeStamp']) {
                $StartTime = reset($Values)['TimeStamp'];
            }
        }

        //Get the Start and endkey and delete the values between them
        $endKey = count($Values);
        reset($Values);
        $startKey = key($Values);

        foreach ($Values as $key => $value) {
            if ($value['TimeStamp'] < $StartTime) {
                $startKey = $key + 1;
            } else {
                break;
            }
        }
        foreach ($Values as $key => $value) {
            if ($value['TimeStamp'] > $EndTime) {
                $endKey = $key;
                break;
            }
        }

        $slicedData = array_slice($Values, $startKey, $endKey - $startKey, true);
        //Only keep the values that are not in the sliced Data
        $callback = function ($key) use ($slicedData)
        {
            return !array_key_exists($key, $slicedData);
        };
        $loggedValues = array_filter($Values, $callback, ARRAY_FILTER_USE_KEY);

        $archivedData['Values'] = array_values($loggedValues);
        $this->SetVariableData($VariableID, $archivedData);

        return count($slicedData);
    }

    public function GetAggregatedValues(int $VariableID, int $AggregationSpan, int $StartTime, int $EndTime, int $Limit)
    {
        if (empty($this->Archive[$VariableID]['AggregatedValues'][$AggregationSpan])) {
            throw new Exception('Aggregated data has to be added through the function AC_StubsAddAggregatedValues()');
        }
        if ($Limit > 10000 || $Limit == 0) {
            $Limit = 10000;
        }
        $archivedData = $this->GetVariableData($VariableID);
        $aggregatedArchiveData = $archivedData['AggregatedValues'][$AggregationSpan];
        $return = [];
        foreach (array_reverse($aggregatedArchiveData) as $data) {
            if (count($return) < $Limit) {
                if (($data['TimeStamp'] >= $StartTime) && ($EndTime == 0 || (($data['TimeStamp'] + $data['Duration'] - 1) <= $EndTime))) {
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
        return $this->GetVariableData($VariableID)['AggregationType'];
    }

    //Only AggregationActive, AggregationType and VariableID work properly
    public function GetAggregationVariables(bool $DatabaseRequest)
    {
        $aggregationVariables = [];
        foreach ($this->Archive as $id => $data) {
            $recordCount = 0;
            foreach ($data['AggregatedValues'] as $values) {
                $recordCount += count($values);
            }
            $aggregationVariables[] = [
                'FirstTime'          => 0,
                'LastTime'           => 0,
                'RecordCount'        => $recordCount,
                'RecordSize'         => 0,
                'VariableID'         => $id,
                'AggregationType'    => $data['AggregationType'],
                'AggregationVisible' => false,
                'AggregationActive'  => $data['AggregationActive']
            ];
        }

        //display hint that this function is not fully implemented
        echo PHP_EOL . 'AC_GetAggregationVariables NOT FULLY IMPLEMENTED' . PHP_EOL;

        return $aggregationVariables;
    }

    public function GetGraphStatus(int $VariableID)
    {
        return $this->GetVariableData($VariableID)['AggregationVisible'];
    }

    public function GetLoggedValues(int $VariableID, int $StartTime, int $EndTime, int $Limit = 10000)
    {
        if ($Limit > 10000 || $Limit == 0) {
            $Limit = 10000;
        }
        $archivedData = array_reverse($this->GetVariableData($VariableID)['Values']);
        $return = [];
        foreach ($archivedData as $data) {
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
        return $this->GetVariableData($VariableID)['AggregationActive'];
    }

    public function ReAggregateVariable(int $VariableID)
    {
    }

    public function SetAggregationType(int $VariableID, int $AggregationType)
    {
        $data = $this->GetVariableData($VariableID);
        $data['AggregationType'] = $AggregationType;
        $this->SetVariableData($VariableID, $data);
    }

    public function SetGraphStatus(int $VariableID, bool $Active)
    {
        $data = $this->GetVariableData($VariableID);
        $data['AggregationVisible'] = $Active;
        $this->SetVariableData($VariableID, $data);
    }

    // Status will be updated without ApplyChanges() unlike current (5.4) IP-Symcon implementation
    // However, this will change in IP-Symcon with the archive rebuild
    public function SetLoggingStatus(int $VariableID, bool $Active)
    {
        $data = $this->GetVariableData($VariableID);
        $data['AggregationActive'] = $Active;
        $this->SetVariableData($VariableID, $data);
    }

    public function SetCounterIgnoreZeros(int $VariableID, bool $IgnoreZeros)
    {
        $data = $this->GetVariableData($VariableID);
        $data['CounterIgnoreZeros'] = $IgnoreZeros;
        $this->SetVariableData($VariableID, $data);
    }

    public function GetCounterIgnoreZeros(int $VariableID)
    {
        return $this->GetVariableData($VariableID)['CounterIgnoreZeros'];
    }

    private function GetVariableData($VariableID)
    {
        if (empty($this->Archive[$VariableID])) {
            $this->Archive[$VariableID] = [
                'AggregationActive' => false,
                'Values'            => [],
                'AggregationType'   => 0,
                'AggregatedValues'  => [
                    0 /* Hourly */   => [],
                    1 /* Daily */    => [],
                    2 /* Weekly */   => [],
                    3 /* Monthly */  => [],
                    4 /* Yearly */   => [],
                    5 /* 5-Minute */ => [],
                    6 /* 1-Minute */ => [],
                    7 /* Changes */  => []
                ],
                'AggregationVisible' => false,
                'CounterIgnoreZeros' => false
            ];
        }
        return $this->Archive[$VariableID];
    }

    private function SetVariableData($VariableID, $Data)
    {
        $this->Archive[$VariableID] = $Data;
    }
}
