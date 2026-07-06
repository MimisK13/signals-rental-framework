<?php

namespace Pest\Browser\Api;

class PendingAwaitablePage
{
    public function assertSee(string $text): self
    {
        return $this;
    }

    public function assertPresent(string $selector): self
    {
        return $this;
    }

    public function assertNoJavascriptErrors(): self
    {
        return $this;
    }
}

class ArrayablePendingAwaitablePage
{
    public function assertNoJavascriptErrors(): self
    {
        return $this;
    }
}

interface Webpage {}
