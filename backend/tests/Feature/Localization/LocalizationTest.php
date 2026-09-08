<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    public function test_api_messages_are_available_in_both_languages(): void
    {
        app()->setLocale('fr');
        $this->assertSame('Utilisateurs récupérés.', __('Users retrieved.'));

        app()->setLocale('en');
        $this->assertSame('Users retrieved.', __('Users retrieved.'));
    }

    public function test_standard_validation_messages_are_french(): void
    {
        app()->setLocale('fr');
        $validator = Validator::make([], ['email' => ['required', 'email']]);

        $this->assertTrue($validator->fails());
        $this->assertSame('Le champ adresse e-mail est obligatoire.', $validator->errors()->first('email'));
    }

    public function test_strong_password_rule_uses_the_active_language(): void
    {
        app()->setLocale('fr');
        $validator = Validator::make(['password' => 'weak'], ['password' => [new StrongPassword()]]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Le champ mot de passe doit', $validator->errors()->first('password'));
    }
}
