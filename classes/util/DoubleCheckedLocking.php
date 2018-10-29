<?php

namespace malkusch\lock\util;

use malkusch\lock\exception\LockAcquireException;
use malkusch\lock\exception\LockReleaseException;
use malkusch\lock\mutex\Mutex;

/**
 * The double-checked locking pattern.
 *
 * You should not instantiate this class directly. Use {@link Mutex::check()}.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1P5FAZ4QhXCuwYPnLZdk3PJsqePbu1UDDA Donations
 * @license WTFPL
 */
class DoubleCheckedLocking
{
    
    /**
     * @var Mutex The mutex.
     */
    private $mutex;
    
    /**
     * @var callable The check.
     */
    private $check;

    /**
     * Sets the mutex.
     *
     * @param Mutex $mutex The mutex.
     * @internal
     */
    public function __construct(Mutex $mutex)
    {
        $this->mutex = $mutex;
    }
    
    /**
     * Sets the check.
     *
     * @param callable $check The check.
     * @internal
     */
    public function setCheck(callable $check)
    {
        $this->check = $check;
    }
    
    /**
     * Executes a code only if a check is true.
     *
     * Both the check and the code execution are locked by a mutex.
     * Only if the check fails the method returns before acquiring a lock.
     *
     * If then returns boolean FALSE, the check did not pass before or after
     * acquiring the lock. A boolean FALSE can also be returned from the
     * synchronized code to indicate that processing did not occure or has
     * failed. It is up to the user to decide.
     *
     * @param  callable $code The locked code.
     * @return mixed Boolean FALSE if check did not pass or mixed for what ever
     *               the synchronized code returns.
     *
     * @throws \Exception The execution block or the check threw an exception.
     * @throws LockAcquireException The mutex could not be acquired.
     * @throws LockReleaseException The mutex could not be released.
     */
    public function then(callable $code)
    {
        if (!call_user_func($this->check)) {
            return false;
        }

        return $this->mutex->synchronized(function () use ($code) {
            if (!call_user_func($this->check)) {
                return false;
            }

            return call_user_func($code);
        });
    }
}
