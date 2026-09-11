<?php

namespace Tests {
    use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
    use Laravel\Fortify\Features;

    abstract class TestCase extends BaseTestCase
    {
        use CreatesApplication;
        use \Illuminate\Foundation\Testing\WithFaker;

        protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
        {
            if (! Features::enabled($feature)) {
                $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
            }
        }
    }
}

namespace {
    if (! function_exists('fake')) {
        function fake(?string $locale = null): \Faker\Generator
        {
            $app = app();
            if ($app->bound(\Faker\Generator::class)) {
                return $app->make(\Faker\Generator::class);
            }

            return \Faker\Factory::create($locale ?? \Faker\Factory::DEFAULT_LOCALE);
        }
    }
}
