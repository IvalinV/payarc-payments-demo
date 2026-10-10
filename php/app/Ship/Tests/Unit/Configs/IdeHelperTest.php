<?php

namespace App\Ship\Tests\Unit\Configs;

use App\Ship\Tests\ShipTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class IdeHelperTest extends ShipTestCase
{
    public function testConfigHasCorrectValues(): void
    {
        $config = config('ide-helper');
        $this->assertSame('_ide_helper.php', $config['filename']);
        $this->assertSame('_ide_helper_models.php', $config['models_filename']);
        $this->assertSame('.phpstorm.meta.php', $config['meta_filename']);
        $this->assertSame([
            base_path() . '/vendor/laravel/framework/src/Illuminate/Support/helpers.php',
        ], $config['helper_files']);
        $this->assertSame(['app/Containers/*/*/Models'], $config['model_locations']);
        $this->assertSame([
            'Eloquent' => ['Illuminate\Database\Eloquent\Builder', 'Illuminate\Database\Query\Builder'],
            'Session' => ['Illuminate\Session\Store'],
        ], $config['extra']);
        $this->assertTrue($config['include_class_docblocks']);
        $this->assertTrue($config['use_generics_annotations']);
    }
}
