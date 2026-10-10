<?php

namespace App\Ship\Tests\Unit;

use App\Ship\Tests\ShipTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class GenerateDocumentationTest extends ShipTestCase
{
    public function testDocumentationCreation(): void
    {
        if (!is_file(base_path('node_modules/.bin/apidoc'))) {
            $this->markTestSkipped('API documentation generation requires node_modules/.bin/apidoc.');
        }

        $this->artisan('apiato:apidoc')
            ->assertSuccessful();
    }
}
