<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use PhpParser\Node;
use Hospiria\MultiUnit\Services\Log;
use Hospiria\MultiUnit\Services\OpenApi\Generators\SchemaClass;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\MultyType;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;

/**
 * Parses PHP AST nodes into schema types
 */
class TypeParser
{
  /**
   * Class resolver for handling class name resolution
   */
  private ClassResolver $classResolver;

  /**
   * Schema processor for processing schema types
   */
  private SchemaProcessor $schemaProcessor;

  /**
   * Constructor
   *
   * @param ClassResolver $classResolver The class resolver to use
   * @param SchemaProcessor $schemaProcessor The schema processor to use
   */
  public function __construct(ClassResolver $classResolver, SchemaProcessor $schemaProcessor)
  {
    $this->classResolver = $classResolver;
    $this->schemaProcessor = $schemaProcessor;
    
    // Set up circular reference for handling complex nested structures
    $this->schemaProcessor->setTypeParser($this);
  }

  /**
   * Parse a return value into a structured type for OpenAPI schema generation
   * 
   * @param mixed $returnValue The return value to parse
   * @return mixed The structured type
   */
  public function parseReturnValueType(mixed $returnValue)
  {
    // If it's already a SchemaType, just process it
    if ($returnValue instanceof SchemaType) {
      return $this->schemaProcessor->processSchemaType($returnValue);
    }
    
    // Handle standard arrays
    if (is_array($returnValue)) {
      return array_map([$this, 'parseReturnValueType'], $returnValue);
    }
    
    // Handle PHP-Parser node types
    if ($returnValue instanceof Node\Expr\Array_) {
      return $this->parseArrayNode($returnValue);
    }
    
    // Handle property access
    if ($returnValue instanceof Node\Expr\PropertyFetch) {
      return $this->getPropertyType($returnValue->name->name);
    }
    
    // Handle static calls (like collection methods)
    if ($returnValue instanceof Node\Expr\StaticCall) {
      return $this->parseStaticCall($returnValue);
    }
    
    // Handle object instantiation
    if ($returnValue instanceof Node\Expr\New_) {
      return $this->parseClass($returnValue->class);
    }
    
    // Handle scalar values
    if ($returnValue instanceof Node\Scalar\String_) {
      return new SchemaType(SchemaType::STRING);
    }
    
    if ($returnValue instanceof Node\Scalar\Int_) {
      return new SchemaType(SchemaType::INTEGER);
    }
    
    if ($returnValue instanceof Node\Scalar\Float_) {
      return new SchemaType(SchemaType::FLOAT);
    }
    
    // Handle boolean constants
    if ($returnValue instanceof Node\Expr\ConstFetch) {
      $constName = strtolower($returnValue->name->toString());
      if ($constName === 'true' || $constName === 'false') {
        return new SchemaType(SchemaType::BOOLEAN);
      }
    }

    if ($returnValue instanceof MultyType) {
      return $returnValue;
    }
    
    // Default fallback for unknown types
    return new SchemaType($returnValue);
  }

  /**
   * Parse an array node
   *
   * @param Node\Expr\Array_ $arrayNode The array node to parse
   * @return array The parsed array
   */
  private function parseArrayNode(Node\Expr\Array_ $arrayNode): array
  {
    $result = [];
    foreach ($arrayNode->items as $item) {
      if ($item !== null) {
        $key = $item->key ? $this->getArrayKeyValue($item->key) : null;
        $value = $this->parseReturnValueType($item->value);
        
        if ($key !== null) {
          $result[$key] = $value;
        } else {
          $result[] = $value;
        }
      }
    }
    return $result;
  }

  /**
   * Parse a static call node
   *
   * @param Node\Expr\StaticCall $staticCall The static call node to parse
   * @return mixed The parsed type
   */
  private function parseStaticCall(Node\Expr\StaticCall $staticCall)
  {
    $isCollection = $staticCall->name->name === 'collection';
    $class = $this->parseClass($staticCall->class, $isCollection);
    
    if ($isCollection && is_array($class) && count($class) > 0) {
      // For collections, create an array schema of the resource type
      $schemaType = new SchemaType(SchemaType::ARRAY);
      $itemType = new SchemaType(SchemaType::OBJECT);
      if ($class[0] instanceof SchemaClass) {
        $itemType->setClassName($class[0]->getClass());
      }
      $schemaType->setItemType($itemType);
      return $schemaType;
    }
    
    return $class;
  }
  
  /**
   * Get the value of an array key node
   */
  private function getArrayKeyValue(Node $key): ?string
  {
    if ($key instanceof Node\Scalar\String_) {
      return $key->value;
    }
    
    if ($key instanceof Node\Scalar\Int_) {
      return (string)$key->value;
    }
    
    return null;
  }
  
