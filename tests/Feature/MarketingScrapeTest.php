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

        $this->assertTrue($method->invoke($job, 'promocion@sevenrockradio.com'));
        $this->assertTrue($method->invoke($job, 'hello.john@gmail.com'));
        $this->assertTrue($method->invoke($job, 'prensa@banda.com'));
        
        $this->assertFalse($method->invoke($job, 'info@banda.com'));
        $this->assertFalse($method->invoke($job, 'admin@sevenrockradio.com'));
        $this->assertFalse($method->invoke($job, 'support@empresa.com'));
        $this->assertFalse($method->invoke($job, 'cualquiera@sentry.io'));
        $this->assertFalse($method->invoke($job, 'hola@apob.ai'));
        $this->assertFalse($method->invoke($job, 'notificaciones@mailchimpapp.net'));
        $this->assertFalse($method->invoke($job, 'contacto@wpallimport.com'));
    }

    public function test_it_filters_invalid_roles()
    {
        $job = new ScrapeAndEnrichContactsJob(1, 'INBOX', 10);
        $method = new ReflectionMethod($job, 'isQualityContactRole');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($job, 'manager'));
        $this->assertTrue($method->invoke($job, 'banda'));
        $this->assertTrue($method->invoke($job, 'rrpp'));
        $this->assertTrue($method->invoke($job, 'prensa'));
        $this->assertTrue($method->invoke($job, 'A&R'));

        $this->assertFalse($method->invoke($job, 'fan'));
        $this->assertFalse($method->invoke($job, 'oyente'));
        $this->assertFalse($method->invoke($job, 'suscripción'));
        $this->assertFalse($method->invoke($job, 'robot'));
    }
}
