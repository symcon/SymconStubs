Script to show which IPS_* functions are implemented in the stubs. Run in Symcon with the path to the GlobalStubs.php.

```php
<?php
$functions = IPS_GetFunctionList(0);
$globalStubsPath = 'pathto\GlobalStubs.php';
$globalStubs = file_get_contents($globalStubsPath);
$pattern = '/function\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/';
$matches = [];
if (!preg_match_all($pattern, $globalStubs, $matches)) {
    die("no functions found in $globalStubsPath");
}
$stubs = $matches[1];
$ipsFunctions = [];
$missing = [];
foreach($functions as $function) {
    if (str_starts_with($function, 'IPS_')) {
        $ipsFunctions[] = $function;
        if (!in_array($function, $stubs)) {
            $missing[] = $function;
        }

    }
}
echo 'IPS_*' . PHP_EOL;
echo 'TOTAL ' . count($ipsFunctions) . PHP_EOL;
echo 'MISSING ' . count($missing) . PHP_EOL;
echo 'IMPLEMENTED ' . count($stubs) . PHP_EOL;
print_r($missing);
```
