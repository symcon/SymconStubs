<?php

declare(strict_types=1);

include_once __DIR__ . '/../autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * The I/O stubs have to handle all data flows of their module.json like the kernel does.
 */
class IODataFlowTest extends TestCase
{
    private const SSE_CLIENT = '{2FADB4B7-FDAB-3C64-3E2C-068A4809849A}';
    private const UDP_SOCKET = '{82347F20-F541-41E1-AC5B-A636FD3AE2D8}';
    private const VIRTUAL_IO = '{6179ED6A-FC31-413C-BB8E-1204150CF376}';

    private const SSE_DEVICE = '{FB646C9C-CA6F-7756-501D-1D3D33193416}';
    private const PACKET_DEVICE = '{BF7D6437-7328-2D41-2042-9038A831D33F}';
    private const STRICT_DEVICE = '{89572185-1E80-0442-578E-FC4EE4CE5341}';

    private const SSE_RX_DATA = '{5A709184-B602-D394-227F-207611A33BDF}';
    private const SSCK_RX_PACKET = '{7A1272A4-CBDB-46EF-BFC6-DCF4A53D2FC7}';
    private const SSCK_TX_PACKET = '{C8792760-65CF-4C53-B5C7-A30FCC84FEFE}';
    private const USCK_RX_PACKET = '{9082C662-7864-D5CA-863F-53999200D897}';
    private const USCK_TX_PACKET = '{8E4D9B23-E0F2-1E05-41D8-C21EA53B8706}';
    private const HTTP_TX_PAGE = '{D4C1D08F-CD3B-494B-BE18-B36EF73B8F43}';

