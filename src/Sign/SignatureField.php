<?php

namespace Jeidison\JSignPDF\Sign;

final class SignatureField
{
    public function __construct(
        private string $name,
        private int $page,
        private float $llx,
        private float $lly,
        private float $urx,
        private float $ury,
        private bool $signed,
        private bool $hidden,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLlx(): float
    {
        return $this->llx;
    }

    public function getLly(): float
    {
        return $this->lly;
    }

    public function getUrx(): float
    {
        return $this->urx;
    }

    public function getUry(): float
    {
        return $this->ury;
    }

    public function isSigned(): bool
    {
        return $this->signed;
    }

    public function isBlank(): bool
    {
        return !$this->signed;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function hasVisibleRectangle(): bool
    {
        return $this->urx > $this->llx
            && $this->ury > $this->lly;
    }
}
