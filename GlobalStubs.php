<?php

declare(strict_types=1);

/* Global */
function GetValue(int $VariableID)
{
    switch (IPS\VariableManager::getVariable($VariableID)['VariableType']) {
        case 0: /* Boolean */
            return IPS\VariableManager::readVariableBoolean($VariableID);
        case 1: /* Integer */
            return IPS\VariableManager::readVariableInteger($VariableID);
        case 2: /* Float */
            return IPS\VariableManager::readVariableFloat($VariableID);
        case 3: /* String */
            return IPS\VariableManager::readVariableString($VariableID);
        default:
            throw new Exception('Unsupported VariableType!');
    }
}

function GetValueBoolean(int $VariableID)
{
    return IPS\VariableManager::readVariableBoolean($VariableID);
}

function GetValueInteger(int $VariableID)
{
    return IPS\VariableManager::readVariableInteger($VariableID);
}

function GetValueFloat(int $VariableID)
{
    return IPS\VariableManager::readVariableFloat($VariableID);
}

function GetValueString(int $VariableID)
{
    return IPS\VariableManager::readVariableString($VariableID);
}

function SetValue(int $VariableID, $Value)
{
    switch (IPS\VariableManager::getVariable($VariableID)['VariableType']) {
        case 0: /* Boolean */
            IPS\VariableManager::writeVariableBoolean($VariableID, $Value);
            break;
        case 1: /* Integer */
            IPS\VariableManager::writeVariableInteger($VariableID, $Value);
            break;
        case 2: /* Float */
            IPS\VariableManager::writeVariableFloat($VariableID, $Value);
            break;
        case 3: /* String */
            IPS\VariableManager::writeVariableString($VariableID, $Value);
            break;
        default:
            throw new Exception('Unsupported VariableType!');
    }
}

function SetValueBoolean(int $VariableID, bool $Value)
{
    IPS\VariableManager::writeVariableBoolean($VariableID, $Value);
}

function SetValueInteger(int $VariableID, int $Value)
{
    IPS\VariableManager::writeVariableInteger($VariableID, $Value);
}

function SetValueFloat(int $VariableID, float $Value)
{
    IPS\VariableManager::writeVariableFloat($VariableID, $Value);
}

function SetValueString(int $VariableID, string $Value)
{
    IPS\VariableManager::writeVariableString($VariableID, $Value);
}

function GetValueFormatted(int $VariableID)
{
    return GetValueFormattedEx($VariableID, IPS_GetVariable($VariableID)['VariableValue']);
}

function GetValueFormattedEx(int $VariableID, $Value)
{
    $variable = IPS_GetVariable($VariableID);

    $legacyHandling = function ($profileName) use ($variable, $Value)
    {
        if (!IPS_VariableProfileExists($profileName)) {
            return 'Invalid profile';
        }

        $profile = IPS_GetVariableProfile($profileName);
        if ($profile['ProfileType'] !== $variable['VariableType']) {
            return 'Invalid profile type';
        }

        $addPrefixSuffix = function ($value) use ($profile)
        {
            return strval($profile['Prefix'] . $value . $profile['Suffix']);
        };

        switch ($profileName) {
            case '~UnixTimestamp':
            case '~UnixTimestampTime':
            case '~UnixTimestampDate':
                throw new Exception('TimestampProfiles not implemented yet');

            case '~HexColor':
                return '';

            default:
                if (count($profile['Associations']) == 0) {
                    switch ($profile['ProfileType']) {
                        case 0: //Boolean
                            throw new Exception('Profiles of type boolean need to have two associations');

                        case 1: //Integer
                            if ((trim($profile['Suffix']) === '%') && (($profile['MaxValue'] - $profile['MinValue']) > 0)) {
                                return $addPrefixSuffix(round(($Value - $profile['MinValue']) * 100 / ($profile['MaxValue'] - $profile['MinValue'])));
                            }

                            return $addPrefixSuffix($Value);

                        case 2: //Float
                            if ((trim($profile['Suffix']) === '%') && (($profile['MaxValue'] - $profile['MinValue']) > 0)) {
                                return $addPrefixSuffix(number_format(round(($Value - $profile['MinValue']) * 100 / ($profile['MaxValue'] - $profile['MinValue'])), $profile['Digits']));
                            }

                            return $addPrefixSuffix(number_format(round($Value), $profile['Digits']));

                        case 3: //String
                            return $addPrefixSuffix($Value);

                        default:
                            throw new Exception('Format error: Invalid variable type');

                    }
                } else {
                    switch ($profile['ProfileType']) {
                        case 0: //Boolean
                            if (count($profile['Associations']) < 2) {
                                throw new Exception('Profiles of type boolean need to have two associations');
                            }

                            if ($Value === true) {
                                return $profile['Associations'][1]['Name'];
                            } elseif ($Value === false) {
                                return $profile['Associations'][0]['Name'];
                            }

                            return '-';

                        case 1: //Integer
                        case 2: //Float
                            for ($i = count($profile['Associations']) - 1; $i >= 0; $i--) {
                                if ($Value >= $profile['Associations'][$i]['Value']) {
                                    return $profile['Prefix'] . sprintf($profile['Associations'][$i]['Name'], $Value) . $profile['Suffix'];
                                }
                            }
                            return '-';

                        case 3: //String
                            for ($i = count($profile['Associations']) - 1; $i >= 0; $i--) {
                                if ($Value == $profile['Associations'][$i]['Value']) {
                                    return $profile['Prefix'] . sprintf($profile['Associations'][$i]['Name'], $Value) . $profile['Suffix'];
                                }
                            }
                            return '-';

                    }
                }
        }
    };
    if (!function_exists('IPS_GetVariablePresentation')) {
        $profileName = '';
        if ($variable['VariableCustomProfile'] != '') {
            $profileName = $variable['VariableCustomProfile'];
        } else {
            $profileName = $variable['VariableProfile'];
        }
        return $legacyHandling($profileName);
    } else {
        $presentation = IPS_GetVariablePresentation($VariableID);
        if (empty($presentation)) {
            return strval($Value);
        }
        switch ($presentation['PRESENTATION']) {
            case VARIABLE_PRESENTATION_LEGACY:
                return $legacyHandling($presentation['PROFILE']);

            case VARIABLE_PRESENTATION_ENUMERATION:
                $options = json_decode($presentation['OPTIONS'], true);
                foreach ($options as $option) {
                    if ($option['Value'] == $Value) {
                        return $option['Caption'];
                    }
                }
                return '-';
                break;

            default:
                throw new Exception('Unsupported Presentation: ' . $presentation['PRESENTATION']);

        }
    }

}

