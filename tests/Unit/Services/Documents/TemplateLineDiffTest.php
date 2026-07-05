<?php

use App\Services\Documents\TemplateLineDiff;

describe('TemplateLineDiff', function () {
    beforeEach(function () {
        $this->diff = new TemplateLineDiff;
    });

    it('returns no operations for identical inputs', function () {
        $input = "<p>Hello</p>\n<span>World</span>";

        expect($this->diff->diff($input, $input))->toBe([]);
    });

    it('treats both empty inputs as an empty diff', function () {
        expect($this->diff->diff('', ''))->toBe([]);
    });

    it('treats empty old content as a full insert', function () {
        $ops = $this->diff->diff('', "line one\nline two");

        expect($ops)->toBe([
            ['type' => 'insert', 'lines' => ['line one', 'line two']],
        ]);
    });

    it('treats empty new content as a full delete', function () {
        $ops = $this->diff->diff("line one\nline two", '');

        expect($ops)->toBe([
            ['type' => 'delete', 'lines' => ['line one', 'line two']],
        ]);
    });

    it('detects insert delete and equal runs', function () {
        $old = "alpha\nbeta\ngamma";
        $new = "alpha\ndelta\ngamma";

        $ops = $this->diff->diff($old, $new);

        expect($ops)->toBe([
            ['type' => 'equal', 'lines' => ['alpha']],
            ['type' => 'delete', 'lines' => ['beta']],
            ['type' => 'insert', 'lines' => ['delta']],
            ['type' => 'equal', 'lines' => ['gamma']],
        ]);
    });

    it('merges adjacent operations of the same type', function () {
        $old = "keep\nremove one\nremove two\nkeep again";
        $new = "keep\ninsert one\ninsert two\nkeep again";

        $ops = $this->diff->diff($old, $new);

        expect($ops)->toBe([
            ['type' => 'equal', 'lines' => ['keep']],
            ['type' => 'delete', 'lines' => ['remove one', 'remove two']],
            ['type' => 'insert', 'lines' => ['insert one', 'insert two']],
            ['type' => 'equal', 'lines' => ['keep again']],
        ]);
    });

    it('collapses long equal runs into a marker', function () {
        $old = "same\nsame\nsame\nsame\nsame\nold tail";
        $new = "same\nsame\nsame\nsame\nsame\nnew tail";

        $ops = $this->diff->diff($old, $new, collapseThreshold: 4);

        expect($ops)->toBe([
            ['type' => 'collapsed', 'lines' => [], 'count' => 5],
            ['type' => 'delete', 'lines' => ['old tail']],
            ['type' => 'insert', 'lines' => ['new tail']],
        ]);
    });

    it('keeps short equal runs visible', function () {
        $old = "same\nsame\nsame\nold tail";
        $new = "same\nsame\nsame\nnew tail";

        $ops = $this->diff->diff($old, $new, collapseThreshold: 4);

        expect($ops)->toBe([
            ['type' => 'equal', 'lines' => ['same', 'same', 'same']],
            ['type' => 'delete', 'lines' => ['old tail']],
            ['type' => 'insert', 'lines' => ['new tail']],
        ]);
    });

    it('normalizes Windows line endings before diffing', function () {
        $old = "line one\r\nline two";
        $new = "line one\nline two";

        expect($this->diff->diff($old, $new))->toBe([]);
    });
});