    protected function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../IOStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/DataFlowStubs/library.json');
        parent::setUp();
    }

    public static function sseParents(): array
    {
        return [
            'SSE Client' => [self::SSE_CLIENT],
            'Virtual IO' => [self::VIRTUAL_IO],
        ];
    }

    public static function packetParents(): array
    {
        return [
            'UDP Socket' => [self::UDP_SOCKET],
            'Virtual IO' => [self::VIRTUAL_IO],
        ];
    }

    /**
     * @dataProvider sseParents
     */
    public function testPushDataReachesSSEChild(string $parentModuleID): void
    {
        [$parent, $child] = $this->connect($parentModuleID, self::SSE_DEVICE);

        $parent->PushData('STATUS', '{"key":"value"}', '', '42');

        $this->assertSame([[
            'DataID' => self::SSE_RX_DATA,
            'Event'  => 'STATUS',
            'Data'   => '{"key":"value"}',
            'Retry'  => '',
            'ID'     => '42',
        ]], $child->received);
    }

    public function testVirtualIOPrefersServerSocketPacket(): void
    {
        [$parent, $child] = $this->connect(self::VIRTUAL_IO, self::PACKET_DEVICE);

        $parent->PushPacket('Hello', '192.168.0.1', 1234);

        //The child implements the UDP packet as well, but must not receive the data twice
        $this->assertSame([[
            'DataID'     => self::SSCK_RX_PACKET,
            'Type'       => 0,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
        ]], $child->received);
    }

    public function testUDPSocketPrefersUDPPacket(): void
    {
        [$parent, $child] = $this->connect(self::UDP_SOCKET, self::PACKET_DEVICE);

        $parent->PushPacket('Hello', '192.168.0.1', 1234);

        //The child implements the Server Socket packet and the text as well, but must not receive the data multiple times
        $this->assertSame([[
            'DataID'     => self::USCK_RX_PACKET,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => 0,
        ]], $child->received);
    }

    public function testUDPSocketReportsLocalBroadcast(): void
    {
        [$parent, $child] = $this->connect(self::UDP_SOCKET, self::PACKET_DEVICE);
        $parentID = IPS_GetInstanceListByModuleID(self::UDP_SOCKET)[0];
        IPS_SetProperty($parentID, 'EnableBroadcast', true);
        IPS_ApplyChanges($parentID);

        $parent->PushPacket('Hello', '192.168.0.1', 1234);

        $this->assertSame(1, $child->received[0]['Broadcast']);
    }

    public function testStrictChildReceivesUDPPacketAsHex(): void
    {
        [$parent, $child] = $this->connect(self::VIRTUAL_IO, self::STRICT_DEVICE);

        $parent->PushPacket("\xFF\x01", '192.168.0.1', 1234);

        $this->assertSame([[
            'DataID'     => self::USCK_RX_PACKET,
            'Buffer'     => 'ff01',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => 0,
        ]], $child->received);
    }

    /**
     * @dataProvider packetParents
     */
    public function testForwardServerSocketPacket(string $parentModuleID): void
    {
        [$parent, $child] = $this->connect($parentModuleID, self::PACKET_DEVICE);

        $child->Send(json_encode([
            'DataID'     => self::SSCK_TX_PACKET,
            'Type'       => 0,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
        ]));

        $this->assertSame([
            'Type'       => 0,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
        ], $parent->PopPacket());
        $this->assertFalse($parent->HasPacket());
    }

    /**
     * @dataProvider packetParents
     */
    public function testForwardUDPPacket(string $parentModuleID): void
    {
        [$parent, $child] = $this->connect($parentModuleID, self::PACKET_DEVICE);

        //The kernel still accepts the boolean, which was used before the broadcast modes were introduced
        $child->Send(json_encode([
            'DataID'     => self::USCK_TX_PACKET,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => true,
        ]));

        $this->assertSame([
            'Type'       => 0,
            'Buffer'     => 'Hello',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => 1,
        ], $parent->PopPacket());
        $this->assertFalse($parent->HasPacket());
    }

    public function testForwardUDPPacketOfStrictChild(): void
    {
        [$parent, $child] = $this->connect(self::VIRTUAL_IO, self::STRICT_DEVICE);

        $child->Send(json_encode([
            'DataID'     => self::USCK_TX_PACKET,
            'Buffer'     => 'ff01',
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => 2,
        ]));

        $this->assertSame([
            'Type'       => 0,
            'Buffer'     => "\xFF\x01",
            'ClientIP'   => '192.168.0.1',
            'ClientPort' => 1234,
            'Broadcast'  => 2,
        ], $parent->PopPacket());
    }

    public function testVirtualIOAnswersHTTPRequest(): void
    {
        [$parent, $child] = $this->connect(self::VIRTUAL_IO, self::PACKET_DEVICE);
        $parent->HTTPResponse('/status', 'GET', 'Status');
        $parent->HTTPResponse('*', 'POST', 'Posted');

        $this->assertSame('Status', $child->Send($this->httpRequest('GET', '/status')));
        $this->assertSame('Posted', $child->Send($this->httpRequest('POST', '/anything')));
    }

    public function testVirtualIORejectsUnknownHTTPRoute(): void
    {
        [$parent, $child] = $this->connect(self::VIRTUAL_IO, self::PACKET_DEVICE);
        $parent->HTTPResponse('/status', 'GET', 'Status');

        $this->expectExceptionMessage('Requested Route could not be found!');
        $child->Send($this->httpRequest('GET', '/unknown'));
    }

    private function connect(string $parentModuleID, string $childModuleID): array
    {
        $parentID = IPS_CreateInstance($parentModuleID);
        $childID = IPS_CreateInstance($childModuleID);
        IPS_ConnectInstance($childID, $parentID);

        return [IPS\InstanceManager::getInstanceInterface($parentID), IPS\InstanceManager::getInstanceInterface($childID)];
    }

    private function httpRequest(string $method, string $url): string
    {
        return json_encode([
            'DataID'        => self::HTTP_TX_PAGE,
            'RequestMethod' => $method,
            'RequestURL'    => $url,
            'RequestData'   => '',
            'Timeout'       => 10000,
        ]);
    }
}