function HasAction(int $VariableID)
{
    $v = IPS\VariableManager::getVariable($VariableID);
    if ($v['VariableCustomAction'] > 0) {
        $actionID = $v['VariableCustomAction'];
    } else {
        $actionID = $v['VariableAction'];
    }
    return $actionID >= 10000;
}

function RequestAction(int $VariableID, $Value)
{
    $v = IPS\VariableManager::getVariable($VariableID);
    if ($v['VariableCustomAction'] > 0) {
        $actionID = $v['VariableCustomAction'];
    } else {
        $actionID = $v['VariableAction'];
    }
    if (IPS_InstanceExists($actionID)) {
        $o = IPS\ObjectManager::getObject($VariableID);
        $interface = IPS\InstanceManager::getInstanceInterface($actionID);
        $interface->RequestAction($o['ObjectIdent'], $Value);
        return true;
    } elseif (IPS_ScriptExists($actionID)) {
        $result = IPS_RunScriptWaitEx($actionID, [
            'VARIABLE' => $VariableID,
            'VALUE'    => $Value,
            'SENDER'   => 'Action'
        ]);
        if (strlen($result) > 0) {
            echo $result;
        }
        return true;
    } else {
        throw new Exception('Action is invalid');
    }
}

function RequestActionEx(int $VariableID, $Value, $Sender)
{
    $v = IPS\VariableManager::getVariable($VariableID);
    if ($v['VariableCustomAction'] > 0) {
        $actionID = $v['VariableCustomAction'];
    } else {
        $actionID = $v['VariableAction'];
    }
    if (IPS_InstanceExists($actionID)) {
        $o = IPS\ObjectManager::getObject($VariableID);
        $interface = IPS\InstanceManager::getInstanceInterface($actionID);
        $interface->RequestAction($o['ObjectIdent'], $Value);
        return true;
    } elseif (IPS_ScriptExists($actionID)) {
        $result = IPS_RunScriptWaitEx($actionID, [
            'VARIABLE' => $VariableID,
            'VALUE'    => $Value,
            'SENDER'   => $Sender,
        ]);
        if (strlen($result) > 0) {
            echo $result;
        }
        return true;
    } else {
        throw new Exception('Action is invalid');
    }
}

/* Object Manager */
function IPS_SetParent(int $ID, int $ParentID)
{
    IPS\ObjectManager::setParent($ID, $ParentID);
}

function IPS_SetIdent(int $ID, string $Ident)
{
    IPS\ObjectManager::setIdent($ID, $Ident);
}

function IPS_SetName(int $ID, string $Name)
{
    IPS\ObjectManager::setName($ID, $Name);
}

function IPS_SetInfo(int $ID, string $Info)
{
    IPS\ObjectManager::setInfo($ID, $Info);
}

function IPS_SetIcon(int $ID, string $Icon)
{
    IPS\ObjectManager::setIcon($ID, $Icon);
}

function IPS_SetPosition(int $ID, int $Position)
{
    IPS\ObjectManager::setPosition($ID, $Position);
}

function IPS_SetHidden(int $ID, bool $Hidden)
{
    IPS\ObjectManager::setHidden($ID, $Hidden);
}

function IPS_SetDisabled(int $ID, bool $Disabled)
{
    IPS\ObjectManager::setDisabled($ID, $Disabled);
}

function IPS_ObjectExists(int $ID)
{
    return IPS\ObjectManager::objectExists($ID);
}

