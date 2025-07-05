<?php

declare(strict_types=1);

namespace Hospiria\MultiUnit\Services\OpenApi\Generators;

use ReflectionClass;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Hospiria\MultiUnit\Http\Resources\BuildingResource;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\ParseResource;

class ResourceSchemaGenerator
{
    private array $resourceMap;
    private array $schemas;
    private array $processedResources = [];
    private SchemaTypeConverter $schemaTypeConverter;

    public function __construct()
    {
        $this->schemaTypeConverter = new SchemaTypeConverter(function (SchemaClass $resource) {
            $this->addResourceSchema($resource);
        });
    }

    /**
     * Generate OpenAPI schemas for a map of resources
     *
     * @param array $resourceMap Map of resources to generate schemas for
     * @return array The generated schemas
     */
    public function generate(array $resourceMap): array
    {
        $this->resourceMap = $resourceMap;
        $this->schemas = [];
        $this->processedResources = [];

        foreach ($this->resourceMap as $resource) {
            $this->addResourceSchema($resource);
        }

        return $this->schemas;
    }

    /**
     * Add a resource schema to the schemas array
     *
     * @param SchemaClass $resource The resource to add a schema for
     * @return void
     */
    private function addResourceSchema(SchemaClass $resource): void
    {
        // Prevent processing the same resource twice
        if (in_array($resource->getClass(), $this->processedResources)) {
            return;
        }
        
        $this->processedResources[] = $resource->getClass();
        $schema = $this->generateSchemaFromResource($resource->getClass());
        
        if (!empty($schema)) {
            $this->schemas[$resource->getName()] = $schema;
        }
    }

    /**
     * Generate a schema from a resource class
     *
     * @param string $resourceClass The resource class to generate a schema for
     * @return array The generated schema
     */
    private function generateSchemaFromResource(string $resourceClass): array
    {
        if (!class_exists($resourceClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($resourceClass);

            // Handle collections
            if (is_subclass_of($resourceClass, ResourceCollection::class)) {
                return $this->handleResourceCollection($reflection);
            }

            // Parse the resource using our enhanced parser
            $parseResource = new ParseResource();
            $returnType = $parseResource->parse($reflection, "toArray");
            
            // Convert the return type to an OpenAPI schema
            return $this->schemaTypeConverter->convertToOpenApiSchema($returnType);
            
        } catch (\Exception $e) {
            error_log("Error generating schema from resource {$resourceClass}: " . $e->getMessage());
        }

        return [];
    }
    
    /**
     * Handle a resource collection
     *
     * @param ReflectionClass $reflection The reflection of the collection class
     * @return array The generated schema
     */
    private function handleResourceCollection(ReflectionClass $reflection): array
    {
        $resourceType = $this->getCollectionResourceType($reflection);
        if ($resourceType) {
            $this->addResourceSchema($resourceType);
            return [
                'type' => 'array',
                'items' => [
                    '$ref' => "#/components/schemas/{$resourceType->getName()}"
                ]
            ];
        }
        
        // Default array schema if resource type not determined
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object'
            ]
        ];
    }

    /**
     * Get the resource type from a collection class
     *
     * @param ReflectionClass $reflection The collection class reflection
     * @return SchemaClass|null The resource class
     */
    private function getCollectionResourceType(ReflectionClass $reflection): ?SchemaClass
    {
        $className = $reflection->getShortName();
        if (str_ends_with($className, 'Collection')) {
            $resourceName = str_replace('Collection', 'Resource', $className);
            $resourceClass = $reflection->getNamespaceName() . '\\' . $resourceName;
            $resourceName = str_replace('Resource', '', $resourceName);
            if (class_exists($resourceClass)) {
                return new SchemaClass($resourceName, $resourceClass, false);
            }
        }
        return null;
    }
}
