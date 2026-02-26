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
                'Aliases'            => $module['aliases'],
                'ParentRequirements' => $module['parentRequirements'],
                'ChildRequirements'  => $module['childRequirements'],
                'Implemented'        => $module['implemented'],
                'LibraryID'          => $libraryID,
                'Prefix'             => $module['prefix'],
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
                $function = sprintf('function %s_%s(%s){return IPS\InstanceManager::getInstanceInterface($InstanceID)->%s(%s);}', $modulePrefix, $method->GetName(), implode(', ', $params), $method->GetName(), implode(', ', $fwdparams));
                if (!\function_exists($modulePrefix . '_' . $method->GetName())) {
                    eval($function);
                }
            }
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
            self::$objects[0]['ChildrendIDs'][] = $id;
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
            }

            //Readd ID to available pool
            self::$availableIDs[] = $ID;
        }

        public static function setParent(int $ID, int $ParentID): void
        {
            self::checkRoot($ID);
            self::checkObject($ID);

            self::$objects[self::$objects[$ID]['ParentID']]['ChildrenIDs'] = array_diff(self::$objects[self::$objects[$ID]['ParentID']]['ChildrenIDs'], [$ID]);
            self::$objects[$ID]['ParentID'] = $ParentID;
            self::$objects[$ParentID]['ChildrenIDs'][] = $ID;
        }

        public static function setIdent(int $ID, string $Ident): void
        {
            self::checkObject($ID);

            if (!preg_match('/[a-zA-Z0-9_]*/', $Ident)) {
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

            self::$objects[$ID]['ObjectIcon'] = $Icon;
        }

        public static function setSummary(int $ID, string $Summary): void
        {
            self::checkRoot($ID);

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

        public static function getObjectIDByName(string $Name, int $ParentID): array
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

            throw new \Exception(sprintf('Object with name %s could not be found', $Name));
        }

        public static function getObjectIDByNameEx(string $Name, int $ParentID, int $ObjectType): int
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

            throw new \Exception(sprintf('Object with name %s could not be found', $Name));
        }

        public static function getObjectIDByIdent(string $Ident, int $ParentID)
        {
            if ($Ident == '') {
                throw new \Exception('Ident cannot be empty');
            }

            self::checkObject($ParentID);
            foreach (self::$objects[$ParentID]['ChildrenIDs'] as $ChildID) {
                self::checkObject($ChildID);
                if (self::$objects[$ChildID]['ObjectIdent'] == $Ident) {
                    return $ChildID;
                }
            }

            trigger_error(sprintf('Object with ident %s could not be found', $Ident));
            return false;
        }

        public static function hasChildren(int $ID): bool
        {
            return count(self::getChildrenIDs($ID)) > 0;
        }

        public static function isChild(int $ID, int $ParentID, bool $Recursive): bool
        {
            throw new \Exception('FIXME: Not implemented');
        }

        public static function getChildrenIDs(int $ID): array
        {
            self::checkObject($ID);

            return self::$objects[$ID]['ChildrenIDs'];
        }

        public static function getName(int $ID): string
        {
            return self::$objects[$ID]['ObjectName'];
        }

        public static function getParent(int $ID): int
        {
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

            return [];
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
                'InstanceID'      => $InstanceID,
                'ConnectionID'    => 0,
                'InstanceStatus'  => 100 /* IS_CREATING */,
                'InstanceChanged' => time(),
                'ModuleInfo'      => [
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
        }

        public static function deleteInstance(int $InstanceID): void
        {
            self::checkInstance($InstanceID);
            unset(self::$instances[$InstanceID]);
            unset(self::$interfaces[$InstanceID]);
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

        public static function getStatus($InstanceID): int
        {
            self::checkInstance($InstanceID);

            return self::$instances[$InstanceID]['InstanceStatus'];
        }

        public static function connectInstance(int $InstanceID, int $ParentID): void
        {
            self::checkInstance($InstanceID);
            self::$instances[$InstanceID]['ConnectionID'] = $ParentID;
        }

        public static function disconnectInstance(int $InstanceID): void
        {
            self::checkInstance($InstanceID);
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

        private static function checkInstance(int $InstanceID): void
        {
            if (!self::instanceExists($InstanceID)) {
                throw new \Exception(sprintf('Instance #%d does not exist', $InstanceID));
            }
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
            self::checkVariable($VariableID);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableBoolean(int $VariableID, bool $VariableValue): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableUpdated'] = time();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = time();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableInteger(int $VariableID): int
        {
            self::checkVariable($VariableID);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableInteger(int $VariableID, int $VariableValue): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableUpdated'] = time();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = time();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableFloat(int $VariableID): float
        {
            self::checkVariable($VariableID);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableFloat(int $VariableID, float $VariableValue): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableUpdated'] = time();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = time();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function readVariableString(int $VariableID): string
        {
            self::checkVariable($VariableID);

            return self::$variables[$VariableID]['VariableValue'];
        }

        public static function writeVariableString(int $VariableID, string $VariableValue): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableUpdated'] = time();
            if (self::$variables[$VariableID]['VariableValue'] != $VariableValue) {
                self::$variables[$VariableID]['VariableChanged'] = time();
            }
            self::$variables[$VariableID]['VariableValue'] = $VariableValue;
        }

        public static function variableExists(int $VariableID): bool
        {
            return isset(self::$variables[$VariableID]);
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
            return $variable['VariableCustomPresentation'];
        }

        public static function getVariableList(): array
        {
            return array_keys(self::$variables);
        }

        public static function setVariableCustomProfile(int $VariableID, string $ProfileName): void
        {
            self::checkVariable($VariableID);

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

            self::$variables[$VariableID]['VariableCustomAction'] = $ScriptID;
        }

        public static function setVariableCustomPresentation(int $VariableID, array $Presentation): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableCustomPresentation'] = $Presentation;
        }

        public static function setVariableProfile(int $VariableID, string $ProfileName): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableProfile'] = $ProfileName;

            if (empty($ProfileName)) {
                self::setVariablePresentation($VariableID, []);
            } else {
                self::setVariablePresentation($VariableID, ['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'PROFILE' => $ProfileName]);
            }
        }

        public static function setVariableAction(int $VariableID, int $InstanceID): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariableAction'] = $InstanceID;
        }

        public static function setVariablePresentation(int $VariableID, array $Presentation): void
        {
            self::checkVariable($VariableID);

            self::$variables[$VariableID]['VariablePresentation'] = $Presentation;
        }

        public static function reset()
        {
            self::$variables = [];
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
                'ScriptIsBroken' => false
            ];

            self::$content[$ScriptID] = '<?php ' . PHP_EOL . PHP_EOL . '//Start writing your scripts between the brackets' . PHP_EOL . PHP_EOL . '?>';
        }

        public static function deleteScript(int $ScriptID, bool $DeleteFile): void
        {
            self::checkScript($ScriptID);
            unset(self::$scripts[$ScriptID]);
        }

        public static function setScriptFile(int $ScriptID, string $FilePath): void
        {
            self::$scripts[$ScriptID]['ScriptFile'] = $FilePath;
        }

        public static function setScriptContent(int $ScriptID, string $Content): void
        {
            self::$content[$ScriptID] = $Content;
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
            return self::$content[$ScriptID];
        }

        public static function reset()
        {
            self::$scripts = [];
        }
    }

    class ScriptEngine
    {
        private static $semaphores = [];

        public static function runScript(int $ScriptID): void
        {
            self::runScriptEx($ScriptID, []);
        }

        public static function runScriptEx(int $ScriptID, array $Parameters): void
        {
            self::runScriptWaitEx($ScriptID, $Parameters);
        }

        public static function IPS_RunScriptWait(int $ScriptID): string
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
        }

        public static function mediaExists(int $MediaID): bool
        {
            return isset(self::$medias[$MediaID]);
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
            self::checkVariableProfile($ProfileName);
            unset(self::$profiles[$ProfileName]);
        }

        public static function setVariableProfileText(string $ProfileName, string $Prefix, string $Suffix): void
        {
            self::checkVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['Prefix'] = $Prefix;
            self::$profiles[$ProfileName]['Suffix'] = $Suffix;
        }

        public static function setVariableProfileValues(string $ProfileName, float $MinValue, float $MaxValue, float $StepSize): void
        {
            self::checkVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['MinValue'] = $MinValue;
            self::$profiles[$ProfileName]['MaxValue'] = $MaxValue;
            self::$profiles[$ProfileName]['StepSize'] = $StepSize;
        }

        public static function setVariableProfileDigits(string $ProfileName, int $Digits): void
        {
            self::checkVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['Digits'] = $Digits;
        }

        public static function setVariableProfileIcon(string $ProfileName, string $Icon): void
        {
            self::checkVariableProfile($ProfileName);

            self::$profiles[$ProfileName]['Icon'] = $Icon;
        }

        public static function setVariableProfileAssociation(string $ProfileName, $AssociationValue, string $AssociationName, string $AssociationIcon, int $AssociationColor)
        {
            self::checkVariableProfile($ProfileName);
            if (($AssociationName == '') && ($AssociationIcon == '')) {
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
                        usort(self::$profiles[$ProfileName]['Associations'], function ($a, $b) {
                            return $a['Value'] - $b['Value'];
                        });
                    }
                } else {
                    trigger_error(sprintf('Cannot find association for deletion with value %f', $AssociationValue), E_USER_WARNING);
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
                usort(self::$profiles[$ProfileName]['Associations'], function ($a, $b) {
                    return $a['Value'] - $b['Value'];
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
                throw new \Exception(sprintf('Profile #%s does not exist', $ProfileName));
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
                    $result[] = $profile;
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
            VariableManager::reset();
            ScriptManager::reset();
            EventManager::reset();
            MediaManager::reset();
            LinkManager::reset();
            ProfileManager::reset();
            DebugServer::reset();
            ActionPool::reset();
            PresentationPool::reset();
            TemplateManager::reset();
        }
    }
}
