<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types;

use PhpParser\Node;

class SchemaType
{
  private string $type;
  private bool $required;
  private mixed $children;
  private array $properties = [];
  private mixed $items = null;
  private ?SchemaType $itemType = null;
  private ?string $className = null;
  private ?array $enums = [];

  public const ANY = 'any';
  public const STRING = 'string';
  public const INTEGER = 'int';
  public const FLOAT = 'float';
  public const BOOLEAN = 'bool';
  public const ARRAY = 'array';
  public const OBJECT = 'object';
  public const MULTY = 'multy';
  public const ENUM = 'enum';
  public const NULL = 'null';

  public const TYPE_MAP = [
    Node\Scalar\String_::class => self::STRING,
    Node\Scalar\Int_::class => self::INTEGER,
    Node\Scalar\Float_::class => self::FLOAT,
  ];

  public const TYPES = [
    self::ANY,
    self::STRING,
    self::INTEGER,
    self::FLOAT,
    self::BOOLEAN,
    self::ARRAY,
    self::OBJECT,
    self::ENUM,
  ];

  public function __construct(mixed $rawType, bool $required = false)
  {
    $this->required = $required;

    if (in_array($rawType, self::TYPES)) {
      $this->type = $rawType;
      return;
    }

    foreach (self::TYPE_MAP as $class => $type) {
      if ($rawType instanceof $class) {
        $this->type = $type;
        return;
      }
    }

    $this->type = self::ANY;
  }

  public function setEnums(array $enums): void
  {
    $this->enums = $enums;
  }
  
  public function getEnums(): array
  {
    return $this->enums;
  }

  public function getType(): string
  { 
    return $this->type;
  }

  public function isRequired(): bool
  {
    return $this->required;
  }

  public function isAny(): bool
  {
    return $this->type === self::ANY;
  }

  public function setRequired(bool $required): void
  {
    $this->required = $required;
  }

  public function setChildren(mixed $children): void
  {
    $this->children = $children;
  }

  public function getChildren(): mixed
  {
    return $this->children;
  }
  
  /**
   * Set properties for object types
   * 
   * @param array $properties The object properties
   * @return void
   */
  public function setProperties(array $properties): void
  {
    $this->properties = $properties;
  }
  
  /**
   * Get the object properties
   * 
   * @return array The object properties
   */
  public function getProperties(): array
  {
    return $this->properties;
  }
  
  /**
   * Set items for array types
   * 
   * @param mixed $items The array items
   * @return void
   */
  public function setItems(mixed $items): void
  {
    $this->items = $items;
  }
  
  /**
   * Get array items
   * 
   * @return mixed The array items
   */
  public function getItems(): mixed
  {
    return $this->items;
  }
  
  /**
   * Set the type of items in an array
   * 
   * @param SchemaType $itemType The type of items in the array
   * @return void
   */
  public function setItemType(SchemaType $itemType): void
  {
    $this->itemType = $itemType;
  }
  
  /**
   * Get the type of items in an array
   * 
   * @return SchemaType|null The type of items in the array
   */
  public function getItemType(): ?SchemaType
  {
    return $this->itemType;
  }
  
  /**
   * Set the class name for object types
   * 
   * @param string $className The class name
   * @return void
   */
  public function setClassName(string $className): void
  {
    $this->className = $className;
  }
  
  /**
   * Get the class name for object types
   * 
   * @return string|null The class name
   */
  public function getClassName(): ?string
  {
    return $this->className;
  }
}
