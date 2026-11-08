<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextProgramRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_next_program_component_without_broken_x_data_attribute(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Verify that double quotes do not appear inside x-data for next-program
        $this->assertStringNotContainsString('button[aria-label="Cerrar"]', $content, 'Found double quotes collision inside x-data attribute!');

        // 2. Parse HTML and verify no text node leaks JavaScript code
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);

        // Find the next-program container div
        $divs = $xpath->query('//div[contains(@x-data, "activeProgram")]');
        $this->assertGreaterThan(0, $divs->length, 'Next program container div not found');

        $nextProgDiv = $divs->item(0);
        $xData = $nextProgDiv->getAttribute('x-data');

        // x-data must contain the full component definition including init()
        $this->assertStringContainsString('init()', $xData, 'x-data was truncated prematurely!');
        $this->assertStringContainsString('this.closeInfoModal()', $xData, 'x-data does not contain closeInfoModal method');

        // The inner text of the div should NOT have the code as raw text outside elements
        // Direct child text nodes of the container:
        foreach ($nextProgDiv->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $this->assertStringNotContainsString('this.closeInfoModal()', $child->textContent, 'Leaked JavaScript detected in DOM text node!');
                $this->assertStringNotContainsString("window.addEventListener('pageshow'", $child->textContent, 'Leaked JavaScript detected in DOM text node!');
            }
        }
    }
}
