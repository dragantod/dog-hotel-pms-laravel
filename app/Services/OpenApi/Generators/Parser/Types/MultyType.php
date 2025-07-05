<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types;

class MultyType
{
  public function __construct(private array $types)
  {
  }

  public function getTypes(): array
  {
    return $this->types;
  }
}