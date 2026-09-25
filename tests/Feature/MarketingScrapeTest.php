<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\ScrapeAndEnrichContactsJob;
use ReflectionMethod;

class MarketingScrapeTest extends TestCase
{
    public function test_it_filters_invalid_domains_and_prefixes()
    {
        $job = new ScrapeAndEnrichContactsJob(1, 'INBOX', 10);
        $method = new ReflectionMethod($job, 'isQualityContactEmail');
        $method->setAccessible(true);
        $reason = '';

        $this->assertTrue($method->invokeArgs($job, ['promocion@sevenrockradio.com', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['hello.john@gmail.com', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['prensa@banda.com', &$reason]));
        
        $this->assertFalse($method->invokeArgs($job, ['info@banda.com', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['admin@sevenrockradio.com', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['support@empresa.com', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['cualquiera@sentry.io', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['hola@apob.ai', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['notificaciones@mailchimpapp.net', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['contacto@wpallimport.com', &$reason]));
    }

    public function test_it_filters_invalid_roles()
    {
        $job = new ScrapeAndEnrichContactsJob(1, 'INBOX', 10);
        $method = new ReflectionMethod($job, 'isQualityContactRole');
        $method->setAccessible(true);
        $reason = '';

        $this->assertTrue($method->invokeArgs($job, ['manager', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['banda', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['rrpp', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['prensa', &$reason]));
        $this->assertTrue($method->invokeArgs($job, ['A&R', &$reason]));

        $this->assertFalse($method->invokeArgs($job, ['fan', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['oyente', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['suscripción', &$reason]));
        $this->assertFalse($method->invokeArgs($job, ['robot', &$reason]));
    }
}
