<?php

namespace Tests\Unit;

use App\Enums\MailTemplates;
use PHPUnit\Framework\TestCase;

class MailTemplatesEnumTest extends TestCase
{
    public function test_mail_templates_class_exists(): void
    {
        $this->assertTrue(class_exists(MailTemplates::class) || enum_exists(MailTemplates::class));
    }
}