function IPS_GetObject(int $ID)
{
    return IPS\ObjectManager::getObject($ID);
}

function IPS_GetObjectList()
{
    return IPS\ObjectManager::getObjectList();
}

function IPS_GetObjectIDByName(string $Name, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByName($Name, $ParentID);
}

function IPS_GetObjectIDByIdent(string $Ident, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByIdent($Ident, $ParentID);
}

function IPS_HasChildren(int $ID)
{
    return IPS\ObjectManager::hasChildren($ID);
}

function IPS_IsChild(int $ID, int $ParentID, bool $Recursive)
{
    return IPS\ObjectManager::isChild($ID, $ParentID, $Recursive);
}

function IPS_GetChildrenIDs(int $ID)
{
    return IPS\ObjectManager::getChildrenIDs($ID);
}

function IPS_GetName(int $ID)
{
    return IPS\ObjectManager::getName($ID);
}

function IPS_GetParent(int $ID)
{
    return IPS\ObjectManager::getParent($ID);
}

function IPS_GetLocation(int $ID)
{
    return IPS\ObjectManager::getLocation($ID);
}

/* Category Manager */
function IPS_CreateCategory()
{
    $id = IPS\ObjectManager::registerObject(0 /* Category */);
    IPS\CategoryManager::createCategory($id);

    return $id;
}

function IPS_DeleteCategory(int $CategoryID)
{
    IPS\CategoryManager::deleteCategory($CategoryID);
    IPS\ObjectManager::unregisterObject($CategoryID);
}

function IPS_CategoryExists(int $CategoryID)
{
    return IPS\CategoryManager::categoryExists($CategoryID);
}

function IPS_GetCategory(int $CategoryID)
{
    return IPS\CategoryManager::getCategory($CategoryID);
}

function IPS_GetCategoryList()
{
    return IPS\CategoryManager::getCategoryList();
}

function IPS_GetCategoryIDByName(string $Name, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByNameEx($Name, $ParentID, 0 /* Category */);
}

/* Instance Manager */
function IPS_CreateInstance(string $ModuleID)
{
    $module = IPS\ModuleLoader::getModule($ModuleID);
    $id = IPS\ObjectManager::registerObject(1 /* Instance */);
    IPS\InstanceManager::createInstance($id, $module);

    return $id;
}

function IPS_DeleteInstance(int $InstanceID)
{
    IPS\InstanceManager::deleteInstance($InstanceID);
    IPS\ObjectManager::unregisterObject($InstanceID);
}

function IPS_InstanceExists(int $InstanceID)
{
    return IPS\InstanceManager::instanceExists($InstanceID);
}

function IPS_GetInstance(int $InstanceID)
{
    return IPS\InstanceManager::getInstance($InstanceID);
}

function IPS_GetInstanceList()
{
    return IPS\InstanceManager::getInstanceList();
}

function IPS_GetInstanceIDByName(string $Name, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByNameEx($Name, $ParentID, 1 /* Instance */);
}

function IPS_GetInstanceListByModuleType(int $ModuleType)
{
    return IPS\InstanceManager::getInstanceListByModuleType($ModuleType);
}

function IPS_GetInstanceListByModuleID(string $ModuleID)
{
    return IPS\InstanceManager::getInstanceListByModuleID($ModuleID);
}

function IPS_GetReferenceList(int $InstanceID)
{
    return IPS\InstanceManager::getReferenceList($InstanceID);
}

/* Instance Manager - Configuration */
function IPS_HasChanges(int $InstanceID)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->HasChanges();
}

function IPS_ResetChanges(int $InstanceID)
{
    IPS\InstanceManager::getInstanceInterface($InstanceID)->ResetChanges();
}

function IPS_ApplyChanges(int $InstanceID)
{
    IPS\InstanceManager::getInstanceInterface($InstanceID)->ApplyChanges();
}

function IPS_GetProperty(int $InstanceID, string $Name)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->GetProperty($Name);
}

function IPS_GetConfiguration(int $InstanceID)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->GetConfiguration();
}

function IPS_GetConfigurationForParent(int $InstanceID)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->GetConfigurationForParent();
}

function IPS_GetConfigurationForm(int $InstanceID)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->GetConfigurationForm();
}

function IPS_SetProperty(int $InstanceID, string $Name, $Value)
{
    IPS\InstanceManager::getInstanceInterface($InstanceID)->SetProperty($Name, $Value);
}

function IPS_SetConfiguration(int $InstanceID, string $Configuration)
{
    IPS\InstanceManager::getInstanceInterface($InstanceID)->SetConfiguration($Configuration);
}

/* Instance Manager - Connections */
function IPS_ConnectInstance(int $InstanceID, int $ParentID)
{
    IPS\InstanceManager::connectInstance($InstanceID, $ParentID);
}

function IPS_DisconnectInstance(int $InstanceID)
{
    IPS\InstanceManager::disconnectInstance($InstanceID);
}

/* Instance Manager - Searching */
function IPS_StartSearch(int $InstanceID)
{
    throw new Exception('Not implemented');
}

