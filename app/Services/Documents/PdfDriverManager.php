<?php

namespace App\Services\Documents;

use App\Contracts\Documents\PdfDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PdfDriverManager
{
    /**
     * @var array<string, class-string<PdfDriver>>
     */
    private array $drivers;

    public function __construct(
        private readonly Container $container,
    ) {
        /** @var array<string, class-string<PdfDriver>> $configured */
        $configured = config('documents.drivers', []);
        $this->drivers = $configured;
    }

    /**
     * @param  class-string<PdfDriver>  $driverClass
     */
    public function register(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    public function driver(?string $name = null): PdfDriver
    {
        $name ??= (string) config('documents.pdf_driver', 'dompdf');

        $driverClass = $this->drivers[$name] ?? null;

        if ($driverClass === null) {
            throw new InvalidArgumentException("Unknown document PDF driver [{$name}].");
        }

        return $this->container->make($driverClass);
    }

    /**
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        return array_keys($this->drivers);
    }
}
