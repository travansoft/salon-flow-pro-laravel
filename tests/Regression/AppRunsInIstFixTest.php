<?php

namespace Tests\Regression;

use Tests\TestCase;

class AppRunsInIstFixTest extends TestCase
{
    /**
     * Bug: the app ran on PHP's UTC default, so now()/Carbon timestamps
     * were generated and stored in UTC while every UI screen and printed
     * receipt displayed them unconverted, showing times 5.5 hours behind
     * actual IST. Fixed by setting APP_TIMEZONE=Asia/Kolkata so all
     * generated and persisted timestamps are IST end to end.
     */
    public function test_application_timezone_is_ist(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('Asia/Kolkata', now()->timezoneName);
    }
}
