<?php

declare(strict_types=1);

namespace IPS {

    class ModuleLoader
    {
        private static $libraries = [];
        private static $modules = [];

        public static function libraryExists(string $LibraryID): bool
        {
            return isset(self::$libraries[$LibraryID]);
        }

        public static function getLibrary(string $LibraryID): array
        {
            self::checkLibrary($LibraryID);

            return self::$libraries[$LibraryID];
        }

        public static function getLibraryList(): array
        {
            return array_keys(self::$libraries);
        }

        public static function getLibraryModules(string $LibraryID): array
        {
            $result = [];
            foreach (self::$modules as $module) {
                if ($module['LibraryID'] == $LibraryID) {
                    $result[] = $module['ModuleID'];
                }
            }

            return $result;
        }

        public static function moduleExists(string $ModuleID): bool
        {
            return isset(self::$modules[$ModuleID]);
        }

        public static function getModule(string $ModuleID): array
        {
            self::checkModule($ModuleID);

            return self::$modules[$ModuleID];
        }

        public static function getModuleList(): array
        {
            return array_keys(self::$modules);
        }

        public static function getModuleListByType(int $ModuleType): array
        {
            $result = [];
            foreach (self::$modules as $module) {
                if ($module['ModuleType'] == $ModuleType) {
                    $result[] = $module['ModuleID'];
                }
            }

            return $result;
        }

        public static function loadLibrary(string $file): void
        {
            $library = json_decode(file_get_contents($file), true);
            self::$libraries[$library['id']] = [
                'LibraryID' => $library['id'],
                'Author'    => $library['author'],
                'URL'       => $library['url'],
                'Name'      => $library['name'],
                'Version'   => $library['version'],
                'Build'     => $library['build'],
                'Date'      => $library['date'],
            ];
            self::loadModules(dirname($file), $library['id']);
        }

        public static function loadSingleModule(string $folder, string $libraryID)
        {
            self::loadModule($folder, $libraryID);
        }

        public static function reset()
        {
            self::$libraries = [];
            self::$modules = [];
        }

        private static function checkLibrary(string $LibraryID): void
        {
            if (!self::libraryExists($LibraryID)) {
                throw new \Exception(sprintf('Library #%s does not exist', $LibraryID));
            }
        }

        private static function checkModule(string $ModuleID): void
        {
            if (!self::moduleExists($ModuleID)) {
                throw new \Exception(sprintf('Module #%s does not exist', $ModuleID));
            }
        }

        private static function loadModules(string $folder, string $libraryID): void
        {
            $modules = glob($folder . '/*', GLOB_ONLYDIR);
            $filter = ['libs', 'docs', 'imgs', 'tests', 'actions'];
            foreach ($modules as $module) {
                if (!in_array(basename($module), $filter)) {
                    self::loadModule($module, $libraryID);
                }
            }
        }

        private static function loadModule(string $folder, string $libraryID): void
        {
            $module = json_decode(file_get_contents($folder . '/module.json'), true);
            self::$modules[$module['id']] = [
                'ModuleID'           => $module['id'],
                'ModuleName'         => $module['name'],
                'ModuleType'         => $module['type'],
                'Vendor'             => $module['vendor'],
                'Aliases'            => $module['aliases'] ?? [],
                'ParentRequirements' => $module['parentRequirements'] ?? [],
                'ChildRequirements'  => $module['childRequirements'] ?? [],
                'Implemented'        => $module['implemented'] ?? [],
                'LibraryID'          => $libraryID,
                'Prefix'             => $module['prefix'],
                'URL'                => $module['url'] ?? '',
                'Limit'              => 0,
                'Translation'        => $module['translations'] ?? [],
                'Class'              => str_replace(' ', '', $module['name'])
            ];

            //Include module class file
            require_once $folder . '/module.php';

            self::registerFunctions($module['name'], $module['prefix']);
        }

        private static function registerFunctions($moduleName, $modulePrefix)
        {
            $class = new \ReflectionClass(str_replace(' ', '', $moduleName));
            foreach ($class->GetMethods() as $method) {
                if (!$method->isPublic()) {
                    continue;
                }
                if (in_array($method->GetName(), ['__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__sleep', '__wakeup', '__toString', '__invoke', '__set_state', '__clone', '__debuginfo', 'Create', 'Destroy', 'ApplyChanges', 'ReceiveData', 'ForwardData', 'RequestAction', 'MessageSink', 'GetConfigurationForm', 'GetConfigurationForParent', 'Translate', 'GetCompatibleParents'])) {
                    continue;
                }
                $params = ['int $InstanceID'];
                $fwdparams = [];
                foreach ($method->getParameters() as $parameter) {
                    $type = @$parameter->GetClass();
                    if ($type !== false && $type !== null) {
                        $type = strtolower($type->GetName());
                    } else {
                        $type = $parameter->GetType();
                        if ($type !== null) {
                            $type = $type->GetName();
                        }
                    }
                    $params[] = $type . ' $' . $parameter->GetName();
                    $fwdparams[] = '$' . $parameter->GetName();
                }
                $function = sprintf('function %s_%s(%s){$result = IPS\InstanceManager::getInstanceInterface($InstanceID)->%s(%s); IPS\TimeManager::updateTime($InstanceID); return $result;}', $modulePrefix, $method->GetName(), implode(', ', $params), $method->GetName(), implode(', ', $fwdparams));
                if (!\function_exists($modulePrefix . '_' . $method->GetName())) {
                    eval($function);
                }
            }
        }
    }

    //Mimics the value conversions of the kernel
    class ValueConverter
    {
        //Equivalent to the FETCH_*_PARAMETER macros, which are used for function parameters like the value of SetValue
        public static function toBoolean($Value, string $Parameter = 'Value'): bool
        {
            if (($Value === null) || is_bool($Value)) {
                return (bool) $Value;
            }
            if (is_int($Value) || is_float($Value)) {
                return $Value != 0;
            }
            if (is_string($Value)) {
                $lower = strtolower(trim($Value));
                if ($lower === 'true') {
                    return true;
                }
                if ($lower === 'false') {
                    return false;
                }
                return self::roundInteger(self::parseFloat($Value, $Parameter)) != 0;
            }
            throw self::autoConvertError($Parameter, 'Type does not match');
        }

        public static function toInteger($Value, string $Parameter = 'Value'): int
        {
            if (($Value === null) || is_bool($Value) || is_int($Value)) {
                return (int) $Value;
            }
            if (is_float($Value)) {
                return self::roundInteger($Value);
            }
            if (is_string($Value)) {
                return self::roundInteger(self::parseFloat($Value, $Parameter));
            }
            throw self::autoConvertError($Parameter, 'Type does not match');
        }

        public static function toFloat($Value, string $Parameter = 'Value'): float
        {
            if (($Value === null) || is_bool($Value) || is_int($Value) || is_float($Value)) {
                return (float) $Value;
            }
            if (is_string($Value)) {
                return self::parseFloat($Value, $Parameter);
            }
            throw self::autoConvertError($Parameter, 'Type does not match');
        }

        public static function toString($Value, string $Parameter = 'Value'): string
        {
            if ($Value === null) {
                return '';
            }
            if (is_bool($Value)) {
                return $Value ? 'True' : 'False';
            }
            if (is_int($Value) || is_float($Value)) {
                return strval($Value);
            }
            if (is_string($Value)) {
                if (!mb_check_encoding($Value, 'UTF-8')) {
                    throw self::autoConvertError($Parameter, 'Value is not encoded as valid UTF-8!');
                }
                return $Value;
            }
            throw self::autoConvertError($Parameter, 'Type does not match');
        }

        public static function toVariableType($Value, int $VariableType, string $Parameter = 'Value')
        {
            switch ($VariableType) {
                case 0: /* Boolean */
                    return self::toBoolean($Value, $Parameter);
                case 1: /* Integer */
                    return self::toInteger($Value, $Parameter);
                case 2: /* Float */
                    return self::toFloat($Value, $Parameter);
                case 3: /* String */
                    return self::toString($Value, $Parameter);
                default:
                    throw new \Exception('Unsupported VariableType!');
            }
        }

        //Equivalent to the ConvertValueVisitor, which is used for setting properties
        public static function toPropertyType(string $Name, $Value, int $PropertyType)
        {
            try {
                if (is_bool($Value)) {
                    switch ($PropertyType) {
                        case 0: return $Value;
                        case 1: return $Value ? 1 : 0;
                        case 2: return $Value ? 1.0 : 0.0;
                        case 3: return $Value ? 'True' : 'False';
                    }
                } elseif (is_int($Value) || is_float($Value)) {
                    switch ($PropertyType) {
                        case 0: return $Value > 0;
                        case 1: return (int) $Value;
                        case 2: return (float) $Value;
                        case 3: return strval($Value);
                    }
                } elseif (is_string($Value)) {
                    switch ($PropertyType) {
                        case 0: return $Value === 'True';
                        case 1: return self::roundInteger(self::parseFloat($Value));
                        case 2: return self::parseFloat($Value);
                        case 3: return $Value;
                    }
                }
                throw new \Exception('Parameter type of Value does not match');
            } catch (\Exception $e) {
                throw new \Exception(sprintf('Cannot set property %s: %s', $Name, $e->getMessage()));
            }
        }

        //Equivalent to std::stod(StrTrim(FixDecimalCharacter(...))), which parses the leading number of a string
        private static function parseFloat(string $Value, string $Parameter = ''): float
        {
            if (!preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?/', trim(str_replace(',', '.', $Value)), $matches)) {
                if ($Parameter === '') {
                    throw new \Exception('stod');
                }
                throw self::autoConvertError($Parameter, 'stod');
            }
            return (float) $matches[0];
        }

        private static function roundInteger(float $Value): int
        {
            return (int) round($Value);
        }

        private static function autoConvertError(string $Parameter, string $Reason): \Exception
        {
            return new \Exception(sprintf('Cannot auto-convert value for parameter %s (%s)', $Parameter, $Reason));
        }
    }

    class ObjectManager
    {
        private static $availableIDs = [];

        private static $objects = [];

