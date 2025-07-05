<?php

declare(strict_types=1);

namespace Hospiria\MultiUnit\Services\OpenApi\Generators;

use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\MultyType;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;

class SchemaTypeConverter
{
    public function __construct(
      private $addResourceSchemaCallback
    ) {}

    /**
     * Convert a return type to an OpenAPI schema
     *
     * @param mixed $returnType The return type to convert
     * @return array The OpenAPI schema
     */
    public function convertToOpenApiSchema($returnType): array
    {
        // Handle SchemaType objects
        if ($returnType instanceof SchemaType || $returnType instanceof MultyType) {
            return $this->schemaTypeToOpenApi($returnType);
        }
        
        // Handle arrays
        if (is_array($returnType)) {
            return $this->arrayToOpenApiSchema($returnType);
        }
        
        // Handle SchemaClass objects
        if ($returnType instanceof SchemaClass) {
            ($this->addResourceSchemaCallback)($returnType);
            return [
                '$ref' => "#/components/schemas/{$returnType->getName()}"
            ];
        }
        
        // Default to an empty object schema
        return [
            'type' => 'object',
            'properties' => []
        ];
    }

    /**
     * Convert a SchemaType or MultyType to an OpenAPI schema
     *
     * @param SchemaType|MultyType $schemaType The schema type to convert
     * @return array The OpenAPI schema
     */
    public function schemaTypeToOpenApi(SchemaType | MultyType $schemaType): array
    {
        if ($schemaType instanceof MultyType) {
            return [
                'anyOf' => array_map(fn($item) => $this->schemaTypeToOpenApi($item), $schemaType->getTypes())
            ];
        }

        return $this->convertPrimitiveSchemaTypeToOpenApi($schemaType);
    }

    /**
     * Convert a primitive SchemaType to an OpenAPI schema
     *
     * @param SchemaType $schemaType The schema type to convert
     * @return array The OpenAPI schema
     */
    public function convertPrimitiveSchemaTypeToOpenApi(SchemaType $schemaType): array
    {
        $type = $schemaType->getType();
        
        switch ($type) {
            case SchemaType::ARRAY:
                return $this->handleArraySchemaType($schemaType);
                
            case SchemaType::OBJECT:
                return $this->handleObjectSchemaType($schemaType);
                
            case SchemaType::STRING:
                return ['type' => 'string'];
                
            case SchemaType::INTEGER:
                return ['type' => 'integer'];
                
            case SchemaType::FLOAT:
                return ['type' => 'number', 'format' => 'float'];
                
            case SchemaType::BOOLEAN:
                return ['type' => 'boolean'];

            case SchemaType::ENUM:
                return ['type' => 'string', 'enum' => $schemaType->getEnums()];
                
            case SchemaType::ANY:
            default:
                return ['anyOf' => [
                    ['type' => 'string'],
                    ['type' => 'integer'],
                    ['type' => 'number'],
                    ['type' => 'boolean'],
                    ['type' => 'object'],
                    ['type' => 'array', 'items' => ['type' => 'object']],
                ]];
        }
    }
    
    /**
     * Handle an array schema type
     *
     * @param SchemaType $schemaType The array schema type
     * @return array The OpenAPI schema
     */
    public function handleArraySchemaType(SchemaType $schemaType): array
    {
        $schema = ['type' => 'array'];
        
        // If we have an item type, use it
        if ($schemaType->getItemType()) {
            $schema['items'] = $this->schemaTypeToOpenApi($schemaType->getItemType());
            return $schema;
        }
        
        // If we have items, infer the type from the first item
        $items = $schemaType->getItems();
        if (is_array($items) && count($items) > 0) {
            $firstItem = reset($items);
            if ($firstItem instanceof SchemaType) {
                $schema['items'] = $this->schemaTypeToOpenApi($firstItem);
            } elseif ($firstItem instanceof SchemaClass) {
                // We need to handle this case differently in external usage
                // Providing a hook for custom handling
                $schema['items'] = $this->handleSchemaClass($firstItem);
            } else {
                $schema['items'] = ['type' => 'object'];
            }
            return $schema;
        }
        
        // Default to object items
        $schema['items'] = ['type' => 'object'];
        return $schema;
    }
    
