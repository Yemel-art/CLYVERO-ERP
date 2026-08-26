<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\StrongPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StrongPasswordRuleTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function passwordProvider(): array
    {
        return [
            'too short' => ['Abc1!aB', false],
            'no uppercase' => ['lowercase1234!', false],
            'no lowercase' => ['UPPERCASE1234!', false],
            'no digit' => ['NoDigitsHere!', false],
            'no special' => ['NoSpecial1234X', false],
            'minimum valid' => ['Abcdef1234!X', true],
            'strong administrator' => ['ClyveroSecure2027!', true],
            'unicode passphrase' => ['Yaounde2027Cameroon!', true],
        ];
    }

    #[DataProvider('passwordProvider')]
    public function test_password_validation(string $password, bool $shouldPass): void
    {
        $failed = false;
        (new StrongPassword())->validate('password', $password, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertSame(! $shouldPass, $failed);
    }
}