function IPS_StopSearch(int $InstanceID)
{
    throw new Exception('Not implemented');
}

function IPS_SupportsSearching(int $InstanceID)
{
    throw new Exception('Not implemented');
}

function IPS_IsSearching(int $InstanceID)
{
    throw new Exception('Not implemented');
}

/* Instance Manager - Debugging */
function IPS_DisableDebug(int $ID)
{
    IPS\DebugServer::disableDebug($ID);
}

function IPS_EnableDebug(int $ID, int $Duration)
{
    IPS\DebugServer::enableDebug($ID, $Duration);
}

function IPS_SendDebug(int $SenderID, string $Message, string $Data, int $Format)
{
    IPS\DebugServer::sendDebug($SenderID, $Message, $Data, $Format);
}

/* Instance Manager - Actions */
function IPS_RequestAction(int $InstanceID, string $VariableIdent, $Value)
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->RequestAction($VariableIdent, $Value);
}

/* Variable Manager */
function IPS_CreateVariable(int $VariableType)
{
    $id = IPS\ObjectManager::registerObject(2 /* Variable */);
    IPS\VariableManager::createVariable($id, $VariableType);

    return $id;
}

function IPS_DeleteVariable(int $VariableID)
{
    IPS\VariableManager::deleteVariable($VariableID);
    IPS\ObjectManager::unregisterObject($VariableID);
}

function IPS_VariableExists(int $VariableID)
{
    return IPS\VariableManager::variableExists($VariableID);
}

function IPS_GetVariable(int $VariableID)
{
    return IPS\VariableManager::getVariable($VariableID);
}

if (!defined('IPS_VERSION') || (defined('IPS_VERSION') && IPS_VERSION >= 8.0)) {
    function IPS_GetVariablePresentation(int $VariableID)
    {
        return IPS\VariableManager::getVariablePresentation($VariableID);

    }
}

function IPS_GetVariableEventList(int $VariableID)
{
    return []; //FIXME
}

function IPS_GetVariableIDByName(string $Name, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByNameEx($Name, $ParentID, 2 /* Variable */);
}

function IPS_GetVariableList()
{
    return IPS\VariableManager::getVariableList();
}

function IPS_SetVariableCustomAction(int $VariableID, int $ScriptID)
{
    IPS\VariableManager::setVariableCustomAction($VariableID, $ScriptID);
}

function IPS_SetVariableCustomProfile(int $VariableID, string $ProfileName)
{
    IPS\VariableManager::setVariableCustomProfile($VariableID, $ProfileName);
}

function IPS_SetVariableCustomPresentation(int $VariableID, array $Presentation)
{
    IPS\VariableManager::setVariableCustomPresentation($VariableID, $Presentation);
}

/* Script Manager */
function IPS_CreateScript(int $ScriptType)
{
    $id = IPS\ObjectManager::registerObject(3 /* Script */);
    IPS\ScriptManager::createScript($id, $ScriptType);

    return $id;
}

function IPS_DeleteScript(int $ScriptID, bool $DeleteFile)
{
    IPS\ScriptManager::deleteScript($ScriptID, $DeleteFile);
    IPS\ObjectManager::unregisterObject($ScriptID);
}

function IPS_ScriptExists(int $ScriptID)
{
    return IPS\ScriptManager::scriptExists($ScriptID);
}

function IPS_SetScriptContent(int $ScriptID, string $Content)
{
    IPS\ScriptManager::setScriptContent($ScriptID, $Content);
}

function IPS_SetScriptFile(int $ScriptID, string $FilePath)
{
    IPS\ScriptManager::setScriptFile($ScriptID, $FilePath);
}

function IPS_GetScript(int $ScriptID)
{
    return IPS\ScriptManager::getScript($ScriptID);
}

function IPS_GetScriptContent(int $ScriptID)
{
    return IPS\ScriptManager::getScriptContent($ScriptID);
}

function IPS_GetScriptEventList(int $ScriptID)
{
    throw new Exception('Not implemented');
}

function IPS_GetScriptFile(int $ScriptID)
{
    IPS\ScriptManager::getScriptFile($ScriptID);
}

function IPS_GetScriptIDByFile(string $FilePath)
{
    throw new Exception('Not implemented');
}

function IPS_GetScriptIDByName(string $Name, int $ParentID)
{
    return IPS\ObjectManager::getObjectIDByNameEx($Name, $ParentID, 3 /* Script */);
}

function IPS_GetScriptList()
{
    return IPS\ScriptManager::getScriptList();
}

/* Event Manager */
function IPS_CreateEvent(int $EventType)
{
    return 0;
}

function IPS_DeleteEvent(int $EventID)
{
    return true;
}

function IPS_EventExists(int $EventID)
{
    return true;
}

function IPS_GetEvent(int $EventID)
{
    return [];
}

function IPS_GetEventIDByName(string $Name, int $ParentID)
{
    return 0;
}

function IPS_GetEventList()
{
    return [];
}

function IPS_GetEventListByType(int $EventType)
{
    return [];
}

function IPS_SetEventActive(int $EventID, bool $Active)
{
    return true;
}

