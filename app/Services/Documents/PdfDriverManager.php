<?php

namespace App\Services\Documents;

use App\Contracts\Documents\PdfDriver;
use App\Services\Documents\Drivers\BrowsershotDriver;
use App\Services\Documents\Drivers\DompdfDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PdfDriverManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function driver(?string $name = null): PdfDriver
    {
        $name ??= (string) config('documents.pdf_driver', 'dompdf');

        return match ($name) {
            'dompdf' => $this->container->make(DompdfDriver::class),
            'browsershot' => $this->container->make(BrowsershotDriver::class),
            default => throw new InvalidArgumentException("Unknown document PDF driver [{$name}]."),
        };
    }

    /**
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        return array_keys(config('documents.drivers', []));
    }
}
