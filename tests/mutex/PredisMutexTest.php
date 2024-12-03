<?php

namespace malkusch\lock\mutex;

use malkusch\lock\exception\LockAcquireException;
use malkusch\lock\exception\LockReleaseException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Predis\PredisException;
use Psr\Log\LoggerInterface;

/**
 * Tests for PredisMutex.
 *
 * @group   redis
 */
class PredisMutexTest extends TestCase
{
    /**
     * @var ClientInterface|MockObject
     */
    private $client;

    /**
     * @var PredisMutex
     */
    private $mutex;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->getMockBuilder(ClientInterface::class)
            ->setMethods(array_merge(get_class_methods(ClientInterface::class), ['set', 'eval']))
            ->getMock();

        $this->mutex = new PredisMutex([$this->client], 'test', 2.5);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mutex->setLogger($this->logger);
    }

    /**
     * Tests add() fails.
     */
    public function testAddFailsToSetKey()
    {
        $this->client->expects($this->atLeastOnce())
            ->method('set')
            ->with('lock_test', $this->isType('string'), 'PX', 3500, 'NX')
            ->willReturn(null);

        $this->logger->expects($this->never())
            ->method('warning');

        $this->expectException(LockAcquireException::class);

        $this->mutex->synchronized(
            function (): void {
                $this->fail('Code execution is not expected');
            }
        );
    }

    /**
     * Tests add() errors.
     */
    public function testAddErrors()
    {
        $this->client->expects($this->atLeastOnce())
            ->method('set')
            ->with('lock_test', $this->isType('string'), 'PX', 3500, 'NX')
            ->willThrowException($this->createMock(PredisException::class));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Could not set {key} = {token} at server #{index}.', $this->anything());

        $this->expectException(LockAcquireException::class);

        $this->mutex->synchronized(
            function () {
                $this->fail('Code execution is not expected');
            }
        );
    }

    public function testWorksNormally()
    {
        $this->client->expects($this->atLeastOnce())
            ->method('set')
            ->with('lock_test', $this->isType('string'), 'PX', 3500, 'NX')
            ->willReturnSelf();

        $this->client->expects($this->once())
            ->method('eval')
            ->with($this->anything(), 1, 'lock_test', $this->isType('string'))
            ->willReturn(true);

        $executed = false;

        $this->mutex->synchronized(function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    /**
     * Tests evalScript() fails.
     */
    public function testEvalScriptFails()
    {
        $this->client->expects($this->atLeastOnce())
            ->method('set')
            ->with('lock_test', $this->isType('string'), 'PX', 3500, 'NX')
            ->willReturnSelf();

        $this->client->expects($this->once())
            ->method('eval')
            ->with($this->anything(), 1, 'lock_test', $this->isType('string'))
            ->willThrowException($this->createMock(PredisException::class));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Could not unset {key} = {token} at server #{index}.', $this->anything());

        $executed = false;

        $this->expectException(LockReleaseException::class);

        $this->mutex->synchronized(function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }
}