function IPS_SetEventCyclic(int $EventID, int $DateType, int $DateValue, int $DateDay, int $DateDayValue, int $TimeType, int $TimeValue)
{
    return true;
}

function IPS_SetEventCyclicDateFrom(int $EventID, int $Day, int $Month, int $Year)
{
    return true;
}

function IPS_SetEventCyclicDateTo(int $EventID, int $Day, int $Month, int $Year)
{
    return true;
}

function IPS_SetEventCyclicTimeFrom(int $EventID, int $Hour, int $Minute, int $Second)
{
    return true;
}

function IPS_SetEventCyclicTimeTo(int $EventID, int $Hour, int $Minute, int $Second)
{
    return true;
}

function IPS_SetEventLimit(int $EventID, int $Count)
{
    return true;
}

function IPS_SetEventScheduleAction(int $EventID, int $ActionID, string $Name, int $Color, string $ScriptText)
{
    return true;
}

function IPS_SetEventScheduleGroup(int $EventID, int $GroupID, int $Days)
{
    return true;
}

function IPS_SetEventScheduleGroupPoint(int $EventID, int $GroupID, int $PointID, int $StartHour, int $StartMinute, int $StartSecond, int $ActionID)
{
    return true;
}

function IPS_SetEventScript(int $EventID, string $EventScript)
{
    return true;
}

function IPS_SetEventTrigger(int $EventID, int $TriggerType, int $TriggerVariableID)
{
    return true;
}

function IPS_SetEventTriggerSubsequentExecution(int $EventID, bool $AllowSubsequentExecutions)
{
    return true;
}

function IPS_SetEventTriggerValue(int $EventID, $TriggerValue)
{
    return true;
}

function IPS_IsConditionPassing(string $Conditions)
{
    $condition = json_decode($Conditions, true);
    switch (count($condition)) {
        case 0:
            return true;

        case 1:
            if (isset($condition[0]['rules']['date']) ||
                isset($condition[0]['rules']['time']) ||
                isset($condition[0]['rules']['dayOfTheWeek'])
            ) {
                throw new Error('Time related conditions not implemented yet');
            }
            $results = [];
            foreach ($condition[0]['rules']['variable'] as $variableRule) {
                $comparisionValue = (($variableRule['type'] ?? 0) === 0) ? $variableRule['value'] : GetValue($variableRule['value']);
                $variableValue = GetValue($variableRule['variableID']);
                switch ($variableRule['comparison']) {
                    case 0:
                        $results[] = ($variableValue == $comparisionValue);
                        break;

                    default:
                        throw new Error('Comparison type not implemented yet');
                }
            }

            switch ($condition[0]['operation']) {
                case 0:
                    return array_reduce(
                        $results,
                        function ($carry, $result)
                        {
                            return $carry && $result;
                        },
                        true
                    );

                default:
                    throw new Error('Operation type not implemented yet');
            }

            // No break. Add additional comment above this line if intentional
        default:
            throw new Error('Complex conditions not implemented yet');
    }
}

function IPS_GetScriptTimer(int $ScriptID)
{
    return 0;
}

function IPS_SetScriptTimer(int $ScriptID, int $Interval)
{
    return true;
}

/* Media Manager */
function IPS_CreateMedia(int $MediaType)
{
    $id = IPS\ObjectManager::registerObject(5 /* Media */);
    IPS\MediaManager::createMedia($id, $MediaType);
    return $id;
}

function IPS_DeleteMedia(int $MediaID, bool $DeleteFile)
{
    IPS\MediaManager::deleteMedia($MediaID, $DeleteFile);
    IPS\ObjectManager::unregisterObject($MediaID);
}

function IPS_MediaExists(int $MediaID)
{
    return IPS\MediaManager::mediaExists($MediaID);
}

function IPS_GetMedia(int $MediaID)
{
    return [];
}

function IPS_GetMediaContent(int $MediaID)
{
    return '';
}

function IPS_GetMediaIDByFile(string $FilePath)
{
    return 0;
}

function IPS_GetMediaIDByName(string $Name, int $ParentID)
{
    return 0;
}

function IPS_GetMediaList()
{
    return [];
}

function IPS_GetMediaListByType(int $MediaType)
{
    return [];
}

function IPS_SetMediaCached(int $MediaID, bool $Cached)
{
    return true;
}

function IPS_SetMediaContent(int $MediaID, string $Content)
{
    return true;
}

function IPS_SetMediaFile(int $MediaID, string $FilePath, bool $FileMustExists)
{
    return true;
}

function IPS_SendMediaEvent(int $MediaID)
{
    return true;
}

/* Link Manager */
function IPS_CreateLink()
{
    $id = IPS\ObjectManager::registerObject(6 /* Link */);
    IPS\LinkManager::createLink($id);
    return $id;
}

function IPS_DeleteLink(int $LinkID)
{
    IPS\LinkManager::deleteLink($LinkID);
    IPS\ObjectManager::unregisterObject($LinkID);
}

