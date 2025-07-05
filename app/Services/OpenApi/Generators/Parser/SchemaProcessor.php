<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;

/**
 * Processes SchemaType objects to ensure they're properly formed
 */
class SchemaProcessor
{
  /**
   * Class resolver for handling class name resolution
   */
  private ClassResolver $classResolver;

  /**
   * Type parser for parsing property values
   */
  private ?TypeParser $typeParser = null;

  /**
   * Constructor
   *
   * @param ClassResolver $classResolver The class resolver to use
   */
  public function __construct(ClassResolver $classResolver)
  {
    $this->classResolver = $classResolver;
  }

  /**
   * Set the type parser
   * 
   * @param TypeParser $typeParser The type parser to use
   * @return void
   */
  public function setTypeParser(TypeParser $typeParser): void
  {
    $this->typeParser = $typeParser;
  }

  /**
   * Process a SchemaType to ensure it's properly formed
   */
  public function processSchemaType(SchemaType $schemaType): SchemaType
  {
    // Process array type schemas
    if ($schemaType->getType() === SchemaType::ARRAY) {
      $this->processArrayType($schemaType);
    }
    
    // Process object type schemas with class names
    if ($schemaType->getType() === SchemaType::OBJECT && $schemaType->getClassName()) {
      $className = $schemaType->getClassName();
      // Resolve the class name using use statements
      $resolvedClassName = $this->classResolver->resolveClassName($className);
      $schemaType->setClassName($resolvedClassName);
    }

    // Process object type schemas with properties
    if ($schemaType->getType() === SchemaType::OBJECT && $schemaType->getProperties()) {
      $this->processObjectProperties($schemaType);
    }

    return $schemaType;
  }

  /**
   * Process an array type schema
   */
  private function processArrayType(SchemaType $schemaType): void
  {
    // If we have items but no itemType, try to infer the item type
    if ($schemaType->getItems() && !$schemaType->getItemType()) {
      $items = $schemaType->getItems();
      if (is_array($items) && count($items) > 0) {
        // Use the first item to determine type
        $firstItem = reset($items);
        if ($firstItem instanceof SchemaType) {
          $schemaType->setItemType($firstItem);
        } else {
          // Create a generic item type
          $itemType = new SchemaType(SchemaType::OBJECT);
          $schemaType->setItemType($itemType);
        }
      }
    }

    if ($schemaType->getItemType() !== null) {
      // Process item type if it's an object with className
      $itemType = $schemaType->getItemType();
      if ($itemType instanceof SchemaType && 
          $itemType->getType() === SchemaType::OBJECT && 
          $itemType->getClassName()) {
        // Resolve the class name for the item type
        $className = $itemType->getClassName();
        $resolvedClassName = $this->classResolver->resolveClassName($className);
        $itemType->setClassName($resolvedClassName);
      }
    }
  }

  /**
   * Process object properties in a schema
   */
  private function processObjectProperties(SchemaType $schemaType): void
  {
    $properties = [];
    foreach ($schemaType->getProperties() as $key => $value) {
      // Recursively process each property value
      if ($value instanceof SchemaType) {
        $properties[$key] = $this->processSchemaType($value);
      } else {
        // Use TypeParser to handle array or PropertyFetch values
        if ($this->typeParser !== null) {
          $properties[$key] = $this->typeParser->parseReturnValueType($value);
        } else {
          // Fallback if TypeParser is not set
          $properties[$key] = $value;
        }
      }
    }
    $schemaType->setProperties($properties);
  }

  /**
   * Handle a collection class
   *
   * @param string $className The collection class name
   * @return SchemaType A schema type representing the collection
   */
  public function handleCollection(string $className): SchemaType
  {
    $collectionType = new SchemaType(SchemaType::ARRAY);
    
    try {
      // Try to determine the resource type from the collection
      $reflection = new \ReflectionClass($className);
      $methodNames = ['collects', 'getCollects'];
      
      foreach ($methodNames as $methodName) {
        if ($reflection->hasMethod($methodName)) {
          $method = $reflection->getMethod($methodName);
          $method->setAccessible(true);
          
          if (!$method->isStatic()) {
            // Need an instance to call the method
            $instance = $reflection->newInstanceWithoutConstructor();
            $resourceClass = $method->invoke($instance);
          } else {
            $resourceClass = $method->invoke(null);
          }
          
          if ($resourceClass && is_string($resourceClass)) {
            // Create a SchemaType object instead of SchemaClass
            $itemType = new SchemaType(SchemaType::OBJECT);
            $itemType->setClassName($resourceClass);
            
            $collectionType->setItemType($itemType);
            break;
          }
        }
      }
    } catch (\Exception $e) {
      error_log("Failed to determine collection type for $className: " . $e->getMessage());
    }
    
    // If we couldn't determine the item type, use a generic object
    if (!$collectionType->getItemType()) {
      $itemType = new SchemaType(SchemaType::OBJECT);
      $collectionType->setItemType($itemType);
    }
    
    return $collectionType;
  }

  /**
   * Check if a class is a collection
   *
   * @param string $className The class name to check
   * @return bool Whether the class is a collection
   */
  public function isCollection(string $className): bool
  {
    return is_subclass_of($className, ResourceCollection::class);
  }
} 