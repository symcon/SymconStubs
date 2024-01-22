<?php

declare(strict_types=1);

include_once __DIR__ . '/ModuleStrictStubs.php';

use PHPUnit\Framework\TestCase;

class TestCaseSymconValidation extends TestCase
{
    public function testNop(): void
    {
        $this->assertTrue(true);
    }

    protected function validateLibrary($folder): void
    {
        $library = json_decode(file_get_contents($folder . '/library.json'), true);

        $this->assertArrayHasKey('id', $library);
        $this->assertIsString($library['id']);
        $this->assertTrue($this->isValidGUID($library['id']), 'library id is not a valid GUID');

        $this->assertArrayHasKey('author', $library);
        $this->assertIsString($library['author']);

        $this->assertArrayHasKey('name', $library);
        $this->assertIsString($library['name']);

        $this->assertArrayHasKey('url', $library);
        $this->assertIsString($library['url']);
        $this->assertTrue($this->isValidURL($library['url']), 'library url is not a valid');

        $this->assertArrayHasKey('version', $library);
        $this->assertIsString($library['version']);

        $this->assertArrayHasKey('build', $library);
        $this->assertIsInt($library['build']);

        $this->assertArrayHasKey('date', $library);
        $this->assertIsInt($library['date']);

        //This is purely optional
        if (!isset($library['compatibility'])) {
            $this->assertCount(7, $library);
        } else {
            $this->assertCount(8, $library);
            $this->assertIsArray($library['compatibility']);
            if (isset($library['compatibility']['version'])) {
                $this->assertIsString($library['compatibility']['version']);
            }
            if (isset($library['compatibility']['date'])) {
                $this->assertIsInt($library['compatibility']['date']);
            }
        }
    }

    protected function validateModule($folder): void
    {
        $this->assertTrue(file_exists($folder . '/module.json'), 'module json is missing');

        if (file_exists($folder . '/module.json')) {
            $module = json_decode(file_get_contents($folder . '/module.json'), true);

            $this->assertArrayHasKey('id', $module);
            $this->assertIsString($module['id']);
            $this->assertTrue($this->isValidGUID($module['id']), 'module id is not a valid GUID');

            $this->assertArrayHasKey('name', $module);
            $this->assertIsString($module['name']);
            $this->assertTrue($this->isValidName($module['name']), 'module name is not valid');

            $this->assertArrayHasKey('type', $module);
            $this->assertIsInt($module['type']);
            $this->assertGreaterThanOrEqual(0, $module['type']);
            $this->assertLessThanOrEqual(5, $module['type']);

            $this->assertArrayHasKey('vendor', $module);
            $this->assertIsString($module['vendor']);

            $this->assertArrayHasKey('aliases', $module);
            $this->assertIsArray($module['aliases']);

            $this->assertArrayHasKey('url', $module);
            $this->assertIsString($module['url']);
            $this->assertTrue($this->isValidURL($module['url']), 'module url is not valid');

            $this->assertArrayHasKey('parentRequirements', $module);
            $this->assertIsArray($module['parentRequirements']);
            foreach ($module['parentRequirements'] as $parentRequirement) {
                $this->assertIsString($parentRequirement);
                $this->assertTrue($this->isValidGUID($parentRequirement), 'module parent requirements guid is not valid');
            }

            $this->assertArrayHasKey('childRequirements', $module);
            $this->assertIsArray($module['childRequirements']);
            foreach ($module['childRequirements'] as $childRequirement) {
                $this->assertIsString($childRequirement);
                $this->assertTrue($this->isValidGUID($childRequirement), 'module child requirements guid is not valid');
            }

            $this->assertArrayHasKey('implemented', $module);
            $this->assertIsArray($module['implemented']);
            foreach ($module['implemented'] as $implemented) {
                $this->assertIsString($implemented);
                $this->assertTrue($this->isValidGUID($implemented), 'module implemented guid is not valid');
            }

            $this->assertArrayHasKey('prefix', $module);
            $this->assertIsString($module['prefix']);
            $this->assertTrue($this->isValidPrefix($module['prefix']), 'module prefix is not valid');

            if (file_exists($folder . '/form.json')) {
                $this->assertTrue(json_decode(file_get_contents($folder . '/form.json')) !== null, 'module form.json is invalid JSON');
            }

            if (file_exists($folder . '/locale.json')) {
                $this->assertTrue(json_decode(file_get_contents($folder . '/locale.json')) !== null, 'module locale.json is invalid JSON');
            }

            //Check if parameter types are set
            include_once "$folder/module.php";
            $class = new \ReflectionClass(str_replace(' ', '', $module['name']));
            foreach ($class->GetMethods() as $method) {
                if (!$method->isPublic()) {
                    continue;
                }
                if (in_array($method->GetName(), ['__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__sleep', '__wakeup', '__toString', '__invoke', '__set_state', '__clone', '__debuginfo', 'Create', 'Destroy', 'ApplyChanges', 'ReceiveData', 'ForwardData', 'RequestAction', 'MessageSink', 'GetConfigurationForm', 'GetConfigurationForParent', 'Translate', 'GetProperty', 'SetProperty', 'SetConfiguration'])) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    $this->assertTrue($parameter->hasType(), sprintf("Parameter '%s' on method '%s' is missing type hint definition", $parameter->getName(), $method->getName()));
                }
            }
        }
    }

    private function isValidGUID($guid): bool
    {
        return preg_match('/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/', $guid) == 1;
    }

    private function isValidName($name): bool
    {
        return preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9 _]*[A-Za-z0-9])?$/', $name) == 1;
    }

    private function isValidPrefix($name): bool
    {
        return preg_match('/^[A-Z0-9]+$/', $name) == 1;
    }

    private function isValidURL($name): bool
    {
        return preg_match('/^(?:http:\/\/|https:\/\/)/', $name) == 1;
    }

    private function ignoreFolders(): array
    {
        return ['..', '.', 'libs', 'docs', 'imgs', 'tests'];
    }
}