function IPS_LinkExists(int $LinkID)
{
    return IPS\LinkManager::linkExists($LinkID);
}

function IPS_GetLink(int $LinkID)
{
    return IPS\LinkManager::getLink($LinkID);
}

function IPS_GetLinkIDByName(string $Name, int $ParentID)
{
    return IPS\LinkManager::getLinkIdByName($Name, $ParentID);
}

function IPS_GetLinkList()
{
    return IPS\LinkManager::getLinkList();
}

function IPS_SetLinkTargetID(int $LinkID, int $ChildID)
{
    IPS\LinkManager::setLinkTargetID($LinkID, $ChildID);
}

/* Profile Manager */
function IPS_CreateVariableProfile(string $ProfileName, int $ProfileType)
{
    IPS\ProfileManager::createVariableProfile($ProfileName, $ProfileType);
}

function IPS_DeleteVariableProfile(string $ProfileName)
{
    IPS\ProfileManager::deleteVariableProfile($ProfileName);
}

function IPS_VariableProfileExists(string $ProfileName)
{
    return IPS\ProfileManager::variableProfileExists($ProfileName);
}

function IPS_GetVariableProfile(string $ProfileName)
{
    return IPS\ProfileManager::getVariableProfile($ProfileName);
}

function IPS_GetVariableProfileList()
{
    return IPS\ProfileManager::getVariableProfileList();
}

function IPS_GetVariableProfileListByType(int $ProfileType)
{
    return IPS\ProfileManager::getVariableProfileListByType($ProfileType);
}

function IPS_SetVariableProfileAssociation(string $ProfileName, $AssociationValue, string $AssociationName, string $AssociationIcon, int $AssociationColor)
{
    IPS\ProfileManager::setVariableProfileAssociation($ProfileName, $AssociationValue, $AssociationName, $AssociationIcon, $AssociationColor);
}

function IPS_SetVariableProfileDigits(string $ProfileName, int $Digits)
{
    IPS\ProfileManager::setVariableProfileDigits($ProfileName, $Digits);
}

function IPS_SetVariableProfileIcon(string $ProfileName, string $Icon)
{
    IPS\ProfileManager::setVariableProfileIcon($ProfileName, $Icon);
}

function IPS_SetVariableProfileText(string $ProfileName, string $Prefix, string $Suffix)
{
    IPS\ProfileManager::setVariableProfileText($ProfileName, $Prefix, $Suffix);
}

function IPS_SetVariableProfileValues(string $ProfileName, float $MinValue, float $MaxValue, float $StepSize)
{
    IPS\ProfileManager::setVariableProfileValues($ProfileName, $MinValue, $MaxValue, $StepSize);
}

/* Kernel */
function IPS_GetKernelDir()
{
    return sys_get_temp_dir();
}

function IPS_GetKernelDirEx()
{
    return sys_get_temp_dir();
}

function IPS_GetKernelRunlevel()
{
    return 10103 /* KR_READY */;
}

function IPS_GetKernelStartTime()
{
    return time();
}

function IPS_GetKernelPlatform()
{
    return 'Stubs';
}

function IPS_GetKernelVersion()
{
    return '5.2';
}

function IPS_GetKernelRevision()
{
    return 'e4b85ff1670f4c936db014d8b6540b2a38776e50';
}

function IPS_GetKernelDate()
{
    return 1566980315;
}

function IPS_GetLogDir()
{
    return sys_get_temp_dir() . '/logs';
}

function IPS_GetSystemLanguage()
{
    return 'de_DE';
}

function IPS_LogMessage(string $Sender, string $Message)
{
    return true;
}

/* License Pool */
function IPS_GetLicensee()
{
    return 'max@mustermann.de';
}

function IPS_GetLimitDemo()
{
    return 0;
}

function IPS_GetLimitServer()
{
    return '';
}

function IPS_GetLimitVariables()
{
    return 0;
}

function IPS_GetLimitWebFront()
{
    return 0;
}

function IPS_GetDemoExpiration()
{
    return 0;
}

function IPS_GetLiveConsoleCRC()
{
    throw new Exception('Not implemented');
}

function IPS_GetLiveConsoleFile()
{
    throw new Exception('Not implemented');
}

function IPS_GetLiveDashboardCRC()
{
    throw new Exception('Not implemented');
}

function IPS_GetLiveDashboardFile()
{
    throw new Exception('Not implemented');
}

function IPS_GetLiveUpdateVersion()
{
    throw new Exception('Not implemented');
}

function IPS_SetLicense(string $Licensee, string $LicenseContent)
{
    throw new Exception('Not implemented');
}

/* Script Engine */
function IPS_RunScript(int $ScriptID)
{
    IPS\ScriptEngine::runScript($ScriptID);
    return true;
}

function IPS_RunScriptEx(int $ScriptID, array $Parameters)
{
    IPS\ScriptEngine::runScriptEx($ScriptID, $Parameters);
    return true;
}

function IPS_RunScriptWait(int $ScriptID)
{
    return IPS\ScriptEngine::runScriptWait($ScriptID);
}

