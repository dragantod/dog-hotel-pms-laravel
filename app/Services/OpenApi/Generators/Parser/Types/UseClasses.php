<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types;

class UseClasses
{
  public function __construct(private string $className, private ?string $as = null)
  {
  }

  public function getClassName(): string
  {
    return $this->className;
  }

  public function getAs(): ?string
  {
    return $this->as;
  }
}
