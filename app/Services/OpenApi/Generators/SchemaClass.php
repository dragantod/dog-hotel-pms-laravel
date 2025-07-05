<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators;

class SchemaClass
{
  private string $name;
  private string $class;
  private bool $isPaginated;

  public function __construct(string $name, string $class, bool $isPaginated)
  {
    $this->name = $name;
    $this->class = $class;
    $this->isPaginated = $isPaginated;
  }

  public function getName(): string
  {
    if (substr($this->name, -8) === 'Resource') {
        return substr($this->name, 0, -8);
    }

    return $this->name;
  }

  public function getClass(): string
  {
    return $this->class;
  }

  public function isPaginated(): bool
  {
    return $this->isPaginated;
  }
}