function IPS_RunScriptWaitEx(int $ScriptID, array $Parameters)
{
    return IPS\ScriptEngine::runScriptWaitEx($ScriptID, $Parameters);
}

function IPS_RunScriptText(string $ScriptText)
{
    IPS\ScriptEngine::runScriptText($ScriptText);
    return true;
}

function IPS_RunScriptTextEx(string $ScriptText, array $Parameters)
{
    IPS\ScriptEngine::runScriptTextEx($ScriptText, $Parameters);
    return true;
}

function IPS_RunScriptTextWait(string $ScriptText)
{
    return IPS\ScriptEngine::IPS_RunScriptTextWait($ScriptText);
}

function IPS_RunScriptTextWaitEx(string $ScriptText, array $Parameters)
{
    return IPS\ScriptEngine::runScriptTextWaitEx($ScriptText, $Parameters);
}

function IPS_SemaphoreEnter(string $Name, int $Milliseconds)
{
    return IPS\ScriptEngine::semaphoreEnter($Name, $Milliseconds);
}

function IPS_SemaphoreLeave(string $Name)
{
    return IPS\ScriptEngine::semaphoreLeave($Name);
}

function IPS_ScriptThreadExists(int $ThreadID)
{
    return IPS\ScriptEngine::scriptThreadExists($ThreadID);
}

function IPS_GetScriptThread(int $ThreadID)
{
    return IPS\ScriptEngine::getScriptThread($ThreadID);
}

function IPS_GetScriptThreadList()
{
    return IPS\ScriptEngine::getScriptThreadList();
}

// This function is only usable via JSON-RPC and is only "implemented" in Gluegen but not in the script engine
function IPS_GetScriptThreads(array $Parameter)
{
    throw new Exception('Not implemented');
}

/* Data Server */
function IPS_FunctionExists(string $FunctionName)
{
    return IPS\DataServer::functionExists($FunctionName);
}

function IPS_GetFunction(string $FunctionName)
{
    return IPS\DataServer::getFunction($FunctionName);
}

function IPS_GetFunctionList(int $InstanceID)
{
    return IPS\DataServer::getFunctionList($InstanceID);
}

function IPS_GetFunctionListByModuleID(string $ModuleID)
{
    return IPS\DataServer::getFunctionListByModuleID($ModuleID);
}

function IPS_GetFunctions(array $Parameter)
{
    return IPS\DataServer::getFunctions($Parameter);
}

function IPS_GetFunctionsMap(array $Parameter)
{
    return IPS\DataServer::getFunctionsMap($Parameter);
}

/* Timer Pool */
function IPS_TimerExists(int $TimerID)
{
    throw new Exception('Not implemented');
}

function IPS_GetTimer(int $TimerID)
{
    throw new Exception('Not implemented');
}

function IPS_GetTimerList()
{
    throw new Exception('Not implemented');
}

function IPS_GetTimers(array $Parameter)
{
    throw new Exception('Not implemented');
}

/* Module Loader */
function IPS_LibraryExists(string $LibraryID)
{
    return IPS\ModuleLoader::libraryExists($LibraryID);
}

function IPS_GetLibrary(string $LibraryID)
{
    return IPS\ModuleLoader::getLibrary($LibraryID);
}

function IPS_GetLibraryList()
{
    return IPS\ModuleLoader::getLibraryList();
}

function IPS_GetLibraryModules(string $LibraryID)
{
    return IPS\ModuleLoader::getLibraryModules($LibraryID);
}

function IPS_ModuleExists(string $ModuleID)
{
    return IPS\ModuleLoader::moduleExists($ModuleID);
}

function IPS_GetModule(string $ModuleID)
{
    return IPS\ModuleLoader::getModule($ModuleID);
}

function IPS_GetModuleList()
{
    return IPS\ModuleLoader::getModuleList();
}

function IPS_GetModuleListByType(int $ModuleType)
{
    return IPS\ModuleLoader::getModuleListByType($ModuleType);
}

function IPS_IsModuleCompatible(string $ModuleID, string $ParentModuleID)
{
    throw new Exception('Not implemented');
}

function IPS_GetCompatibleModules(string $ModuleID)
{
    throw new Exception('Not implemented');
}

function IPS_IsInstanceCompatible(int $InstanceID, int $ParentInstanceID)
{
    throw new Exception('Not implemented');
}

function IPS_GetCompatibleInstances(int $InstanceID)
{
    throw new Exception('Not implemented');
}

/* Module Loader - Helper */
function IPS_GetModules(array $Parameter)
{
    if (count($Parameter) == 0) {
        $Parameter = IPS_GetModuleList();
    }
    $result = [];
    foreach ($Parameter as $ModuleID) {
        $result[] = IPS_GetModule($ModuleID);
    }
    return $result;
}

function IPS_GetLibraries(array $Parameter)
{
    if (count($Parameter) == 0) {
        $Parameter = IPS_GetLibraryList();
    }
    $result = [];
    foreach ($Parameter as $LibraryID) {
        $result[] = IPS_GetLibrary($LibraryID);
    }
    return $result;
}

