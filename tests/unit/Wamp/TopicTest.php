<?php
namespace Ratchet\Wamp;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Topic::class)]
class TopicTest extends TestCase {
    public function testGetId() {
        $id    = uniqid();
        $topic = new Topic($id);

        $this->assertEquals($id, $topic->getId());
    }

    public function testAddAndCount() {
        $topic = new Topic('merp');

        $topic->add($this->newConn());
        $topic->add($this->newConn());
        $topic->add($this->newConn());

        $this->assertEquals(3, count($topic));
    }

    public function testRemove() {
        $topic   = new Topic('boop');
        $tracked = $this->newConn();

        $topic->add($this->newConn());
        $topic->add($tracked);
        $topic->add($this->newConn());

        $topic->remove($tracked);

        $this->assertEquals(2, count($topic));
    }

    public function testBroadcast() {
        $msg  = 'Hello World!';
        $name = 'Batman';
        $wamp = new \stdClass();
        $wamp->sessionId = '123';
        $wamp->prefixes = array();
        $protocol = json_encode(array(8, $name, $msg));

        $first = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));
        $second = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));

        $first->WAMP = $wamp;
        $second->WAMP = $wamp;
        $first->expects($this->once())
              ->method('send')
              ->with($this->equalTo($protocol));

        $second->expects($this->once())
              ->method('send')
              ->with($this->equalTo($protocol));

        $topic = new Topic($name);
        $topic->add($first);
        $topic->add($second);

        $topic->broadcast($msg);
    }

    public function testBroadcastWithExclude() {
        $msg  = 'Hello odd numbers';
        $name = 'Excluding';
        $protocol = json_encode(array(8, $name, $msg));
        $wamp = new \stdClass();
        $wamp->sessionId = '123';
        $wamp->prefixes = array();

        $first = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));
        $second = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));
        $third = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));

        $first->WAMP = $wamp;
        $second->WAMP = $wamp;
        $third->WAMP = $wamp;
        $first->expects($this->once())
            ->method('send')
            ->with($this->equalTo($protocol));

        $second->expects($this->never())->method('send');

        $third->expects($this->once())
            ->method('send')
            ->with($this->equalTo($protocol));

        $topic = new Topic($name);
        $topic->add($first);
        $topic->add($second);
        $topic->add($third);

        $topic->broadcast($msg, array($second->WAMP->sessionId));
    }

    public function testBroadcastWithEligible() {
        $msg  = 'Hello white list';
        $name = 'Eligible';
        $protocol = json_encode(array(8, $name, $msg));
        $wamp = new \stdClass();
        $wamp->sessionId = '123';
        $wamp->prefixes = array();

        $first = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));
        $second = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));
        $third = $this->createPartialMock('Ratchet\\Wamp\\WampConnection', array('send'));

        $first->WAMP = $wamp;
        $second->WAMP = $wamp;
        $third->WAMP = $wamp;

        $first->expects($this->once())
            ->method('send')
            ->with($this->equalTo($protocol));

        $second->expects($this->never())->method('send');

        $third->expects($this->once())
            ->method('send')
            ->with($this->equalTo($protocol));

        $topic = new Topic($name);
        $topic->add($first);
        $topic->add($second);
        $topic->add($third);

        $topic->broadcast($msg, array(), array($first->WAMP->sessionId, $third->WAMP->sessionId));
    }

    public function testIterator() {
        $first  = $this->newConn();
        $second = $this->newConn();
        $third  = $this->newConn();

        $topic  = new Topic('Joker');
        $topic->add($first)->add($second)->add($third);

        $check = array($first, $second, $third);

        foreach ($topic as $mock) {
            $this->assertNotSame(false, array_search($mock, $check));
        }
    }

    public function testToString() {
        $name  = 'Bane';
        $topic = new Topic($name);

        $this->assertEquals($name, (string)$topic);
    }

    public function testDoesHave() {
        $conn  = $this->newConn();
        $topic = new Topic('Two Face');
        $topic->add($conn);

        $this->assertTrue($topic->has($conn));
    }

    public function testDoesNotHave() {
        $conn  = $this->newConn();
        $topic = new Topic('Alfred');

        $this->assertFalse($topic->has($conn));
    }

    public function testDoesNotHaveAfterRemove() {
        $conn  = $this->newConn();
        $topic = new Topic('Ras');

        $topic->add($conn)->remove($conn);

        $this->assertFalse($topic->has($conn));
    }

    protected function newConn() {
        return new WampConnection($this->createMock('\\Ratchet\\ConnectionInterface'));
    }
}