        public static function registerObject(int $ObjectType): int
        {
            if (count(self::$objects) == 0) {
                throw new \Exception('Reset was not called on Kernel.');
            }

            //Initialize
            if (count(self::$availableIDs) == 0 && count(self::$objects) == 1) {
                for ($i = 10000; $i < 60000; $i++) {
                    self::$availableIDs[] = $i;
                }
                shuffle(self::$availableIDs);
            }

            //Check for availability
            if (count(self::$availableIDs) == 0) {
                throw new \Exception('No usable IDs left. Please contact support.');
            }

            //Fetch first. The array is already randomized
            $id = array_shift(self::$availableIDs);

            //Add object
            self::$objects[$id] = [
                'ObjectID'         => $id,
                'ObjectType'       => $ObjectType,
                'ObjectName'       => sprintf('Unnamed Object (ID: %d)', $id),
                'ObjectIcon'       => '',
                'ObjectInfo'       => '',
                'ObjectIdent'      => '',
                'ObjectSummary'    => '',
                'ObjectIsHidden'   => false,
                'ObjectIsDisabled' => false,
                'ObjectIsLocked'   => false,
                'ObjectIsReadOnly' => false,
                'ObjectPosition'   => 0,
                'ParentID'         => 0,
                'ChildrenIDs'      => [],
                'HasChildren'      => false
            ];

            //Add to root
            self::$objects[0]['ChildrenIDs'][] = $id;
            self::$objects[0]['HasChildren'] = true;

            return $id;
        }

        public static function unregisterObject(int $ID): void
        {
            self::checkObject($ID);

            if (self::hasChildren($ID)) {
                throw new \Exception('Cannot call UnregisterObject if a children is present');
            }

            //Delete ID from Children array
            $ParentID = self::$objects[$ID]['ParentID'];
            if (($key = array_search($ID, self::$objects[$ParentID]['ChildrenIDs'])) !== false) {
                unset(self::$objects[$ParentID]['ChildrenIDs'][$key]);
                self::$objects[$ParentID]['ChildrenIDs'] = array_values(self::$objects[$ParentID]['ChildrenIDs']);
            }
            self::$objects[$ParentID]['HasChildren'] = count(self::$objects[$ParentID]['ChildrenIDs']) > 0;

            //Remove object
            unset(self::$objects[$ID]);

            //Readd ID to available pool
            self::$availableIDs[] = $ID;
        }

        public static function setParent(int $ID, int $ParentID): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            if (self::isChild($ParentID, $ID, true)) {
                throw new \Exception('Cannot assign a parent object to his child');
            }

            if ($ID == $ParentID) {
                throw new \Exception('Cannot assign self as a parent object');
            }

            $OldParentID = self::$objects[$ID]['ParentID'];
            if ($OldParentID == $ParentID) {
                return;
            }

            if (self::$objects[$ID]['ObjectIdent'] != '') {
                if (self::findObjectIDByIdent(self::$objects[$ID]['ObjectIdent'], $ParentID) !== false) {
                    throw new \Exception('Ident must be unique for each category');
                }
            }

            self::$objects[$OldParentID]['ChildrenIDs'] = array_values(array_diff(self::$objects[$OldParentID]['ChildrenIDs'], [$ID]));
            self::$objects[$OldParentID]['HasChildren'] = count(self::$objects[$OldParentID]['ChildrenIDs']) > 0;
            self::$objects[$ID]['ParentID'] = $ParentID;
            self::$objects[$ParentID]['ChildrenIDs'][] = $ID;
            self::$objects[$ParentID]['HasChildren'] = true;
        }

        public static function setIdent(int $ID, string $Ident): void
        {
            self::checkObject($ID);

            if (!preg_match('/^[a-zA-Z0-9_]*$/', $Ident)) {
                throw new \Exception('Ident may contain only letters and numbers');
            }

            if ($Ident != '') {
                $ParentID = self::$objects[$ID]['ParentID'];
                foreach (self::$objects[$ParentID]['ChildrenIDs'] as $ChildID) {
                    if (self::$objects[$ChildID]['ObjectIdent'] == $Ident) {
                        if ($ChildID != $ID) {
                            throw new \Exception('Ident must be unique for each category');
                        }
                    }
                }
            }

            self::$objects[$ID]['ObjectIdent'] = $Ident;
        }

        public static function setName(int $ID, string $Name): void
        {
            self::checkObject($ID);

            if ($Name == '') {
                $Name = sprintf('Unnamed Object (ID: %d)', $ID);
            }

            self::$objects[$ID]['ObjectName'] = $Name;
        }

        public static function setInfo(int $ID, string $Info): void
        {
            self::checkObject($ID);

            self::$objects[$ID]['ObjectInfo'] = $Info;
        }

        public static function setIcon(int $ID, string $Icon): void
        {
            self::checkObject($ID);

            if (strpos($Icon, ' ') !== false) {
                throw new \Exception('Icons must not contain spaces!');
            }

            self::$objects[$ID]['ObjectIcon'] = $Icon;
        }

