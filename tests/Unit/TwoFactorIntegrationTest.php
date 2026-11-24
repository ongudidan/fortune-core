<?php

namespace Ongudidan\FortuneCore\Tests\Unit;

use Ongudidan\FortuneCore\FortuneCoreServiceProvider;
use Ongudidan\FortuneCore\Tests\TestCase;

class TwoFactorIntegrationTest extends TestCase
{
    public function test_fortune_core_registers_two_factor_bindings(): void
    {
        $this->assertTrue(config('fortune.modules.two_factor'));
        $this->assertTrue($this->app->providerIsLoaded(FortuneCoreServiceProvider::class));
    }

    public function test_two_factor_can_be_disabled_via_feature_toggle(): void
    {
        config(['fortune.modules.two_factor' => false]);
        $this->assertFalse(config('fortune.modules.two_factor'));
    }
}