    /**
     * Handle an object schema type
     *
     * @param SchemaType $schemaType The object schema type
     * @return array The OpenAPI schema
     */
    public function handleObjectSchemaType(SchemaType $schemaType): array
    {
        // If it has a class name, reference that resource
        if ($schemaType->getClassName()) {
            $className = $schemaType->getClassName();
            
            // Get the short name for the reference
            $shortName = $this->getShortClassName($className);

            // Add the resource schema
            $resourceClass = new SchemaClass($shortName, $className, false);
            ($this->addResourceSchemaCallback)($resourceClass);
            
            // Provide a hook for handling references to other schemas
            return $this->handleClassReference($shortName, $className);
        }
        
        // If it has properties, convert them to an OpenAPI object schema
        $properties = $schemaType->getProperties();
        if (!empty($properties)) {
            $openApiProperties = [];
            $requiredProperties = [];
            
            foreach ($properties as $key => $value) {
                if ($value instanceof SchemaType && $value->isRequired()) {
                    $requiredProperties[] = $key;
                }
                $openApiProperties[$key] = $this->convertToOpenApi($value);
            }
            
            return [
                'type' => 'object',
                'required' => $requiredProperties,
                'properties' => $openApiProperties
            ];
        }
        
        // Default empty object schema
        return [
            'type' => 'object',
            'properties' => []
        ];
    }
    
    /**
     * Convert any type to an OpenAPI schema
     *
     * @param mixed $returnType The return type to convert
     * @return array The OpenAPI schema
     */
    public function convertToOpenApi($returnType): array
    {
        // Handle SchemaType objects
        if ($returnType instanceof SchemaType || $returnType instanceof MultyType) {
            return $this->schemaTypeToOpenApi($returnType);
        }
        
        // Handle arrays
        if (is_array($returnType)) {
            return $this->arrayToOpenApiSchema($returnType);
        }
        
        // Handle SchemaClass objects
        if ($returnType instanceof SchemaClass) {
            return $this->handleSchemaClass($returnType);
        }
        
        // Default to an empty object schema
        return [
            'type' => 'object',
            'properties' => []
        ];
    }
    
    /**
     * Convert an array to an OpenAPI schema
     *
     * @param array $array The array to convert
     * @return array The OpenAPI schema
     */
    public function arrayToOpenApiSchema(array $array): array
    {
        $properties = [];
        $isAssociative = false;
        
        // Check if this is an associative array
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $isAssociative = true;
                break;
            }
        }
        
        // For associative arrays, convert to an object schema
        if ($isAssociative) {
            foreach ($array as $key => $value) {
                $properties[$key] = $this->convertToOpenApi($value);
            }
            
            return [
                'type' => 'object',
                'properties' => $properties
            ];
        }
        
        // For sequential arrays, convert to an array schema
        // Try to determine the item type from the first element
        if (count($array) > 0) {
            $firstItem = reset($array);
            return [
                'type' => 'array',
                'items' => $this->convertToOpenApi($firstItem)
            ];
        }
        
        // Empty array - default to object items
        return [
            'type' => 'array',
            'items' => ['type' => 'object']
        ];
    }
    
    /**
     * Get the short class name from a fully qualified class name
     *
     * @param string $className The fully qualified class name
     * @return string The short class name
     */
    public function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = end($parts);
        
        // Remove 'Resource' suffix for cleaner names
        if (str_ends_with($shortName, 'Resource')) {
            $shortName = substr($shortName, 0, -8);
        }
        
        return $shortName;
    }
    
    /**
     * Handle a SchemaClass object - to be overridden in subclasses if needed
     *
     * @param SchemaClass $schemaClass The schema class to handle
     * @return array The OpenAPI schema
     */
    protected function handleSchemaClass(SchemaClass $schemaClass): array
    {
        // Default implementation returns a reference
        return [
            '$ref' => "#/components/schemas/{$schemaClass->getName()}"
        ];
    }
    
    /**
     * Handle a class reference - to be overridden in subclasses if needed
     *
     * @param string $shortName The short class name
     * @param string $className The fully qualified class name
     * @return array The OpenAPI schema
     */
    protected function handleClassReference(string $shortName, string $className): array
    {
        // Default implementation returns a reference
        return [
            '$ref' => "#/components/schemas/{$shortName}"
        ];
    }
} 