        public static function setSummary(int $ID, string $Summary): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[$ID]['ObjectSummary'] = $Summary;
        }

        public static function setPosition(int $ID, int $Position): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[$ID]['ObjectPosition'] = $Position;
        }

        public static function setReadOnly(int $ID, bool $ReadOnly): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[$ID]['ObjectIsReadOnly'] = $ReadOnly;
        }

        public static function setHidden(int $ID, bool $Hidden): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[$ID]['ObjectIsHidden'] = $Hidden;
        }

        public static function setDisabled(int $ID, bool $Disabled): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[$ID]['ObjectIsDisabled'] = $Disabled;
        }

        public static function objectExists(int $ID): bool
        {
            return isset(self::$objects[$ID]);
        }

        public static function getObject(int $ID): array
        {
            self::checkObject($ID);

            return self::$objects[$ID];
        }

        public static function getObjectList(): array
        {
            return array_keys(self::$objects);
        }

        public static function getObjectIDByName(string $Name, int $ParentID): int|false
        {
            if ($Name == '') {
                throw new \Exception('Name cannot be empty');
            }

            self::checkObject($ParentID);
            foreach (self::$objects[$ParentID]['ChildrenIDs'] as $ChildID) {
                self::checkObject($ChildID);
                if (self::$objects[$ChildID]['ObjectName'] == $Name) {
                    return $ChildID;
                }
            }

            trigger_error(sprintf('Object with name %s could not be found', $Name), E_USER_WARNING);
            return false;
        }

        public static function getObjectIDByNameEx(string $Name, int $ParentID, int $ObjectType): int|false
        {
            if ($Name == '') {
                throw new \Exception('Name cannot be empty');
            }

            self::checkObject($ParentID);
            foreach (self::$objects[$ParentID]['ChildrenIDs'] as $ChildID) {
                self::checkObject($ChildID);
                if (self::$objects[$ChildID]['ObjectType'] == $ObjectType) {
                    if (self::$objects[$ChildID]['ObjectName'] == $Name) {
                        return $ChildID;
                    }
                }
            }

            trigger_error(sprintf('Object with name %s could not be found', $Name), E_USER_WARNING);
            return false;
        }

        public static function getObjectIDByIdent(string $Ident, int $ParentID)
        {
            if ($Ident == '') {
                throw new \Exception('Ident cannot be empty');
            }

            $id = self::findObjectIDByIdent($Ident, $ParentID);
            if ($id === false) {
                trigger_error(sprintf('Object with ident %s could not be found', $Ident), E_USER_WARNING);
            }
            return $id;
        }

        public static function findObjectIDByIdent(string $Ident, int $ParentID): int|false
        {
            self::checkObject($ParentID);
            foreach (self::$objects[$ParentID]['ChildrenIDs'] as $ChildID) {
                self::checkObject($ChildID);
                if (self::$objects[$ChildID]['ObjectIdent'] == $Ident) {
                    return $ChildID;
                }
            }

            return false;
        }

        public static function checkNoChildren(int $ID, string $Type): void
        {
            if (self::objectExists($ID) && self::hasChildren($ID)) {
                throw new \Exception(sprintf('Cannot delete %s if it has child objects', $Type));
            }
        }

        public static function hasChildren(int $ID): bool
        {
            return count(self::getChildrenIDs($ID)) > 0;
        }

        public static function isChild(int $ID, int $ParentID, bool $Recursive): bool
        {
            self::checkObject($ID);
            self::checkObject($ParentID);

            while ($ID != 0) {
                $ID = self::$objects[$ID]['ParentID'];
                if ($ID == $ParentID) {
                    return true;
                }
                if (!$Recursive) {
                    break;
                }
            }

            return false;
        }

        public static function getChildrenIDs(int $ID): array
        {
            self::checkObject($ID);

            return self::$objects[$ID]['ChildrenIDs'];
        }

        public static function getName(int $ID): string
        {
            self::checkObject($ID);

            return self::$objects[$ID]['ObjectName'];
        }

        public static function getParent(int $ID): int
        {
            self::checkObject($ID);

            return self::$objects[$ID]['ParentID'];
        }

        public static function getLocation(int $ID): string
        {
            $result = self::getName($ID);
            $parentID = self::getParent($ID);

            while ($parentID > 0) {
                $result = self::getName($parentID) . '\\' . $result;
                $parentID = self::getParent($parentID);
            }

            return $result;
        }

        public static function reset()
        {
            self::$availableIDs = [];
            self::$objects = [
                0 => [
                    'ObjectID'         => 0,
                    'ObjectType'       => 0 /* Category */,
                    'ObjectName'       => 'IP-Symcon',
                    'ObjectIcon'       => '',
                    'ObjectInfo'       => '',
                    'ObjectIdent'      => '',
                    'ObjectSummary'    => '',
                    'ObjectIsHidden'   => false,
                    'ObjectIsDisabled' => false,
                    'ObjectIsLocked'   => false,
                    'ObjectIsReadOnly' => false,
                    'ObjectPosition'   => 0,
                    'ParentID'         => 0,
                    'ChildrenIDs'      => [],
                    'HasChildren'      => false
                ]
            ];
        }

        private static function checkRoot(int $ID): void
        {
            if ($ID == 0) {
                throw new \Exception('Cannot change root');
            }
        }

        private static function checkObject(int $ID): void
        {
            if (!self::objectExists($ID)) {
                throw new \Exception(sprintf('Object #%d does not exist', $ID));
            }
        }
    }

    class CategoryManager
    {
        private static $categories = [];

        public static function createCategory(int $CategoryID): void
        {
            self::$categories[$CategoryID] = [];
        }

        public static function deleteCategory(int $CategoryID): void
        {
            self::checkCategory($CategoryID);
            unset(self::$categories[$CategoryID]);
        }

        public static function categoryExists(int $CategoryID): bool
        {
            return isset(self::$categories[$CategoryID]);
        }

        public static function getCategory(int $CategoryID): array
        {
            self::checkCategory($CategoryID);

            return [
                'CategoryID' => $CategoryID
            ];
        }

        public static function getCategoryList(): array
        {
            return array_keys(self::$categories);
        }

        public static function reset()
        {
            self::$categories = [];
        }

        private static function checkCategory(int $CategoryID): void
        {
            if (!self::categoryExists($CategoryID)) {
                throw new \Exception(sprintf('Category #%d does not exist', $CategoryID));
            }
        }
    }

    class InstanceManager
    {
        private static $instances = [];
        private static $interfaces = [];

        public static function createInstance(int $InstanceID, array $Module): void
        {
            if (!class_exists($Module['Class'])) {
                throw new \Exception(sprintf('Cannot find class %s', $Module['Class']));
            }

            if (!in_array('IPSModule', class_parents($Module['Class'])) && !in_array('IPSModuleStrict', class_parents($Module['Class']))) {
                throw new \Exception(sprintf('Class %s does not inherit from IPSModule or IPSModuleStrict', $Module['Class']));
            }

            self::$instances[$InstanceID] = [
                'InstanceID'                => $InstanceID,
                'ConnectionID'              => 0,
                'InstanceStatus'            => 101 /* IS_CREATING */,
                'InstanceChanged'           => TimeManager::getTime(),
                'InstanceSupportsSearching' => false,
                'InstanceIsSearching'       => false,
                'InstanceVisualizationType' => 0 /* INSTANCE_VISUALIZATION_TYPE_NONE */,
                'ModuleInfo'                => [
                    'ModuleID'   => $Module['ModuleID'],
                    'ModuleName' => $Module['ModuleName'],
                    'ModuleType' => $Module['ModuleType']
                ],
            ];

            $interface = new $Module['Class']($InstanceID);

            self::$interfaces[$InstanceID] = $interface;

            if (($interface instanceof \IPSModule) || ($interface instanceof \IPSModuleStrict)) {
                $interface->Create();
                $interface->ApplyChanges();
            }

            //Apply the active status to all instances which did not use SetStatus themselves
            if (self::$instances[$InstanceID]['InstanceStatus'] == 101 /* IS_CREATING */) {
                self::$instances[$InstanceID]['InstanceStatus'] = 102 /* IS_ACTIVE */;
            }
        }

        public static function deleteInstance(int $InstanceID): void
        {
            self::checkInstance($InstanceID);
            unset(self::$instances[$InstanceID]);
            unset(self::$interfaces[$InstanceID]);
        }

        //Mimics the kernel calling Migrate on IP-Symcon start/module update and persisting the result
        public static function migrateInstance(int $InstanceID): bool
        {
            self::checkInstance($InstanceID);

            $interface = self::$interfaces[$InstanceID];

            $result = $interface->Migrate(json_encode($interface->GetPersistence()));
            if (!is_string($result) || ($result === '')) {
                return false;
            }

            $persistence = json_decode($result, true);
            if (!is_array($persistence)) {
                throw new \Exception('Migrate returned invalid JSON');
            }

            $interface->ApplyPersistence($persistence);
            return true;
        }

        public static function instanceExists(int $InstanceID): bool
        {
            return isset(self::$instances[$InstanceID]);
        }

        public static function getInstance(int $InstanceID): array
        {
            self::checkInstance($InstanceID);

            return self::$instances[$InstanceID];
        }

        public static function getInstanceInterface(int $InstanceID): mixed
        {
            self::checkInstance($InstanceID);

            return self::$interfaces[$InstanceID];
        }

        public static function getInstanceList(): array
        {
            return array_keys(self::$instances);
        }

        public static function getInstanceListByModuleType(int $ModuleType): array
        {
            $result = [];
            foreach (self::$instances as $instance) {
                if ($instance['ModuleInfo']['ModuleType'] == $ModuleType) {
                    $result[] = $instance['InstanceID'];
                }
            }

            return $result;
        }

        public static function getInstanceListByModuleID(string $ModuleID): array
        {
            $result = [];
            foreach (self::$instances as $instance) {
                if ($instance['ModuleInfo']['ModuleID'] == $ModuleID) {
                    $result[] = $instance['InstanceID'];
                }
            }

            return $result;
        }

        public static function setStatus($InstanceID, $Status): void
        {
            self::checkInstance($InstanceID);

            self::$instances[$InstanceID]['InstanceStatus'] = $Status;
        }

        public static function setVisualizationType(int $InstanceID, int $VisualizationType): void
        {
            self::checkInstance($InstanceID);

            self::$instances[$InstanceID]['InstanceVisualizationType'] = $VisualizationType;
        }

        public static function getStatus($InstanceID): int
        {
            self::checkInstance($InstanceID);

            return self::$instances[$InstanceID]['InstanceStatus'];
        }

        public static function connectInstance(int $InstanceID, int $ParentID): void
        {
            self::checkInstance($InstanceID);

            if (self::$instances[$InstanceID]['ConnectionID'] != 0) {
                throw new \Exception(sprintf('Instance #%d already has a parent', $InstanceID));
            }

            self::checkInstance($ParentID);

            if ($InstanceID == $ParentID) {
                throw new \Exception('Cannot connect ourselves as a parent');
            }

            switch (self::$instances[$InstanceID]['ModuleInfo']['ModuleType']) {
                case 0: /* Core */
                    throw new \Exception('Core instances cannot be connected to any other instance');
                case 1: /* I/O */
                    throw new \Exception('I/O instance cannot be connected to any other instance');
                case 2: /* Splitter */
                case 3: /* Device */
                case 4: /* Configurator */
                case 5: /* Discovery */
                case 6: /* Visualization */
                    if (!in_array(self::$instances[$ParentID]['ModuleInfo']['ModuleType'], [1 /* I/O */, 2 /* Splitter */])) {
                        throw new \Exception('Device/Configuration/Discovery/Visualization/Splitter instances can only be connected to other Splitter or I/O instances');
                    }
                    break;
                default:
                    throw new \Exception('Unknown instance module type');
            }

            $child = ModuleLoader::getModule(self::$instances[$InstanceID]['ModuleInfo']['ModuleID']);
            $parent = ModuleLoader::getModule(self::$instances[$ParentID]['ModuleInfo']['ModuleID']);
            if ((count(array_intersect($parent['ChildRequirements'], $child['Implemented'])) == 0) || (count(array_intersect($child['ParentRequirements'], $parent['Implemented'])) == 0)) {
                throw new \Exception('Dataflow is incompatible');
            }

            $connectionID = self::$instances[$ParentID]['ConnectionID'];
            while ($connectionID > 0) {
                if ($connectionID == $InstanceID) {
                    throw new \Exception('Building a loop is not allowed');
                }
                self::checkInstance($connectionID);
                $connectionID = self::$instances[$connectionID]['ConnectionID'];
            }

            self::$instances[$InstanceID]['ConnectionID'] = $ParentID;
        }

        public static function disconnectInstance(int $InstanceID): void
        {
            self::checkInstance($InstanceID);

            if (self::$instances[$InstanceID]['ConnectionID'] == 0) {
                throw new \Exception(sprintf('Instance #%d does not have a parent', $InstanceID));
            }

            self::$instances[$InstanceID]['ConnectionID'] = 0;
        }

        public static function getReferenceList($InstanceID)
        {
            self::checkInstance($InstanceID);

            return self::$interfaces[$InstanceID]->GetReferenceList();
        }

        public static function reset()
        {
            self::$instances = [];
            self::$interfaces = [];
        }

        public static function checkInstance(int $InstanceID): void
        {
            if (!self::instanceExists($InstanceID)) {
                throw new \Exception(sprintf('Instance #%d does not exist', $InstanceID));
            }
        }
    }

    class TimeManager
    {
        private static $time = null;

        // Remember the time of the module after each function call, so timestamps follow the time simulated by tests
        public static function updateTime(int $InstanceID): void
        {
            $interface = InstanceManager::getInstanceInterface($InstanceID);
            if (!method_exists($interface, 'getTime')) {
                return;
            }
            $method = new \ReflectionMethod($interface, 'getTime');
            // Protected methods are accessible by default since PHP 8.1 and setAccessible is deprecated since PHP 8.5
            if (PHP_VERSION_ID < 80100) {
                $method->setAccessible(true);
            }
            try {
                self::$time = $method->invoke($interface);
            } catch (\Throwable $e) {
                // Module does not implement getTime, keep the last known time
            }
        }

        // Last known time of a module or the real time if no module provided a time yet
        public static function getTime(): int
        {
            return self::$time ?? time();
        }

        public static function reset()
        {
            self::$time = null;
        }
    }

    class VariableManager
    {
        private static $variables = [];

        public static function createVariable(int $VariableID, int $VariableType): void
        {
            switch ($VariableType) {
                case 0: /* Boolean */
                    $VariableValue = false;
                    break;
                case 1: /* Integer */
                    $VariableValue = 0;
                    break;
                case 2: /* Float */
                    $VariableValue = 0.0;
                    break;
                case 3: /* String */
                    $VariableValue = '';
                    break;
                default:
                    throw new \Exception('Unsupported VariableType!');
            }

            self::$variables[$VariableID] = [
                'VariableID'                  => $VariableID,
                'VariableProfile'             => '',
                'VariableAction'              => 0,
                'VariablePresentation'        => [],
                'VariableCustomProfile'       => '',
                'VariableCustomAction'        => 0,
                'VariableCustomPresentation'  => [],
                'VariableUpdated'             => 0,
                'VariableChanged'             => 0,
                'VariableType'                => $VariableType,
                'VariableValue'               => $VariableValue,
                'VariableIsLocked'            => false
            ];
        }

        public static function deleteVariable(int $VariableID): void
        {
            self::checkVariable($VariableID);
            unset(self::$variables[$VariableID]);
        }

        public static function readVariableBoolean(int $VariableID): bool
        {
            self::checkVariableType($VariableID, 0 /* Boolean */);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableBoolean(int $VariableID, bool $VariableValue): void
        {
            self::checkVariableType($VariableID, 0 /* Boolean */);

            self::$variables[$VariableID]['VariableUpdated'] = TimeManager::getTime();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = TimeManager::getTime();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableInteger(int $VariableID): int
        {
            self::checkVariableType($VariableID, 1 /* Integer */);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableInteger(int $VariableID, int $VariableValue): void
        {
            self::checkVariableType($VariableID, 1 /* Integer */);

            self::$variables[$VariableID]['VariableUpdated'] = TimeManager::getTime();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = TimeManager::getTime();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableFloat(int $VariableID): float
        {
            self::checkVariableType($VariableID, 2 /* Float */);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableFloat(int $VariableID, float $VariableValue): void
        {
            self::checkVariableType($VariableID, 2 /* Float */);

            if (!is_finite($VariableValue)) {
                throw new \Exception('NaN/INF values are not supported');
            }

            self::$variables[$VariableID]['VariableUpdated'] = TimeManager::getTime();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = TimeManager::getTime();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableString(int $VariableID): string
        {
            self::checkVariableType($VariableID, 3 /* String */);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableString(int $VariableID, string $VariableValue): void
        {
            self::checkVariableType($VariableID, 3 /* String */);

            if (strlen($VariableValue) > 1024 * 1024) {
                throw new \Exception('Limit of 1024kb exceeded. Variable has not been written.');
            }

            if (!mb_check_encoding($VariableValue, 'UTF-8')) {
                throw new \Exception('Variable value must be UTF-8 encoded since Symcon 6.3!');
            }

            self::$variables[$VariableID]['VariableUpdated'] = TimeManager::getTime();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = TimeManager::getTime();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        //Converts the value to the type of the variable like the kernel does for SetValue
        public static function writeVariable(int $VariableID, $VariableValue): void
        {
            self::checkVariable($VariableID);

            switch (self::$variables[$VariableID]['VariableType']) {
                case 0: /* Boolean */
                    self::writeVariableBoolean($VariableID, ValueConverter::toBoolean($VariableValue));
                    break;
                case 1: /* Integer */
                    self::writeVariableInteger($VariableID, ValueConverter::toInteger($VariableValue));
                    break;
                case 2: /* Float */
                    self::writeVariableFloat($VariableID, ValueConverter::toFloat($VariableValue));
                    break;
                case 3: /* String */
                    self::writeVariableString($VariableID, ValueConverter::toString($VariableValue));
                    break;
            }
        }

        public static function variableExists(int $VariableID): bool
        {
            return isset(self::$variables[$VariableID]);
        }

        public static function checkNotReadOnly(int $VariableID): void
        {
            self::checkVariable($VariableID);

            if (ObjectManager::getObject($VariableID)['ObjectIsReadOnly']) {
                throw new \Exception('Variable is marked as read-only and cannot be changed');
            }
        }

        public static function checkVariableType(int $VariableID, int $VariableType): void
        {
            self::checkVariable($VariableID);

            if (self::$variables[$VariableID]['VariableType'] != $VariableType) {
                throw new \Exception('VariableType does not match');
            }
        }

        public static function checkVariableTypeRange(int $VariableType): void
        {
            if (($VariableType < 0) || ($VariableType > 3)) {
                throw new \Exception('VariableType must be between 0 and 3');
            }
        }

        public static function checkVariable(int $VariableID): void
        {
            if (!self::variableExists($VariableID)) {
                throw new \Exception(sprintf('Variable #%d does not exist', $VariableID));
            }
        }

        public static function getVariable(int $VariableID): array
        {
            self::checkVariable($VariableID);

            return self::$variables[$VariableID];
        }

        public static function getVariablePresentation(int $VariableID): array
        {
            self::checkVariable($VariableID);
            $variable = self::getVariable($VariableID);
            if (!empty($variable['VariableCustomPresentation'])) {
                return $variable['VariableCustomPresentation'];
            }
            return $variable['VariablePresentation'];
        }

        public static function getVariableList(): array
        {
            return array_keys(self::$variables);
        }

        public static function setVariableCustomProfile(int $VariableID, string $ProfileName): void
        {
            self::checkVariable($VariableID);

            //The kernel still accepts the removed ~String profile and replaces it with an empty profile
            if ($ProfileName == '~String') {
                trigger_error(sprintf('Profile ~String is not available since Symcon 5.0. Just use an empty profile. This will be an error in future versions! Check your IPS_SetVariableCustomProfile usage for VariableID %d', $VariableID), E_USER_DEPRECATED);
                $ProfileName = '';
            }
            self::checkProfileType($VariableID, $ProfileName);

            self::$variables[$VariableID]['VariableCustomProfile'] = $ProfileName;

            if (empty($ProfileName)) {
                self::setVariableCustomPresentation($VariableID, []);
            } else {
                self::setVariableCustomPresentation($VariableID, ['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'PROFILE' => $ProfileName]);
            }
        }

        public static function setVariableCustomAction(int $VariableID, int $ScriptID): void
        {
            self::checkVariable($VariableID);

            //1 is a special value, which is allowed although it is invalid
            if ($ScriptID > 1) {
                ScriptManager::checkScript($ScriptID);
            }

            self::$variables[$VariableID]['VariableCustomAction'] = $ScriptID;
        }

        public static function setVariableCustomPresentation(int $VariableID, array $Presentation): void
        {
            self::checkVariable($VariableID);
            self::checkPresentationFormat($Presentation);

            self::$variables[$VariableID]['VariableCustomPresentation'] = $Presentation;
        }

        public static function setVariableProfile(int $VariableID, string $ProfileName): void
        {
            self::checkVariable($VariableID);
            self::checkProfileType($VariableID, $ProfileName);

            self::$variables[$VariableID]['VariableProfile'] = $ProfileName;

            if (empty($ProfileName)) {
                self::setVariablePresentation($VariableID, []);
            } else {
                self::setVariablePresentation($VariableID, ['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'PROFILE' => $ProfileName]);
            }
        }

        public static function setVariableAction(int $VariableID, int $InstanceID): void
        {
            //1 is a special value, which is allowed although it is invalid
            if ($InstanceID > 1) {
                InstanceManager::checkInstance($InstanceID);
            }

            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableAction'] = $InstanceID;
        }

        public static function setVariablePresentation(int $VariableID, array $Presentation): void
        {
            self::checkVariable($VariableID);
            self::checkPresentationFormat($Presentation);

            self::$variables[$VariableID]['VariablePresentation'] = $Presentation;
        }

        public static function reset()
        {
            self::$variables = [];
        }

        private static function checkProfileType(int $VariableID, string $ProfileName): void
        {
            //This will also check for the existence of the profile
            if ($ProfileName !== '') {
                if (ProfileManager::getVariableProfile($ProfileName)['ProfileType'] != self::$variables[$VariableID]['VariableType']) {
                    throw new \Exception('Variable Type and Profile Type do not match!');
                }
            }
        }

        private static function checkPresentationFormat(array $Presentation): void
        {
            if (!isset($Presentation['PRESENTATION'])) {
                if (empty($Presentation)) {
                    return;
                }
                throw new \Exception("Non-empty presentation format is missing 'PRESENTATION' key.");
            }

            //The presentations are not loaded by the stubs. Use the well-known presentations instead
            $known = PresentationPool::presentationExists($Presentation['PRESENTATION']);
            foreach (get_defined_constants(true)['user'] as $name => $value) {
                if ((strpos($name, 'VARIABLE_PRESENTATION_') === 0) && ($value === $Presentation['PRESENTATION'])) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                throw new \Exception(sprintf("Presentation format contains invalid 'PRESENTATION' ID: %s", $Presentation['PRESENTATION']));
            }
        }
    }

    class ScriptManager
    {
        private static $scripts = [];
        private static $content = [];

        public static function createScript(int $ScriptID, int $ScriptType): void
        {
            self::$scripts[$ScriptID] = [
                'ScriptID'       => $ScriptID,
                'ScriptType'     => $ScriptType,
                'ScriptFile'     => $ScriptID . '.ips.php',
                'ScriptExecuted' => 0,
                'ScriptIsBroken' => false,
                'ScriptCRC'      => '',
                'ScriptSize'     => 0,
                'ScriptUpdated'  => 0
            ];

            self::$content[$ScriptID] = '<?php ' . PHP_EOL . PHP_EOL . '//Start writing your scripts between the brackets' . PHP_EOL . PHP_EOL . '?>';
        }

        public static function deleteScript(int $ScriptID, bool $DeleteFile): void
        {
            self::checkScript($ScriptID);
            unset(self::$scripts[$ScriptID]);
            unset(self::$content[$ScriptID]);
        }

        public static function checkScriptTypeRange(int $ScriptType): void
        {
            if (($ScriptType < 0) || ($ScriptType > 2)) {
                throw new \Exception('ScriptType must be between 0 and 2');
            }
        }

        public static function setScriptFile(int $ScriptID, string $FilePath): void
        {
            self::checkScript($ScriptID);

            self::$scripts[$ScriptID]['ScriptFile'] = $FilePath;
        }

        public static function setScriptContent(int $ScriptID, string $Content): void
        {
            self::checkScript($ScriptID);

            self::$content[$ScriptID] = $Content;
            self::$scripts[$ScriptID]['ScriptUpdated'] = TimeManager::getTime();
            self::$scripts[$ScriptID]['ScriptSize'] = strlen($Content);
            self::$scripts[$ScriptID]['ScriptCRC'] = strtoupper(hash('crc32b', $Content));
        }

        public static function scriptExists(int $ScriptID): bool
        {
            return isset(self::$scripts[$ScriptID]);
        }

        public static function checkScript(int $ScriptID): void
        {
            if (!self::scriptExists($ScriptID)) {
                throw new \Exception(sprintf('Script #%d does not exist', $ScriptID));
            }
        }

        public static function getScript(int $ScriptID): array
        {
            self::checkScript($ScriptID);

            return self::$scripts[$ScriptID];
        }

        public static function getScriptList(): array
        {
            return array_keys(self::$scripts);
        }

        public static function getScriptFile(int $ScriptID): string
        {
            self::checkScript($ScriptID);

            return self::$scripts[$ScriptID]['ScriptFile'];
        }

        public static function getScriptContent(int $ScriptID): string
        {
            self::checkScript($ScriptID);

            return self::$content[$ScriptID];
        }

        public static function reset()
        {
            self::$scripts = [];
            self::$content = [];
        }
    }

    class ScriptEngine
    {
        private static $semaphores = [];

        public static function reset()
        {
            self::$semaphores = [];
        }

        public static function runScript(int $ScriptID): void
        {
            self::runScriptEx($ScriptID, []);
        }

        public static function runScriptEx(int $ScriptID, array $Parameters): void
        {
            self::runScriptWaitEx($ScriptID, $Parameters);
        }

        public static function runScriptWait(int $ScriptID): string
        {
            return self::runScriptWaitEx($ScriptID, []);
        }

        public static function runScriptWaitEx(int $ScriptID, array $Parameters): string
        {
            return self::runScriptTextWaitEx(ScriptManager::getScriptContent($ScriptID), $Parameters);
        }

        public static function runScriptText(string $ScriptText): void
        {
            self::runScriptTextEx($ScriptText, []);
        }

        public static function runScriptTextEx(string $ScriptText, array $Parameters): void
        {
            self::runScriptTextWaitEx($ScriptText, $Parameters);
        }

        public static function runScriptTextWait(string $ScriptText): string
        {
            return self::runScriptTextWaitEx($ScriptText, []);
        }

        public static function runScriptTextWaitEx(string $ScriptText, array $Parameters): string
        {
            $ScriptText = str_replace('<?php', '', $ScriptText);
            $ScriptText = str_replace('<?', '', $ScriptText);
            $ScriptText = str_replace('?>', '', $ScriptText);
            $ScriptText = '$_IPS = ' . var_export($Parameters, true) . ';' . PHP_EOL . $ScriptText;
            ob_start();
            eval($ScriptText);
            $out = ob_get_contents();
            ob_end_clean();
            return $out;
        }

        public static function semaphoreEnter(string $Name, int $Milliseconds): bool
        {
            if (in_array($Name, self::$semaphores)) {
                return false;
            } else {
                self::$semaphores[] = $Name;
                return true;
            }
        }

        public static function semaphoreLeave(string $Name): bool
        {
            $key = array_search($Name, self::$semaphores);
            if ($key !== false) {
                unset(self::$semaphores[$key]);
            }
            return true;
        }

        public static function scriptThreadExists(int $ThreadID): bool
        {
            throw new \Exception('Not implemented');
        }

        public static function getScriptThread(int $ThreadID): array
        {
            throw new \Exception('Not implemented');
        }

        public static function getScriptThreadList(): array
        {
            throw new \Exception('Not implemented');
        }
    }

    class DataServer
    {
        public static function functionExists(string $FunctionName): bool
        {
            throw new \Exception('Not implemented');
        }

        public static function getFunction(string $FunctionName): array
        {
            throw new \Exception('Not implemented');
        }

        public static function getFunctionList(int $InstanceID): array
        {
            throw new \Exception('Not implemented');
        }

        public static function getFunctionListByModuleID(string $ModuleID): array
        {
            throw new \Exception('Not implemented');
        }

        public static function getFunctions(array $Parameter): array
        {
            throw new \Exception('Not implemented');
        }

        public static function getFunctionsMap(array $Parameter): array
        {
            throw new \Exception('Not implemented');
        }
    }

    class EventManager
    {
        private static $events = [];

        public static function reset()
        {
            self::$events = [];
        }
    }

    class MediaManager
    {
        private static $medias = [];
        private static $content = [];

        public static function createMedia(int $MediaID, int $MediaType): void
        {
            self::$medias[$MediaID] = [
                'MediaID'          => $MediaID,
                'MediaType'        => $MediaType,
                'MediaIsAvailable' => false,
                'MediaFile'        => '',
                'MediaCRC'         => '',
                'MediaIsCached'    => false,
                'MediaSize'        => 0,
                'MediaUpdated'     => 0
            ];
        }

        public static function deleteMedia(int $MediaID, bool $DeleteFile): void
        {
            self::checkMedia($MediaID);
            unset(self::$medias[$MediaID]);
            unset(self::$content[$MediaID]);
        }

        public static function mediaExists(int $MediaID): bool
        {
            return isset(self::$medias[$MediaID]);
        }

        public static function getMedia(int $MediaID): array
        {
            self::checkMedia($MediaID);

            return self::$medias[$MediaID];
        }

        public static function getMediaList(): array
        {
            return array_keys(self::$medias);
        }

        public static function getMediaListByType(int $MediaType): array
        {
            $result = [];
            foreach (self::$medias as $media) {
                if ($media['MediaType'] == $MediaType) {
                    $result[] = $media['MediaID'];
                }
            }

            return $result;
        }

        public static function getMediaIDByFile(string $FilePath): int|false
        {
            foreach (self::$medias as $media) {
                if ($media['MediaFile'] == $FilePath) {
                    return $media['MediaID'];
                }
            }

            trigger_error(sprintf('File %s could not be found', $FilePath), E_USER_WARNING);
            return false;
        }

        //The content is kept in memory instead of a file. Like the kernel, it is passed as Base64
        public static function getMediaContent(int $MediaID): string
        {
            self::checkMedia($MediaID);

            if (!isset(self::$content[$MediaID])) {
                throw new \Exception(sprintf('File #%s does not exist', self::$medias[$MediaID]['MediaFile']));
            }

            return self::$content[$MediaID];
        }

        public static function setMediaContent(int $MediaID, string $Content): void
        {
            self::checkMedia($MediaID);

            $data = base64_decode($Content);
            self::$content[$MediaID] = $Content;
            self::$medias[$MediaID]['MediaIsAvailable'] = true;
            self::$medias[$MediaID]['MediaSize'] = strlen($data);
            self::$medias[$MediaID]['MediaCRC'] = strtoupper(hash('crc32b', $data));
            self::$medias[$MediaID]['MediaUpdated'] = TimeManager::getTime();
        }

        public static function setMediaFile(int $MediaID, string $FilePath, bool $FileMustExists): void
        {
            self::checkMedia($MediaID);

            if ($FileMustExists && !isset(self::$content[$MediaID]) && !file_exists($FilePath)) {
                throw new \Exception(sprintf('File #%s does not exist', $FilePath));
            }

            self::$medias[$MediaID]['MediaFile'] = $FilePath;
        }

        public static function setMediaCached(int $MediaID, bool $Cached): void
        {
            self::checkMedia($MediaID);

            self::$medias[$MediaID]['MediaIsCached'] = $Cached;
        }

        public static function checkMediaTypeRange(int $MediaType): void
        {
            if (($MediaType < 0) || ($MediaType > 5)) {
                throw new \Exception('MediaType must be between 0 and 5');
            }
        }

        public static function checkMedia(int $MediaID): void
        {
            if (!self::mediaExists($MediaID)) {
                throw new \Exception(sprintf('Media #%d does not exist', $MediaID));
            }
        }

        public static function reset()
        {
            self::$medias = [];
            self::$content = [];
        }
    }

    class LinkManager
    {
        private static $links = [];

        public static function createLink(int $LinkID)
        {
            self::$links[$LinkID] = [
                'LinkID'   => $LinkID,
                'TargetID' => 0
            ];
        }

        public static function deleteLink(int $LinkID)
        {
            self::checkLink($LinkID);
            unset(self::$links[$LinkID]);
        }

        public static function getLink(int $LinkID)
        {
            self::checkLink($LinkID);
            return self::$links[$LinkID];
        }

        public static function getLinkIdByName(string $LinkName, int $ParentID)
        {
            throw new \Exception('Getting link by name is not implemented yet');
        }

        public static function getLinkList()
        {
            return array_keys(self::$links);
        }

        public static function linkExists(int $LinkID)
        {
            return isset(self::$links[$LinkID]);
        }

        public static function setLinkTargetID(int $LinkID, int $TargetID)
        {
            self::checkLink($LinkID);

            if ($TargetID > 0) {
                if (!ObjectManager::objectExists($TargetID)) {
                    throw new \Exception(sprintf('Object #%d does not exist', $TargetID));
                }
            }

            if (self::linkExists($TargetID)) {
                throw new \Exception('TargetID must not be another Link');
            }
            self::$links[$LinkID]['TargetID'] = $TargetID;
        }

        public static function reset()
        {
            self::$links = [];
        }

        private static function checkLink(int $LinkID)
        {
            if (!self::linkExists($LinkID)) {
                throw new \Exception(sprintf('Link #%d does not exist', $LinkID));
            }
        }
    }

    class ProfileManager
    {
        private static $profiles = [];

        public static function createVariableProfile(string $ProfileName, int $ProfileType): void
        {
            if (($ProfileType < 0) || ($ProfileType > 3)) {
                throw new \Exception('ProfileType must be between 0 and 3');
            }

            if ($ProfileName == '') {
                throw new \Exception('Profilename cannot be empty');
            }

            if (strpos($ProfileName, ' ') !== false) {
                throw new \Exception('Profilename cannot have spaces');
            }

            if (!preg_match('/^[a-zA-Z0-9_\-.]+$/', $ProfileName)) {
                throw new \Exception('Profilename cannot have special chars');
            }

            if (self::variableProfileExists($ProfileName)) {
                throw new \Exception(sprintf('Profile with name #%s already exists', $ProfileName));
            }

            self::$profiles[$ProfileName] = [
                'ProfileName'  => $ProfileName,
                'ProfileType'  => $ProfileType,
                'Icon'         => '',
                'Prefix'       => '',
                'Suffix'       => '',
                'MaxValue'     => 0,
                'MinValue'     => 0,
                'Digits'       => 0,
                'StepSize'     => 0,
                'IsReadOnly'   => false,
                'Associations' => []
            ];
        }

        public static function deleteVariableProfile(string $ProfileName): void
        {
            self::checkWriteVariableProfile($ProfileName);
            unset(self::$profiles[$ProfileName]);
        }

        public static function setVariableProfileText(string $ProfileName, string $Prefix, string $Suffix): void
        {
            self::checkWriteVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['Prefix'] = $Prefix;
            self::$profiles[$ProfileName]['Suffix'] = $Suffix;
        }

        public static function setVariableProfileValues(string $ProfileName, float $MinValue, float $MaxValue, float $StepSize): void
        {
            self::checkWriteVariableProfile($ProfileName);

            if (self::$profiles[$ProfileName]['ProfileType'] == VARIABLETYPE_STRING) {
                throw new \Exception('Cannot modify values for string profiles');
            }

            if ($MinValue > $MaxValue) {
                throw new \Exception('Min value cannot be bigger than max value');
            }

            if ($StepSize < 0) {
                throw new \Exception('Step size cannot be nagative');
            }

            self::$profiles[$ProfileName]['MinValue'] = $MinValue;
            self::$profiles[$ProfileName]['MaxValue'] = $MaxValue;
            self::$profiles[$ProfileName]['StepSize'] = $StepSize;
        }

        public static function setVariableProfileDigits(string $ProfileName, int $Digits): void
        {
            self::checkWriteVariableProfile($ProfileName);

            if (self::$profiles[$ProfileName]['ProfileType'] == VARIABLETYPE_STRING) {
                throw new \Exception('Cannot modify digits for string profiles');
            }

            if (($Digits < 0) || ($Digits > 16)) {
                throw new \Exception('Digits must be between 0 and 16');
            }

            self::$profiles[$ProfileName]['Digits'] = $Digits;
        }

        public static function setVariableProfileIcon(string $ProfileName, string $Icon): void
        {
            self::checkWriteVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['Icon'] = $Icon;
        }

        public static function setVariableProfileAssociation(string $ProfileName, $AssociationValue, string $AssociationName, string $AssociationIcon, int $AssociationColor)
        {
            self::checkWriteVariableProfile($ProfileName);
            if (($AssociationName == '') && ($AssociationIcon == '')) {
                if (self::$profiles[$ProfileName]['ProfileType'] == VARIABLETYPE_BOOLEAN) {
                    throw new \Exception('Cannot delete association for boolean profiles');
                }

                unset($keyFound);
                foreach (self::$profiles[$ProfileName]['Associations'] as $key => $association) {
                    if ($association['Value'] == $AssociationValue) {
                        $keyFound = $key;
                        break;
                    }
                }
                if (isset($keyFound)) {
                    unset(self::$profiles[$ProfileName]['Associations'][$keyFound]);
                    if (self::$profiles[$ProfileName]['ProfileType'] != VARIABLETYPE_STRING) {
                        usort(self::$profiles[$ProfileName]['Associations'], function ($a, $b)
                        {
                            return $a['Value'] <=> $b['Value'];
                        });
                    }
                } else {
                    throw new \Exception(sprintf('Cannot find association for deletion with value %s', $AssociationValue));
                }
                return;
            }

            foreach (self::$profiles[$ProfileName]['Associations'] as &$association) {
                if ($association['Value'] == $AssociationValue) {
                    $association['Name'] = $AssociationName;
                    $association['Icon'] = $AssociationIcon;
                    $association['Color'] = $AssociationColor;
                    return;
                }
            }

            self::$profiles[$ProfileName]['Associations'][] = [
                'Value' => $AssociationValue,
                'Name'  => $AssociationName,
                'Icon'  => $AssociationIcon,
                'Color' => $AssociationColor
            ];

            if (self::$profiles[$ProfileName]['ProfileType'] != VARIABLETYPE_STRING) {
                usort(self::$profiles[$ProfileName]['Associations'], function ($a, $b)
                {
                    return $a['Value'] <=> $b['Value'];
                });
            }
        }

        public static function variableProfileExists(string $ProfileName): bool
        {
            return isset(self::$profiles[$ProfileName]);
        }

        public static function checkVariableProfile(string $ProfileName): void
        {
            if (!self::variableProfileExists($ProfileName)) {
                throw new \Exception(sprintf('Profile with name #%s does not exist', $ProfileName));
            }
        }

        public static function checkWriteVariableProfile(string $ProfileName): void
        {
            self::checkVariableProfile($ProfileName);

            if (self::$profiles[$ProfileName]['IsReadOnly'] ?? false) {
                throw new \Exception(sprintf('Profile #%s has been marked as read only', $ProfileName));
            }
        }

        public static function getVariableProfile(string $ProfileName): array
        {
            self::checkVariableProfile($ProfileName);

            return self::$profiles[$ProfileName];
        }

        public static function getVariableProfileList(): array
        {
            return array_keys(self::$profiles);
        }

        public static function getVariableProfileListByType(int $ProfileType): array
        {
            $result = [];
            foreach (self::$profiles as $profile) {
                if ($profile['ProfileType'] == $ProfileType) {
                    $result[] = $profile['ProfileName'];
                }
            }

            return $result;
        }

        public static function reset()
        {
            self::$profiles = [
                '~Raining' => [
                    'ProfileName'  => '~Raining',
                    'ProfileType'  => 0,
                    'Icon'         => 'Rainfall',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Kein Regen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Regen',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Temperature' => [
                    'ProfileName'  => '~Temperature',
                    'ProfileType'  => 2,
                    'Icon'         => 'Temperature',
                    'Prefix'       => '',
                    'Suffix'       => ' °C',
                    'MinValue'     => -30.0,
                    'MaxValue'     => 70.0,
                    'StepSize'     => 5.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Temperature.Fahrenheit' => [
                    'ProfileName'  => '~Temperature.Fahrenheit',
                    'ProfileType'  => 2,
                    'Icon'         => 'Temperature',
                    'Prefix'       => '',
                    'Suffix'       => ' °F',
                    'MinValue'     => -22.0,
                    'MaxValue'     => 158.0,
                    'StepSize'     => 5.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~UVIndex' => [
                    'ProfileName'  => '~UVIndex',
                    'ProfileType'  => 1,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 22.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Power' => [
                    'ProfileName'  => '~Power',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' kW',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Illumination' => [
                    'ProfileName'  => '~Illumination',
                    'ProfileType'  => 1,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => ' lx',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 120000.0,
                    'StepSize'     => 20000.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Temperature.Room' => [
                    'ProfileName'  => '~Temperature.Room',
                    'ProfileType'  => 2,
                    'Icon'         => 'Temperature',
                    'Prefix'       => '',
                    'Suffix'       => ' °C',
                    'MinValue'     => 15.0,
                    'MaxValue'     => 25.0,
                    'StepSize'     => 0.5,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Temperature.Difference' => [
                    'ProfileName'  => '~Temperature.Difference',
                    'ProfileType'  => 2,
                    'Icon'         => 'Temperature',
                    'Prefix'       => '',
                    'Suffix'       => ' K',
                    'MinValue'     => -30.0,
                    'MaxValue'     => 30.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~WindDirection' => [
                    'ProfileName'  => '~WindDirection',
                    'ProfileType'  => 1,
                    'Icon'         => 'WindDirection',
                    'Prefix'       => '',
                    'Suffix'       => '°',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 360.0,
                    'StepSize'     => 30.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~AirPressure.F' => [
                    'ProfileName'  => '~AirPressure.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' hPa',
                    'MinValue'     => 850.0,
                    'MaxValue'     => 1100.0,
                    'StepSize'     => 50.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterPosition.100' => [
                    'ProfileName'  => '~ShutterPosition.100',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        1 => [
                            'Value' => 25,
                            'Name'  => '25 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        2 => [
                            'Value' => 50,
                            'Name'  => '50 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        3 => [
                            'Value' => 75,
                            'Name'  => '75 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        4 => [
                            'Value' => 100,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Intensity.65535' => [
                    'ProfileName'  => '~Intensity.65535',
                    'ProfileType'  => 1,
                    'Icon'         => 'Intensity',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 65535.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Humidity' => [
                    'ProfileName'  => '~Humidity',
                    'ProfileType'  => 1,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 10.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Water' => [
                    'ProfileName'  => '~Water',
                    'ProfileType'  => 2,
                    'Icon'         => 'Drops',
                    'Prefix'       => '',
                    'Suffix'       => ' Liter',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Presence' => [
                    'ProfileName'  => '~Presence',
                    'ProfileType'  => 0,
                    'Icon'         => 'Motion',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Abwesend',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Anwesend',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~AirPressure' => [
                    'ProfileName'  => '~AirPressure',
                    'ProfileType'  => 1,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' hPa',
                    'MinValue'     => 850.0,
                    'MaxValue'     => 1100.0,
                    'StepSize'     => 50.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Humidity.F' => [
                    'ProfileName'  => '~Humidity.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 10.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Illumination.F' => [
                    'ProfileName'  => '~Illumination.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => ' lx',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 120000.0,
                    'StepSize'     => 20000.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Motion.Reversed' => [
                    'ProfileName'  => '~Motion.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Motion',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Bewegung erkannt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Keine Bewegung',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Door' => [
                    'ProfileName'  => '~Door',
                    'ProfileType'  => 0,
                    'Icon'         => 'Door',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Liquid.pH' => [
                    'ProfileName'  => '~Liquid.pH',
                    'ProfileType'  => 1,
                    'Icon'         => 'ErlenmeyerFlask',
                    'Prefix'       => '',
                    'Suffix'       => ' pH',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 14.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Switch' => [
                    'ProfileName'  => '~Switch',
                    'ProfileType'  => 0,
                    'Icon'         => 'Power',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Aus',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'An',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Liquid.pH.F' => [
                    'ProfileName'  => '~Liquid.pH.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'ErlenmeyerFlask',
                    'Prefix'       => '',
                    'Suffix'       => ' pH',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 14.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Volt' => [
                    'ProfileName'  => '~Volt',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' V',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Rainfall' => [
                    'ProfileName'  => '~Rainfall',
                    'ProfileType'  => 2,
                    'Icon'         => 'Rainfall',
                    'Prefix'       => '',
                    'Suffix'       => ' mm',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Lock.Reversed' => [
                    'ProfileName'  => '~Lock.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Lock',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Gesperrt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Entriegelt',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Occurrence.CO2' => [
                    'ProfileName'  => '~Occurrence.CO2',
                    'ProfileType'  => 1,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' ppm',
                    'MinValue'     => 300.0,
                    'MaxValue'     => 2200.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Intensity.1' => [
                    'ProfileName'  => '~Intensity.1',
                    'ProfileType'  => 2,
                    'Icon'         => 'Intensity',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.05,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Lamella' => [
                    'ProfileName'  => '~Lamella',
                    'ProfileType'  => 1,
                    'Icon'         => 'TurnRight',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Intensity.100' => [
                    'ProfileName'  => '~Intensity.100',
                    'ProfileType'  => 1,
                    'Icon'         => 'Intensity',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Intensity.255' => [
                    'ProfileName'  => '~Intensity.255',
                    'ProfileType'  => 1,
                    'Icon'         => 'Intensity',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 255.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Millivolt' => [
                    'ProfileName'  => '~Millivolt',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' mV',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterAssociation' => [
                    'ProfileName'  => '~ShutterAssociation',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        1 => [
                            'Value' => 25,
                            'Name'  => '25 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        2 => [
                            'Value' => 50,
                            'Name'  => '50 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        3 => [
                            'Value' => 75,
                            'Name'  => '75 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        4 => [
                            'Value' => 99,
                            'Name'  => '99 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        5 => [
                            'Value' => 100,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Intensity.32767' => [
                    'ProfileName'  => '~Intensity.32767',
                    'ProfileType'  => 1,
                    'Icon'         => 'Intensity',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 32767.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Lamella.Reversed' => [
                    'ProfileName'  => '~Lamella.Reversed',
                    'ProfileType'  => 1,
                    'Icon'         => 'TurnRight',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Valve.F' => [
                    'ProfileName'  => '~Valve.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 10.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Alert' => [
                    'ProfileName'  => '~Alert',
                    'ProfileType'  => 0,
                    'Icon'         => 'Warning',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'OK',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Alarm',
                            'Icon'  => '',
                            'Color' => 16711680,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Alert.Reversed' => [
                    'ProfileName'  => '~Alert.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Warning',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Alarm',
                            'Icon'  => '',
                            'Color' => 16711680,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'OK',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Window' => [
                    'ProfileName'  => '~Window',
                    'ProfileType'  => 0,
                    'Icon'         => 'Window',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterMove' => [
                    'ProfileName'  => '~ShutterMove',
                    'ProfileType'  => 0,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Schließen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Öffnen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Window.Reversed' => [
                    'ProfileName'  => '~Window.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Window',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Door.Reversed' => [
                    'ProfileName'  => '~Door.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Door',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Lock' => [
                    'ProfileName'  => '~Lock',
                    'ProfileType'  => 0,
                    'Icon'         => 'Lock',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Entriegelt',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Gesperrt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Battery.Reversed' => [
                    'ProfileName'  => '~Battery.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Battery',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Batterie schwach',
                            'Icon'  => '',
                            'Color' => 16711680,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'OK',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Milliampere' => [
                    'ProfileName'  => '~Milliampere',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' mA',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Progress' => [
                    'ProfileName'  => '~Progress',
                    'ProfileType'  => 2,
                    'Icon'         => 'Clock',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 0.1,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Battery' => [
                    'ProfileName'  => '~Battery',
                    'ProfileType'  => 0,
                    'Icon'         => 'Battery',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'OK',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Batterie schwach',
                            'Icon'  => '',
                            'Color' => 16711680,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterPosition.255' => [
                    'ProfileName'  => '~ShutterPosition.255',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 255.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        1 => [
                            'Value' => 64,
                            'Name'  => '25 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        2 => [
                            'Value' => 128,
                            'Name'  => '50 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        3 => [
                            'Value' => 191,
                            'Name'  => '75 %%',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        4 => [
                            'Value' => 255,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~PlaybackPreviousNextNoStop' => [
                    'ProfileName'  => '~PlaybackPreviousNextNoStop',
                    'ProfileType'  => 1,
                    'Icon'         => 'Remote',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 4.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Zurück',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 2,
                            'Name'  => 'Play',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 3,
                            'Name'  => 'Pause',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        3 => [
                            'Value' => 4,
                            'Name'  => 'Weiter',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Mode.HM' => [
                    'ProfileName'  => '~Mode.HM',
                    'ProfileType'  => 1,
                    'Icon'         => 'ArrowRight',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Automatisch',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 1,
                            'Name'  => 'Manuell',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Battery.100' => [
                    'ProfileName'  => '~Battery.100',
                    'ProfileType'  => 1,
                    'Icon'         => 'Battery',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 10.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Presence.Reversed' => [
                    'ProfileName'  => '~Presence.Reversed',
                    'ProfileType'  => 0,
                    'Icon'         => 'Motion',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Anwesend',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Abwesend',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Motion' => [
                    'ProfileName'  => '~Motion',
                    'ProfileType'  => 0,
                    'Icon'         => 'Motion',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Keine Bewegung',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Bewegung erkannt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~WindSpeed.kmh' => [
                    'ProfileName'  => '~WindSpeed.kmh',
                    'ProfileType'  => 2,
                    'Icon'         => 'WindSpeed',
                    'Prefix'       => '',
                    'Suffix'       => ' km/h',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 200.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~WindSpeed.ms' => [
                    'ProfileName'  => '~WindSpeed.ms',
                    'ProfileType'  => 2,
                    'Icon'         => 'WindSpeed',
                    'Prefix'       => '',
                    'Suffix'       => ' m/s',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 60.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~WindDirection.F' => [
                    'ProfileName'  => '~WindDirection.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'WindDirection',
                    'Prefix'       => '',
                    'Suffix'       => '°',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 360.0,
                    'StepSize'     => 30.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~SunAltitude.F' => [
                    'ProfileName'  => '~SunAltitude.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => '°',
                    'MinValue'     => -180.0,
                    'MaxValue'     => 180.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~SunAzimuth.F' => [
                    'ProfileName'  => '~SunAzimuth.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => '°',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 360.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Acceleration.F' => [
                    'ProfileName'  => '~Acceleration.F',
                    'ProfileType'  => 2,
                    'Icon'         => 'Cross',
                    'Prefix'       => '',
                    'Suffix'       => ' g',
                    'MinValue'     => 10.0,
                    'MaxValue'     => 10.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~RGB' => [
                    'ProfileName'  => '~RGB',
                    'ProfileType'  => 3,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Duration' => [
                    'ProfileName'  => '~Duration',
                    'ProfileType'  => 1,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Duration.Long' => [
                    'ProfileName'  => '~Duration.Long',
                    'ProfileType'  => 1,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~WindDirection.Text' => [
                    'ProfileName'  => '~WindDirection.Text',
                    'ProfileType'  => 2,
                    'Icon'         => 'WindDirection',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 337.5,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'N',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 22.5,
                            'Name'  => 'NNO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 45,
                            'Name'  => 'NO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        3 => [
                            'Value' => 67.5,
                            'Name'  => 'ONO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        4 => [
                            'Value' => 90,
                            'Name'  => 'O',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        5 => [
                            'Value' => 112.5,
                            'Name'  => 'OSO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        6 => [
                            'Value' => 135,
                            'Name'  => 'SO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        7 => [
                            'Value' => 157.5,
                            'Name'  => 'SSO',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        8 => [
                            'Value' => 180,
                            'Name'  => 'S',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        9 => [
                            'Value' => 202.5,
                            'Name'  => 'SSW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        10 => [
                            'Value' => 225,
                            'Name'  => 'SW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        11 => [
                            'Value' => 247.5,
                            'Name'  => 'WSW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        12 => [
                            'Value' => 270,
                            'Name'  => 'W',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        13 => [
                            'Value' => 292.5,
                            'Name'  => 'WNW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        14 => [
                            'Value' => 315,
                            'Name'  => 'NW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        15 => [
                            'Value' => 337.5,
                            'Name'  => 'NNW',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Valve' => [
                    'ProfileName'  => '~Valve',
                    'ProfileType'  => 1,
                    'Icon'         => 'Gauge',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 10.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Shutter' => [
                    'ProfileName'  => '~Shutter',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Shutter.Reversed' => [
                    'ProfileName'  => '~Shutter.Reversed',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterMoveStop' => [
                    'ProfileName'  => '~ShutterMoveStop',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 4.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Öffnen',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        1 => [
                            'Value' => 2,
                            'Name'  => 'Stop',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 4,
                            'Name'  => 'Schließen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Ampere' => [
                    'ProfileName'  => '~Ampere',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' A',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~ShutterMoveStep' => [
                    'ProfileName'  => '~ShutterMoveStep',
                    'ProfileType'  => 1,
                    'Icon'         => 'Shutter',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 4.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Öffnen',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                        1 => [
                            'Value' => 1,
                            'Name'  => 'Schritt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        2 => [
                            'Value' => 2,
                            'Name'  => 'Stop',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        3 => [
                            'Value' => 3,
                            'Name'  => 'Schritt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        4 => [
                            'Value' => 4,
                            'Name'  => 'Schließen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Volt.230' => [
                    'ProfileName'  => '~Volt.230',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' V',
                    'MinValue'     => 207.0,
                    'MaxValue'     => 253.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Ampere.16' => [
                    'ProfileName'  => '~Ampere.16',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' A',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 16.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Watt' => [
                    'ProfileName'  => '~Watt',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' W',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Watt.3680' => [
                    'ProfileName'  => '~Watt.3680',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' W',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 3680.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Watt.14490' => [
                    'ProfileName'  => '~Watt.14490',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' W',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 14490.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Hertz' => [
                    'ProfileName'  => '~Hertz',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' Hz',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Hertz.50' => [
                    'ProfileName'  => '~Hertz.50',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' Hz',
                    'MinValue'     => 45.0,
                    'MaxValue'     => 55.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Electricity' => [
                    'ProfileName'  => '~Electricity',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' kWh',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Electricity.Wh' => [
                    'ProfileName'  => '~Electricity.Wh',
                    'ProfileType'  => 2,
                    'Icon'         => 'Electricity',
                    'Prefix'       => '',
                    'Suffix'       => ' Wh',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Water.m3' => [
                    'ProfileName'  => '~Water.m3',
                    'ProfileType'  => 2,
                    'Icon'         => 'Drops',
                    'Prefix'       => '',
                    'Suffix'       => ' m³',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 3,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~MailMessages' => [
                    'ProfileName'  => '~MailMessages',
                    'ProfileType'  => 1,
                    'Icon'         => 'Mail',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => -1.0,
                    'MaxValue'     => 2147483647.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => -1,
                            'Name'  => 'Unbekannt',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 0,
                            'Name'  => 'Kein(e)',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 1,
                            'Name'  => '%d',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        3 => [
                            'Value' => 2147483647,
                            'Name'  => '*',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Gas' => [
                    'ProfileName'  => '~Gas',
                    'ProfileType'  => 2,
                    'Icon'         => 'Flame',
                    'Prefix'       => '',
                    'Suffix'       => ' m³',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Repeat' => [
                    'ProfileName'  => '~Repeat',
                    'ProfileType'  => 1,
                    'Icon'         => 'Repeat',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 2.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Aus',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 1,
                            'Name'  => 'Kontext',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 2,
                            'Name'  => 'Lied',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Flow' => [
                    'ProfileName'  => '~Flow',
                    'ProfileType'  => 2,
                    'Icon'         => 'Distance',
                    'Prefix'       => '',
                    'Suffix'       => ' m³/h',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Euro' => [
                    'ProfileName'  => '~Euro',
                    'ProfileType'  => 2,
                    'Icon'         => 'Euro',
                    'Prefix'       => '',
                    'Suffix'       => ' €',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Dollar' => [
                    'ProfileName'  => '~Dollar',
                    'ProfileType'  => 2,
                    'Icon'         => 'Dollar',
                    'Prefix'       => '',
                    'Suffix'       => ' $',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~UnixTimestamp' => [
                    'ProfileName'  => '~UnixTimestamp',
                    'ProfileType'  => 1,
                    'Icon'         => 'Clock',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~UnixTimestampDate' => [
                    'ProfileName'  => '~UnixTimestampDate',
                    'ProfileType'  => 1,
                    'Icon'         => 'Calendar',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~UnixTimestampTime' => [
                    'ProfileName'  => '~UnixTimestampTime',
                    'ProfileType'  => 1,
                    'Icon'         => 'Clock',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~TextBox' => [
                    'ProfileName'  => '~TextBox',
                    'ProfileType'  => 3,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~HTMLBox' => [
                    'ProfileName'  => '~HTMLBox',
                    'ProfileType'  => 3,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~HexColor' => [
                    'ProfileName'  => '~HexColor',
                    'ProfileType'  => 1,
                    'Icon'         => 'Paintbrush',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~TWColor' => [
                    'ProfileName'  => '~TWColor',
                    'ProfileType'  => 1,
                    'Icon'         => 'Paintbrush',
                    'Prefix'       => '',
                    'Suffix'       => ' K',
                    'MinValue'     => 1000.0,
                    'MaxValue'     => 12000.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Playback' => [
                    'ProfileName'  => '~Playback',
                    'ProfileType'  => 1,
                    'Icon'         => 'Remote',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 1.0,
                    'MaxValue'     => 3.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 1,
                            'Name'  => 'Stop',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 2,
                            'Name'  => 'Play',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 3,
                            'Name'  => 'Pause',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~PlaybackPreviousNext' => [
                    'ProfileName'  => '~PlaybackPreviousNext',
                    'ProfileType'  => 1,
                    'Icon'         => 'Remote',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 4.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Zurück',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 1,
                            'Name'  => 'Stop',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        2 => [
                            'Value' => 2,
                            'Name'  => 'Play',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        3 => [
                            'Value' => 3,
                            'Name'  => 'Pause',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        4 => [
                            'Value' => 4,
                            'Name'  => 'Weiter',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~PlaybackNoStop' => [
                    'ProfileName'  => '~PlaybackNoStop',
                    'ProfileType'  => 1,
                    'Icon'         => 'Remote',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 2.0,
                    'MaxValue'     => 3.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 2,
                            'Name'  => 'Play',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 3,
                            'Name'  => 'Pause',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Artist' => [
                    'ProfileName'  => '~Artist',
                    'ProfileType'  => 3,
                    'Icon'         => 'People',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Song' => [
                    'ProfileName'  => '~Song',
                    'ProfileType'  => 3,
                    'Icon'         => 'Melody',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Playlist' => [
                    'ProfileName'  => '~Playlist',
                    'ProfileType'  => 3,
                    'Icon'         => 'Database',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Volume' => [
                    'ProfileName'  => '~Volume',
                    'ProfileType'  => 1,
                    'Icon'         => 'Speaker',
                    'Prefix'       => '',
                    'Suffix'       => ' %',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 100.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Shuffle' => [
                    'ProfileName'  => '~Shuffle',
                    'ProfileType'  => 0,
                    'Icon'         => 'Shuffle',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Aus',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'An',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Mute' => [
                    'ProfileName'  => '~Mute',
                    'ProfileType'  => 0,
                    'Icon'         => 'Speaker',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Aus',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'An',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~IconNotifier' => [
                    'ProfileName'  => '~IconNotifier',
                    'ProfileType'  => 1,
                    'Icon'         => 'Alert',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 999.0,
                    'StepSize'     => 1.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Motion.HM' => [
                    'ProfileName'  => '~Motion.HM',
                    'ProfileType'  => 0,
                    'Icon'         => 'Motion',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 1.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => false,
                            'Name'  => 'Untätig',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => true,
                            'Name'  => 'Bewegung',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Electricity.HM' => [
                    'ProfileName'  => '~Electricity.HM',
                    'ProfileType'  => 2,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => ' Wh',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 2,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Brightness.HM' => [
                    'ProfileName'  => '~Brightness.HM',
                    'ProfileType'  => 1,
                    'Icon'         => 'Sun',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 255.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Temperature.HM' => [
                    'ProfileName'  => '~Temperature.HM',
                    'ProfileType'  => 2,
                    'Icon'         => 'Temperature',
                    'Prefix'       => '',
                    'Suffix'       => ' °C',
                    'MinValue'     => 6.0,
                    'MaxValue'     => 30.0,
                    'StepSize'     => 0.5,
                    'Digits'       => 1,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
                '~Window.HM' => [
                    'ProfileName'  => '~Window.HM',
                    'ProfileType'  => 1,
                    'Icon'         => 'Window',
                    'Prefix'       => '',
                    'Suffix'       => '',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 2.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                        0 => [
                            'Value' => 0,
                            'Name'  => 'Geschlossen',
                            'Icon'  => '',
                            'Color' => -1,
                        ],
                        1 => [
                            'Value' => 1,
                            'Name'  => 'Gekippt',
                            'Icon'  => '',
                            'Color' => 255,
                        ],
                        2 => [
                            'Value' => 2,
                            'Name'  => 'Geöffnet',
                            'Icon'  => '',
                            'Color' => 65280,
                        ],
                    ],
                    'IsReadOnly' => true,
                ],
                '~Milliampere.HM' => [
                    'ProfileName'  => '~Milliampere.HM',
                    'ProfileType'  => 2,
                    'Icon'         => '',
                    'Prefix'       => '',
                    'Suffix'       => ' mA',
                    'MinValue'     => 0.0,
                    'MaxValue'     => 0.0,
                    'StepSize'     => 0.0,
                    'Digits'       => 0,
                    'Associations' => [
                    ],
                    'IsReadOnly' => true,
                ],
            ];
        }
    }

    class TemplateManager
    {
        private static $templates = [];

        public static function createTemplate(string $PresentationID): string
        {

            $templateID = sprintf(
                '{%04X%04X-%04X-%04X-%04X-%04X%04X%04X}',
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(16384, 20479),
                mt_rand(32768, 49151),
                mt_rand(0, 65535),
                mt_rand(0, 65535),
                mt_rand(0, 65535)
            );

            self::$templates[$templateID] = [
                'TemplateID'         => $templateID,
                'PresentationID'     => $PresentationID,
                'DisplayName'        => '',
                'Values'             => [],
                'IsReadOnly'         => false
            ];

            return $templateID;
        }

        public static function deleteTemplate(string $TemplateID): void
        {
            self::checkTemplate($TemplateID);
            unset(self::$templates[$TemplateID]);
        }

        public static function createTemplateEx(array $Template, bool $IsReadOnly = true): void
        {
            $templateID = $Template['TemplateID'];
            self::$templates[$templateID] = $Template;
            self::$templates[$templateID]['IsReadOnly'] = $IsReadOnly;
        }

        public static function setTemplateName(string $TemplateID, string $TemplateName): void
        {
            self::checkTemplate($TemplateID);
            self::$templates[$TemplateID]['Name'] = $TemplateName;
        }

        public static function setTemplateValues(string $TemplateID, array $Values): void
        {
            self::checkTemplate($TemplateID);
            self::$templates[$TemplateID]['Values'] = $Values;
        }

        public static function templateExists(string $TemplateID): bool
        {
            return isset(self::$templates[$TemplateID]);
        }

        public static function getTemplate(string $TemplateID): array
        {
            self::checkTemplate($TemplateID);
            return self::$templates[$TemplateID];
        }

        public static function getTemplateList(): array
        {
            return array_keys(self::$templates);
        }

        public static function getTemplateListByPresentation(string $PresentationID): array
        {
            $result = [];
            foreach (self::$templates as $template) {
                if ($template['PresentationID'] == $PresentationID) {
                    $result[] = $template['TemplateID'];
                }
            }
            return $result;
        }

        public static function reset(): void
        {
            self::$templates = [];
        }

        private static function checkTemplate(string $TemplateID): void
        {
            if (!self::templateExists($TemplateID)) {
                throw new \Exception(sprintf('Template #%s does not exist', $TemplateID));
            }
        }
    }

    class DebugServer
    {
        private static $debug = [];
        private static $messages = [];

        public static function disableDebug(int $ID): void
        {
            self::$debug[$ID] = 0;
        }

        public static function enableDebug(int $ID, int $Duration): void
        {
            self::$debug[$ID] = time() + $Duration;
        }

        public static function sendDebug(int $SenderID, string $Message, string $Data, int $Format): void
        {
            self::$messages[$SenderID][] = [
                'Message' => $Message,
                'Data'    => $Data,
                'Format'  => $Format
            ];

            if (!isset(self::$debug[$SenderID])) {
                return;
            }

            if (time() > self::$debug[$SenderID]) {
                return;
            }

            if ($Format == 1 /* Binary */) {
                $Data = bin2hex($Data);
            }

            echo 'DEBUG: ' . $Message . ' | ' . $Data . PHP_EOL;
        }

        public static function getDebugMessages($SenderID): array
        {
            if (!isset(self::$messages[$SenderID])) {
                return [];
            }
            return self::$messages[$SenderID];
        }

        public static function reset()
        {
            self::$debug = [];
            self::$messages = [];
        }
    }

    class LogServer
    {
        private static $messages = [];

        public static function logMessage(string $Sender, string $Message, int $Type): void
        {
            self::$messages[$Sender][] = [
                'Message' => $Message,
                'Type'    => $Type
            ];
        }

        public static function getLogMessages(string $Sender): array
        {
            if (!isset(self::$messages[$Sender])) {
                return [];
            }
            return self::$messages[$Sender];
        }

        public static function reset()
        {
            self::$messages = [];
        }
    }

    class ActionPool
    {
        private static $actions = [];

        public static function loadActions(string $ActionPath): void
        {
            if (substr($ActionPath, -1) !== '/') {
                $ActionPath .= '/';
            }

            $handle = opendir($ActionPath);

            $file = readdir($handle);
            while ($file !== false) {
                if (is_file($ActionPath . $file) && (substr($file, -5) === '.json')) {
                    self::$actions[] = json_decode(file_get_contents($ActionPath . $file), true);
                }
                $file = readdir($handle);
            }

            closedir($handle);
        }

        public static function getActions(): string
        {
            return json_encode(self::$actions);
        }

        public static function getActionsByEnvironment(int $ID, string $Environment, bool $IncludeDefault): string
        {
            throw new \Exception('Not implemented');
        }

        public static function getActionForm(string $ActionID, array $Parameters): string
        {
            throw new \Exception('Not implemented');
        }

        public static function getActionReadableCode(string $ActionID, array $Parameters): string
        {
            throw new \Exception('Not implemented');
        }

        public static function runAction(string $ActionID, array $Parameters): void
        {
            self::runActionWait($ActionID, $Parameters);
        }

        public static function runActionWait(string $ActionID, array $Parameters): string
        {
            foreach (self::$actions as $action) {
                if ($action['id'] === $ActionID) {
                    $scriptText = $action['action'];
                    if (is_array($scriptText)) {
                        $scriptText = implode("\n", $scriptText);
                    }
                    // This will probably not work with included php files
                    return ScriptEngine::runScriptTextWaitEx($scriptText, $Parameters);
                }
            }

            throw new \Exception('Action does not exist');
        }

        public static function updateFormField(string $Name, string $Parameter, $Value, $ID, string $SessionID): void
        {
            throw new \Exception('Not implemented');
        }

        public static function reset(): void
        {
            self::$actions = [];
        }
    }

    class PresentationPool
    {
        private static $presentations = [];

        public static function getDefaultParameters(array $Variable, string $GUID)
        {
            throw new \Exception('Not implemented');
        }

        public static function checkPresentation(string $GUID)
        {
            if (!self::presentationExists($GUID)) {
                throw new \Exception(sprintf('presentation with GUID %s does not exist', $GUID));
            }
        }

        public static function getPresentations(): string
        {
            return json_encode(self::$presentations);
        }

        public static function getPresentation(string $GUID): array
        {
            self::checkPresentation($GUID);
            return self::$presentations[$GUID];
        }

        public static function getPresentationForm(string $GUID, int $VariableType, array $Parameter): string
        {
            throw new \Exception('Not implemented');
        }

        public static function presentationExists(string $GUID): bool
        {
            return isset(self::$presentations[$GUID]);
        }

        public static function reset(): void
        {
            self::$presentations = [];
        }

    }

    class Kernel
    {
        public static function reset()
        {
            ModuleLoader::reset();
            ObjectManager::reset();
            CategoryManager::reset();
            InstanceManager::reset();
            TimeManager::reset();
            VariableManager::reset();
            ScriptManager::reset();
            ScriptEngine::reset();
            EventManager::reset();
            MediaManager::reset();
            LinkManager::reset();
            ProfileManager::reset();
            DebugServer::reset();
            LogServer::reset();
            ActionPool::reset();
            PresentationPool::reset();
            TemplateManager::reset();
        }
    }
}
