<?php

use App\Support\JsonSyntaxHighlight;

it('highlights json keys strings numbers booleans and null', function () {
    $json = '{"name":"Signals","count":3,"enabled":true,"missing":null}';
    $html = JsonSyntaxHighlight::highlight($json);

    expect($html)
        ->toContain('<span class="s-json-key">&quot;name&quot;</span>:')
        ->toContain('<span class="s-json-string">&quot;Signals&quot;</span>')
        ->toContain('<span class="s-json-number">3</span>')
        ->toContain('<span class="s-json-bool">true</span>')
        ->toContain('<span class="s-json-null">null</span>');
});

it('detects json strings', function () {
    expect(JsonSyntaxHighlight::isJson('{"ok":true}'))->toBeTrue()
        ->and(JsonSyntaxHighlight::isJson('plain-text'))->toBeFalse()
        ->and(JsonSyntaxHighlight::isJson(''))->toBeFalse();
});

it('returns an empty string when highlighting empty json input', function () {
    expect(JsonSyntaxHighlight::highlight(''))->toBe('');
});