  /**
   * Parse a class node into a SchemaClass
   * 
   * @param Node\Name $class The class node
   * @param bool $isCollection Whether the class is part of a collection
   * @return mixed SchemaClass or array of SchemaClass
   */
  private function parseClass(Node\Name $class, bool $isCollection = false)
  {
    $className = $class->toString();
    $resolvedClassName = $this->classResolver->resolveClassName($className);
    
    // Skip invalid class names
    if (empty($resolvedClassName) || in_array(strtolower($className), ['parent', 'self', 'static'])) {
      $schemaType = new SchemaType(SchemaType::OBJECT);
      return $schemaType;
    }
    
    try {
      // Handle collection classes
      if ($this->schemaProcessor->isCollection($resolvedClassName) || $isCollection) {
        return $this->schemaProcessor->handleCollection($resolvedClassName);
      }
      
      // Create a schema class for the resource
      $shortName = basename(str_replace('\\', '/', $resolvedClassName));
      return new SchemaClass($shortName, $resolvedClassName, false);
    } catch (\ReflectionException $e) {
      // Log the issue and return a generic object type
      error_log("Failed to parse class $resolvedClassName: " . $e->getMessage());
      $schemaType = new SchemaType(SchemaType::OBJECT);
      return $schemaType;
    }
  }

  /**
   * Get the type of a property from the reflection
   * 
   * @param string $key The property name
   * @return SchemaType The property type
   */
  private function getPropertyType(string $key): SchemaType
  {
    $reflection = $this->classResolver->getReflection();
    if ($reflection->hasProperty($key)) {
      $property = $reflection->getProperty($key);

      $propertyDoc = $property->getDocComment();

      if ($propertyDoc) {
        $varDoc = $this->parseVarDoc($propertyDoc);
        
        // If we successfully parsed a type from the doc comment, return it
        if (!empty($varDoc) && isset($varDoc['type'])) {
          if ($varDoc['type'] === 'enum' && isset($varDoc['values'])) {
            $schemaType = new SchemaType(SchemaType::ENUM, true);
            $schemaType->setEnums($varDoc['values']);
            return $schemaType;
          } elseif ($varDoc['type'] === 'any' || $varDoc['type'] === 'mixed') {
            return new SchemaType(SchemaType::ANY);
          } else {
            return new SchemaType($varDoc['type'], $varDoc['required'] ?? false);
          }
        }
      }
      
      if ($property->hasType()) {
        $type = $property->getType();
        if ($type instanceof \ReflectionNamedType) {
          $name = $type->getName();
          $isRequired = !$type->allowsNull();
          if (enum_exists($name)) {
            $schemaType = new SchemaType(SchemaType::ENUM, $isRequired);
            $schemaType->setEnums($this->getEnumValues($name));
            return $schemaType;
          }

          if (class_exists($name)) {
            $schemaType = new SchemaType(SchemaType::OBJECT, $isRequired);
            $schemaType->setClassName($name);
            return $schemaType;
          }

          return new SchemaType($type->getName(), $isRequired);
        }
      }
    }
    
    // Default to ANY type if property not found or type not determined
    return new SchemaType(SchemaType::ANY);
  }

  /**
   * Parse the var doc comment to extract type information
   * 
   * @param string $doc The doc comment
   * @return array The extracted type information
   */
  private function parseVarDoc(string $doc): array
  {
    $result = [];
    
    // Extract the @var line, accommodating one-liners (/** @var string */)
    if (preg_match('/@var\s+([^*\r\n]+)(?:\s*\*\/)?/', $doc, $matches)) {
      $varType = trim($matches[1]);
      
      // Handle mixed type or any
      if ($varType === 'mixed') {
        $result['type'] = 'any';
        return $result;
      }
      
      // Handle enum-like format with pipe-separated string literals
      if (preg_match('/^"[^"]+"\s*(\|\s*"[^"]+")+$/', $varType)) {
        $values = [];
        preg_match_all('/"([^"]+)"/', $varType, $matches);
        if (isset($matches[1]) && !empty($matches[1])) {
          $values = $matches[1];
        }
        
        $result['type'] = 'enum';
        $result['values'] = $values;
        return $result;
      }
      
      // Handle array type
      if (substr($varType, -2) === '[]' || strpos($varType, 'array<') === 0) {
        $result['type'] = 'array';
        // Could extract item type here for future enhancement
        return $result;
      }
      
      // Handle nullable types
      $isNullable = false;
      if (strpos($varType, '?') === 0) {
        $isNullable = true;
        $varType = substr($varType, 1);
      } elseif (strpos($varType, 'null|') === 0) {
        $isNullable = true;
        $varType = substr($varType, 5);
      } elseif (strpos($varType, '|null') !== false) {
        $isNullable = true;
        $varType = str_replace('|null', '', $varType);
      }
      
      // Basic mapping of common PHP types
      $typeMap = [
        'string' => 'string',
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
      ];
      
      if (isset($typeMap[$varType])) {
        $result['type'] = $typeMap[$varType];
      } else {
        // For classes/custom types
        $resolvedClassName = $this->classResolver->resolveClassName($varType);

        // Check if it's an enum class (PHP 8.1+)
        if (class_exists($resolvedClassName) && enum_exists($resolvedClassName)) {
          $result['type'] = 'enum';
          $values = $this->getEnumValues($resolvedClassName);
          $result['values'] = $values;
        } else {
          // For non-enum classes/custom types
          $result['type'] = $varType;
        }
      }
      
      $result['required'] = !$isNullable;
    }
    
    return $result;
  }

  private function getEnumValues(mixed $enum)
  {
    $values = [];

    // Get all cases of the enum
    foreach ($enum::cases() as $case) {
      // For string backed enums, use the value; otherwise use the name
      if ($case->value !== null) {
        $values[] = $case->value;
        continue;
      }

      $values[] = $case->name;
    }

    return $values;
  }
}