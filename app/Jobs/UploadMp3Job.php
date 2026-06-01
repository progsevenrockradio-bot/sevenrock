<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * @deprecated Use ProcessMp3Job instead.
 */
class UploadMp3Job extends ProcessMp3Job
{
    public int $timeout = 900;
}
