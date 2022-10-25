<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

class A
{
    public ?string $text;

    public function __construct(?string $text)
    {
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public static function getTextStatic(string $text): string
    {
        return $text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }
}