/* ActionPool */
function IPS_GetActions()
{
    return IPS\ActionPool::getActions();
}

function IPS_GetActionsByEnvironment(int $TargetID, string $Environment, bool $IncludeDefault)
{
    return IPS\ActionPool::getActionsByEnvironment($TargetID, $Environment, $IncludeDefault);
}

function IPS_GetActionForm(string $ActionID, array $Parameters)
{
    return IPS\ActionPool::getActionForm($ActionID, $Parameters);
}

function IPS_GetActionReadableCode(string $ActionID, array $Parameters)
{
    return IPS\ActionPool::getActionReadableCode($ActionID, $Parameters);
}

function IPS_RunAction(string $ActionID, array $Parameters)
{
    IPS\ActionPool::runAction($ActionID, $Parameters);
    return true;
}

function IPS_RunActionWait(string $ActionID, array $Parameters)
{
    return IPS\ActionPool::runActionWait($ActionID, $Parameters);
}

/* Presentation Pool */
function IPS_GetPresentations()
{
    return IPS\PresentationPool::getPresentations();
}

function IPS_GetPresentation(string $GUID)
{
    return IPS\PresentationPool::getPresentation($GUID);
}

function IPS_UpdateFormField(string $Name, string $Parameter, $Value, string $SessionID)
{
    return IPS\ActionPool::updateFormField($Name, $Parameter, $Value, $SessionID);
}

/* Settings */
function IPS_GetOption(string $Option)
{
    throw new Exception('Not implemented');
}

function IPS_GetSecurityMode()
{
    throw new Exception('Not implemented');
}

function IPS_GetSnapshot()
{
    throw new Exception('Not implemented');
}

function IPS_GetSnapshotChanges(int $LastTimestamp)
{
    throw new Exception('Not implemented');
}

function IPS_SetOption(string $Option, int $Value)
{
    throw new Exception('Not implemented');
}

function IPS_SetSecurity(int $Mode, string $Password)
{
    throw new Exception('Not implemented');
}

/* Additional */
function IPS_Execute(string $Filename, string $Parameter, bool $ShowWindow, bool $WaitResult)
{
    throw new Exception('Not implemented');
}

function IPS_ExecuteEx(string $Filename, string $Parameter, bool $ShowWindow, bool $WaitResult, int $SessionID)
{
    throw new Exception('Not implemented');
}

function IPS_Sleep(int $Milliseconds)
{
    usleep($Milliseconds * 1000);
}

/* System Information */
function Sys_GetBattery()
{
    return [
        'OnBattery'            => false,
        'IsCharging'           => false,
        'BatteryLevel'         => -1,
        'BatteryRemainingTime' => -1,
        'BatteryMaxTime'       => -1
    ];
}

function Sys_GetCPUInfo()
{
    return [
        'CPU_0'   => 3,
        'CPU_AVG' => 3
    ];
}

function Sys_GetHardDiskInfo()
{
    return [
        0 => [
            'LETTER' => 'c:\\',
            'LABEL'  => '',
            'TOTAL'  => 53684989952,
            'FREE'   => 23275171840
        ],
        'NUMDRIVES' => 1
    ];
}

function Sys_GetMemoryInfo()
{
    return [
        'TOTALPHYSICAL' => 1072467968,
        'AVAILPHYSICAL' => 526647296,
        'TOTALPAGEFILE' => 2420019200,
        'AVAILPAGEFILE' => 1386422272,
        'TOTALVIRTUAL'  => 2147352576,
        'AVAILVIRTUAL'  => 1906978816
    ];
}

function Sys_GetNetworkInfo()
{
    return [
        0 => [
            'InterfaceIndex' => 10,
            'IP'             => '192.168.1.2',
            'MAC'            => '00:A0:03:AD:14:BD',
            'Description'    => 'Siemens I BT USB Remote NDIS Network Device',
            'Speed'          => 9728000,
            'InTotal'        => 40236,
            'OutTotal'       => 247248
        ],
        1 => [
            'InterfaceIndex' => 13,
            'IP'             => '172.12.1.200',
            'MAC'            => '00:A0:03:AD:14:BD',
            'Description'    => 'Qualcomm Atheros AR8151 PCI-E Gigabit Ethernet Controller (NDIS 6.30)',
            'Speed'          => 1000000000,
            'InTotal'        => 169987950,
            'OutTotal'       => 86029648
        ]
    ];
}

function Sys_GetProcessInfo()
{
    return [
        'IPS_HANDLECOUNT'    => 635,
        'IPS_NUMTHREADS'     => 53,
        'IPS_VIRTUALSIZE'    => 240373760,
        'IPS_WORKINGSETSIZE' => 32706560,
        'IPS_PAGEFILE'       => 52719616,
        'PROCESSCOUNT'       => 53
    ];
}

function Sys_GetSpooler()
{
    return [];
}

function Sys_GetURLContent(string $URL)
{
    return '';
}

function Sys_GetURLContentEx(string $URL, array $Parameter)
{
    return '';
}

function Sys_Ping(string $Host, int $Timeout)
{
    return true;
}

