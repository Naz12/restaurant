<?php

namespace App\Traits;

use Froiden\Envato\Traits\AppBoot;

/**
 * Override AppBoot trait to disable licensing checks
 */
trait AppBootOverride
{
    use AppBoot;

    /**
     * Override isLegal() to always return true (disable licensing check)
     */
    public function isLegal(): bool
    {
        return true;
    }
}

