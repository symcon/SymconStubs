<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

class IOStubsKernelAlignmentTest extends TestCase
{
    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../IOStubs/library.json');
        parent::setUp();
    }

    /**
     * Values as returned by IPS_GetModule() on Symcon 9.1.
     */
    public static function kernelModules(): array
    {
        return [
            'SSE Client' => [
                '{2FADB4B7-FDAB-3C64-3E2C-068A4809849A}',
                1,
                'SSEC',
                ['{5A709184-B602-D394-227F-207611A33BDF}'],
                ['{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'],
            ],
            'UDP Socket' => [
                '{82347F20-F541-41E1-AC5B-A636FD3AE2D8}',
                1,
                'USCK',
                ['{9082C662-7864-D5CA-863F-53999200D897}', '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}', '{018EF6B5-AB94-40C6-AA53-46943E824ACF}'],
                ['{8E4D9B23-E0F2-1E05-41D8-C21EA53B8706}', '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}'],
            ],
            'Virtual IO' => [
                '{6179ED6A-FC31-413C-BB8E-1204150CF376}',
                1,
                'VIO',
                ['{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}', '{9082C662-7864-D5CA-863F-53999200D897}', '{5A709184-B602-D394-227F-207611A33BDF}', '{018EF6B5-AB94-40C6-AA53-46943E824ACF}'],
                ['{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}', '{8E4D9B23-E0F2-1E05-41D8-C21EA53B8706}', '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', '{D4C1D08F-CD3B-494B-BE18-B36EF73B8F43}'],
            ],
            'VoIP' => [
                '{A4224A63-49EA-445F-8422-22EF99D8F624}',
                3,
                'VoIP',
                [],
                [],
            ],
        ];
    }

    /**
     * @dataProvider kernelModules
     */
    public function testModuleDefinitionMatchesKernel(string $moduleID, int $type, string $prefix, array $childRequirements, array $implemented): void
    {
        $module = IPS_GetModule($moduleID);

        $this->assertSame($type, $module['ModuleType']);
        $this->assertSame($prefix, $module['Prefix']);
        $this->assertEqualsCanonicalizing($childRequirements, $module['ChildRequirements']);
        $this->assertEqualsCanonicalizing($implemented, $module['Implemented']);
    }

    public function testSSEClientHasKernelProperties(): void
    {
        $id = IPS_CreateInstance('{2FADB4B7-FDAB-3C64-3E2C-068A4809849A}');
        $configuration = json_decode(IPS_GetConfiguration($id), true);

        //The SSE Client does not have any other property, e.g. no Open like the Virtual IO
        $this->assertEqualsCanonicalizing(['Active', 'URL', 'Headers', 'VerifyHost', 'VerifyPeer'], array_keys($configuration));
        $this->assertFalse($configuration['Active']);
        $this->assertSame('', $configuration['URL']);
        $this->assertSame('[]', $configuration['Headers']);
        $this->assertTrue($configuration['VerifyHost']);
        $this->assertTrue($configuration['VerifyPeer']);
    }
}
