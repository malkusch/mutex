<?php

declare(strict_types=1);

namespace malkusch\lock\Tests\mutex;

use Eloquent\Liberator\Liberator;
use malkusch\lock\exception\DeadlineException;
use malkusch\lock\exception\TimeoutException;
use malkusch\lock\mutex\FlockMutex;
use malkusch\lock\util\LockUtil;
use malkusch\lock\util\PcntlTimeout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlockMutexTest extends TestCase
{
    /** @var FlockMutex */
    private $mutex;

    /** @var string */
    private $file;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->file = LockUtil::getInstance()->makeRandomTemporaryFilePath('flock');
        touch($this->file);
        $this->mutex = Liberator::liberate(new FlockMutex(fopen($this->file, 'r'), 1)); // @phpstan-ignore assign.propertyType
    }

    #[\Override]
    protected function tearDown(): void
    {
        unlink($this->file);

        parent::tearDown();
    }

    /**
     * @param FlockMutex::STRATEGY_* $strategy
     *
     * @dataProvider provideTimeoutableStrategiesCases
     */
    #[DataProvider('provideTimeoutableStrategiesCases')]
    public function testCodeExecutedOutsideLockIsNotThrown(int $strategy): void
    {
        $this->mutex->strategy = $strategy; // @phpstan-ignore property.private

        self::assertTrue($this->mutex->synchronized(static function (): bool { // @phpstan-ignore staticMethod.alreadyNarrowedType
            usleep(1100 * 1000);

            return true;
        }));
    }

    /**
     * @param FlockMutex::STRATEGY_* $strategy
     *
     * @dataProvider provideTimeoutableStrategiesCases
     */
    #[DataProvider('provideTimeoutableStrategiesCases')]
    public function testTimeoutOccurs(int $strategy): void
    {
        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Timeout of 1.0 seconds exceeded');

        $another_resource = fopen($this->file, 'r');
        flock($another_resource, \LOCK_EX);

        $this->mutex->strategy = $strategy; // @phpstan-ignore property.private

        try {
            $this->mutex->synchronized(
                static function () {
                    self::fail('Did not expect code to be executed');
                }
            );
        } finally {
            fclose($another_resource);
        }
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideTimeoutableStrategiesCases(): iterable
    {
        yield [FlockMutex::STRATEGY_PCNTL];
        yield [FlockMutex::STRATEGY_BUSY];
    }

    public function testNoTimeoutWaitsForever(): void
    {
        $this->expectException(DeadlineException::class);

        $another_resource = fopen($this->file, 'r');
        flock($another_resource, \LOCK_EX);

        $this->mutex->strategy = FlockMutex::STRATEGY_BLOCK; // @phpstan-ignore property.private

        $timebox = new PcntlTimeout(1);
        $timebox->timeBoxed(function () {
            $this->mutex->synchronized(static function (): void {
                self::fail('Did not expect code execution');
            });
        });
    }
}
