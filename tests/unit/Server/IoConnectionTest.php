<?php
namespace Ratchet\Application\Server;
use Ratchet\Server\IoConnection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IoConnection::class)]
class IoConnectionTest extends TestCase {
    protected $sock;
    protected $conn;

    public function setUp(): void {
        $this->sock = $this->createMock('\\React\\Socket\\ConnectionInterface');
        $this->conn = new IoConnection($this->sock);
    }

    public function testCloseBubbles() {
        $this->sock->expects($this->once())->method('end');
        $this->conn->close();
    }

    public function testSendBubbles() {
        $msg = '6 hour rides are productive';

        $this->sock->expects($this->once())->method('write')->with($msg);
        $this->conn->send($msg);
    }

    public function testSendReturnsSelf() {
        $this->assertSame($this->conn, $this->conn->send('fluent interface'));
    }
}
