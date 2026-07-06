<?php

namespace Spatie\Browsershot;

/**
 * PHPStan stub for the optional spatie/browsershot dependency.
 */
class Browsershot
{
    public static function html(string $html): self
    {
        return new self;
    }

    public function format(string $format): self
    {
        return $this;
    }

    public function landscape(bool $landscape = true): self
    {
        return $this;
    }

    public function margins(float $top, float $right, float $bottom, float $left): self
    {
        return $this;
    }

    public function showBrowserHeaderAndFooter(): self
    {
        return $this;
    }

    public function headerHtml(string $html): self
    {
        return $this;
    }

    public function footerHtml(string $html): self
    {
        return $this;
    }

    public function hideHeader(): self
    {
        return $this;
    }

    public function hideFooter(): self
    {
        return $this;
    }

    public function pdf(): string
    {
        return '';
    }
}
