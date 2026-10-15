<?php

namespace App\Devnull\Data;

/**
 * A block of a commit body: a paragraph of runs, or a fenced code block.
 */
final readonly class BodyBlock
{
    /**
     * @param  list<BodyRun>  $runs
     */
    private function __construct(public array $runs = [], public ?string $code = null, public ?string $language = null) {}

    /**
     * @param  list<BodyRun>  $runs
     */
    public static function paragraph(array $runs): self
    {
        return new self(runs: $runs);
    }

    public static function code(string $code, ?string $language = null): self
    {
        return new self(code: $code, language: $language);
    }

    public function isCode(): bool
    {
        return $this->code !== null;
    }
}
