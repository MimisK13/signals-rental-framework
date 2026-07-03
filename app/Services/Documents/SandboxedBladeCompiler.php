<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Dedicated Blade compiler for document templates with dangerous directives disabled.
 *
 * Uses an isolated cache path so compiled document templates never share the
 * application view cache.
 */
class SandboxedBladeCompiler extends BladeCompiler
{
    protected function compilePhp($expression): never
    {
        throw DocumentRenderException::forbidden('Raw PHP blocks are not allowed in document templates.');
    }

    protected function compileInject($expression): never
    {
        throw DocumentRenderException::forbidden('Service injection is not allowed in document templates.');
    }

    protected function compileInclude($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeIf($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeWhen($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeUnless($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeFirst($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileIncludeIsolated($expression): never
    {
        throw DocumentRenderException::forbidden('View includes are not allowed in document templates.');
    }

    protected function compileExtends($expression): never
    {
        throw DocumentRenderException::forbidden('Layout inheritance is not allowed in document templates.');
    }

    protected function compileExtendsFirst($expression): never
    {
        throw DocumentRenderException::forbidden('Layout inheritance is not allowed in document templates.');
    }

    protected function compileComponent($expression): never
    {
        throw DocumentRenderException::forbidden('Blade components are not allowed in document templates.');
    }

    protected function compileComponentFirst($expression): never
    {
        throw DocumentRenderException::forbidden('Blade components are not allowed in document templates.');
    }

    /**
     * @param  string  $value
     */
    protected function storePhpBlocks($value): never
    {
        throw DocumentRenderException::forbidden('Raw PHP blocks are not allowed in document templates.');
    }

    /**
     * @param  string|null  $expression
     */
    protected function compileForeach($expression): string
    {
        preg_match('/\( *(.+) +as +(.*)\)$/is', $expression ?? '', $matches);

        if (count($matches) === 0) {
            throw DocumentRenderException::forbidden('Malformed @foreach statement.');
        }

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $prepare = SandboxedLoopEnvironment::class.'::prepareLoopData('.$iteratee.')';
        $initLoop = "\$__currentLoopData = {$prepare}; \$__env->addLoop(\$__currentLoopData);";
        $iterateLoop = '$__env->incrementLoopIndices(); $loop = $__env->getLastLoop();';

        return "<?php {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} ?>";
    }

    protected function compileEndforeach(): string
    {
        return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
    }

    /**
     * @param  string|null  $expression
     */
    protected function compileForelse($expression): string
    {
        $empty = '$__empty_'.++$this->forElseCounter;

        preg_match('/\( *(.+) +as +(.+)\)$/is', $expression ?? '', $matches);

        if (count($matches) === 0) {
            throw DocumentRenderException::forbidden('Malformed @forelse statement.');
        }

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);
        $prepare = SandboxedLoopEnvironment::class.'::prepareLoopData('.$iteratee.')';
        $initLoop = "\$__currentLoopData = {$prepare}; \$__env->addLoop(\$__currentLoopData);";
        $iterateLoop = '$__env->incrementLoopIndices(); $loop = $__env->getLastLoop();';

        return "<?php {$empty} = true; {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} {$empty} = false; ?>";
    }

    protected function compileEmpty($expression): string
    {
        if ($expression) {
            return "<?php if(empty{$expression}): ?>";
        }

        $empty = '$__empty_'.$this->forElseCounter--;

        return "<?php endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$empty}): ?>";
    }
}
