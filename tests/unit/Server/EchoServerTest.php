<?php
namespace Ratchet\Server;
use Ratchet\Server\EchoServer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EchoServer::class)]
class EchoServerTest extends TestCase {
    protected $_conn;
    protected $_comp;

    public function setUp(): void {
        $this->_conn = $this->createMock('\Ratchet\ConnectionInterface');
        $this->_comp = new EchoServer;
    }

    public function testMessageEchod() {
        $message = 'Tillsonburg, my back still aches when I hear that word.';
        $this->_conn->expects($this->once())->method('send')->with($message);
        $this->_comp->onMessage($this->_conn, $message);
    }

    public function testErrorClosesConnection() {
        ob_start();
        $this->_conn->expects($this->once())->method('close');
        $this->_comp->onError($this->_conn, new \Exception);
        ob_end_clean();
    }
}
