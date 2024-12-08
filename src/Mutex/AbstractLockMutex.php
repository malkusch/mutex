<?php

declare(strict_types=1);

namespace Malkusch\Lock\Mutex;

use Malkusch\Lock\Exception\LockAcquireException;
use Malkusch\Lock\Exception\LockReleaseException;

/**
 * Locking mutex.
 *
 * @internal
 */
abstract class AbstractLockMutex extends AbstractMutex
{
    /**
     * Acquire a lock.
     *
     * This method blocks until the lock was acquired.
     *
     * @throws LockAcquireException The lock could not be acquired
     */
    abstract protected function lock(): void;

    /**
     * Release the lock.
     *
     * @throws LockReleaseException The lock could not be released
     */
    abstract protected function unlock(): void;

    #[\Override]
    public function synchronized(callable $fx)
    {
        $this->lock();

        $fxResult = null;
        $fxException = null;
        try {
            $fxResult = $fx();
        } catch (\Throwable $fxException) {
            throw $fxException;
        } finally {
            try {
                $this->unlock();
            } catch (LockReleaseException $lockReleaseException) {
                $lockReleaseException->setCodeResult($fxResult);

                if ($fxException !== null) {
                    $lockReleaseException->setCodeException($fxException);
                }

                throw $lockReleaseException;
            }
        }

        return $fxResult;
    }
}
