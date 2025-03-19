<?php

declare(strict_types=1);

/* EventConditionComparison */
define('EVENTCONDITIONCOMPARISON_EQUAL', 0);
define('EVENTCONDITIONCOMPARISON_NOTEQUAL', 1);
define('EVENTCONDITIONCOMPARISON_GREATER', 2);
define('EVENTCONDITIONCOMPARISON_GREATEROREQUAL', 3);
define('EVENTCONDITIONCOMPARISON_SMALLER', 4);
define('EVENTCONDITIONCOMPARISON_SMALLEROREQUAL', 5);

/* EventCyclicDateType */
define('EVENTCYCLICDATETYPE_NONE', 0);
define('EVENTCYCLICDATETYPE_ONCE', 1);
define('EVENTCYCLICDATETYPE_DAY', 2);
define('EVENTCYCLICDATETYPE_WEEK', 3);
define('EVENTCYCLICDATETYPE_MONTH', 4);
define('EVENTCYCLICDATETYPE_YEAR', 5);

/* EventCyclicTimeType */
define('EVENTCYCLICTIMETYPE_ONCE', 0);
define('EVENTCYCLICTIMETYPE_SECOND', 1);
define('EVENTCYCLICTIMETYPE_MINUTE', 2);
define('EVENTCYCLICTIMETYPE_HOUR', 3);

/* EventTriggerType */
define('EVENTTRIGGERTYPE_ONUPDATE', 0);
define('EVENTTRIGGERTYPE_ONCHANGE', 1);
define('EVENTTRIGGERTYPE_ONLIMITEXCEED', 2);
define('EVENTTRIGGERTYPE_ONLIMITDROP', 3);
define('EVENTTRIGGERTYPE_ONVALUE', 4);

/* EventType */
define('EVENTTYPE_TRIGGER', 0);
define('EVENTTYPE_CYCLIC', 1);
define('EVENTTYPE_SCHEDULE', 2);

/* MediaType */
define('MEDIATYPE_DASHBOARD', 0);
define('MEDIATYPE_IMAGE', 1);
define('MEDIATYPE_SOUND', 2);
define('MEDIATYPE_STREAM', 3);
define('MEDIATYPE_CHART', 4);
define('MEDIATYPE_DOCUMENT', 5);

/* ModuleType */
define('MODULETYPE_CORE', 0);
define('MODULETYPE_IO', 1);
define('MODULETYPE_SPLITTER', 2);
define('MODULETYPE_DEVICE', 3);
define('MODULETYPE_CONFIGURATOR', 4);
define('MODULETYPE_DISCOVERY', 5);

/* ObjectType */
define('OBJECTTYPE_CATEGORY', 0);
define('OBJECTTYPE_INSTANCE', 1);
define('OBJECTTYPE_VARIABLE', 2);
define('OBJECTTYPE_SCRIPT', 3);
define('OBJECTTYPE_EVENT', 4);
define('OBJECTTYPE_MEDIA', 5);
define('OBJECTTYPE_LINK', 6);

/* ScriptType */
define('SCRIPTTYPE_PHP', 0);

/* VariableType */
define('VARIABLETYPE_BOOLEAN', 0);
define('VARIABLETYPE_INTEGER', 1);
define('VARIABLETYPE_FLOAT', 2);
define('VARIABLETYPE_STRING', 3);

/* VariablePresentation */
define('VARIABLE_PRESENTATION_LEGACY', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}');
define('VARIABLE_PRESENTATION_VALUE_PRESENTATION', '{3319437D-7CDE-699D-750A-3C6A3841FA75}');
define('VARIABLE_PRESENTATION_VALUE_INPUT', '{6F477326-1683-A2FD-D2E7-477F366ECB62}');
define('VARIABLE_PRESENTATION_SLIDER', '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}');
define('VARIABLE_PRESENTATION_WEB_CONTENT', '{9DE1D610-5106-97FB-714D-1AADEDF8377A}');
define('VARIABLE_PRESENTATION_COLOR', '{05CC3CC2-A0B2-5837-A4A7-A07EA0B9DDFB}');
define('VARIABLE_PRESENTATION_DATE_TIME', '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}');
define('VARIABLE_PRESENTATION_SWITCH', '{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}');
define('VARIABLE_PRESENTATION_SHUTTER', '{6075FC22-69AF-B110-3749-C24138883082}');
define('VARIABLE_PRESENTATION_ENUMERATION', '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}');
define('VARIABLE_PRESENTATION_PLAYBACK', '{2F0FF5B0-FC86-117B-DDAA-2D2D33C3F8AC}');
define('VARIABLE_PRESENTATION_DURATION', '{08A6AF76-394E-D354-48D5-BFC690488E4E}');
define('VARIABLE_PRESENTATION_TEXT_BOX', '{56696857-92B2-1780-16B8-EB6F09D4AEF7}');
