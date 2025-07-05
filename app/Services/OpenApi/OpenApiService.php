<?php

namespace Hospiria\MultiUnit\Services\OpenApi;

use Symfony\Component\Yaml\Yaml;
use Hospiria\MultiUnit\Services\OpenApi\Generators\RequestSchemaGenerator;
use Hospiria\MultiUnit\Services\OpenApi\Generators\ResourceSchemaGenerator;
use Hospiria\MultiUnit\Services\OpenApi\Generators\PathGenerator;

class OpenApiService
{
    private array $specification;
    private array $resourceMap;

    public function __construct(
        private readonly RequestSchemaGenerator $requestSchemaGenerator,
        private readonly ResourceSchemaGenerator $resourceSchemaGenerator,
        private readonly PathGenerator $pathGenerator
    ) {
        $this->initializeSpecification();
    }

    public function generate(): array
    {
        // Generate paths
        $generated = $this->pathGenerator->generate();
        $this->resourceMap = $generated['resourceMap'];
        $this->specification['paths'] = $generated['paths'];

        // Generate schemas
        $this->generateSchemas();

        return $this->specification;
    }

    public function save(string $outputPath): void
    {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            // Create a serializable version of the specification
            $serializable = $this->makeSerializable($this->specification);
            
            // Convert to YAML
            $yaml = Yaml::dump($serializable, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            file_put_contents($outputPath, $yaml);
            
        } catch (\Exception $e) {
            error_log("Failed to save OpenAPI spec as YAML: " . $e->getMessage());
            error_log("Attempting to save as JSON instead");
            
            try {
                // Try to save as JSON with the serializable version
                $jsonPath = str_replace('.yaml', '.json', $outputPath);
                $jsonData = json_encode($serializable ?? [], JSON_PRETTY_PRINT);
                if ($jsonData === false) {
                    throw new \Exception("Failed to JSON encode for fallback: " . json_last_error_msg());
                }
                file_put_contents($jsonPath, $jsonData);
                error_log("Saved OpenAPI specification as JSON to: $jsonPath");
                echo "Saved OpenAPI specification as JSON to: $jsonPath";
            } catch (\Exception $jsonException) {
                error_log("Failed to save as JSON: " . $jsonException->getMessage());
                // Create an empty JSON object as a last resort
                file_put_contents($jsonPath, "{}");
                error_log("Created empty JSON as fallback at: $jsonPath");
            }
        }
    }
    
    /**
     * Convert the specification to a serializable array by breaking circular references
     * and handling special cases like empty objects and null values
     *
     * @param mixed $data The data to make serializable
     * @param int $maxDepth Maximum recursion depth to avoid issues
     * @param array $processedNodes Track processed nodes to detect circularity
     * @return mixed The serializable version of the data
     */
    private function makeSerializable($data, int $maxDepth = 15, array &$processedNodes = []): mixed
    {
        // Base case: if we've gone too deep, return a simple value
        if ($maxDepth <= 0) {
            if (is_array($data)) {
                return ['type' => 'object', 'description' => 'Maximum depth reached'];
            }
            return $data;
        }
        
        // Handle scalar values directly
        if (is_scalar($data) || $data === null) {
            return $data;
        }
        
        // Handle objects by converting to arrays
        if (is_object($data)) {
            // Detect circular references using object hash
            $hash = spl_object_hash($data);
            if (in_array($hash, $processedNodes)) {
                return ['type' => 'object', 'description' => 'Circular reference detected'];
            }
            
            // Add to processed nodes
            $processedNodes[] = $hash;
            
            // Convert to array and process recursively
            $array = (array) $data;
            $result = $this->makeSerializable($array, $maxDepth - 1, $processedNodes);
            
            return $result;
        }
        
        // Handle arrays
        if (is_array($data)) {
            $result = [];
            
            // Special case: empty array stays empty
            if (empty($data)) {
                return [];
            }
            
            // Detect if this is a schema object (has type:object and properties)
            $isSchemaObject = isset($data['type']) && $data['type'] === 'object' && isset($data['properties']);
            
            // Process each element
            foreach ($data as $key => $value) {
                // Skip internal references that might cause issues
                if ($key === '__processed' || $key === '__circular') {
                    continue;
                }
                
                // Handle specific schema reference keys specially
                if ($key === '$ref' && is_string($value)) {
                    $result[$key] = $value;
                    continue;
                }
                
                // Handle special keys that should be arrays - but be careful with 'required'
                if (in_array($key, ['parameters', 'security', 'schemas'])) {
                    if (empty($value) || !is_array($value)) {
                        $result[$key] = [];
                        continue;
                    }
                }
                
                // Special handling for properties in schemas
                if ($key === 'properties') {
                    // If it's not an array or is empty, set it as an empty array
                    if (!is_array($value) || empty($value)) {
                        $result[$key] = [];
                        continue;
                    }
                    
                    // Process each property - preserve their structure 
                    $properties = [];
                    foreach ($value as $propName => $propValue) {
                        // Skip if property value is not valid
                        if ($propValue === null) {
                            continue;
                        }
                        
                        // Handle property that is just a reference
                        if (isset($propValue['$ref'])) {
                            $properties[$propName] = ['$ref' => $propValue['$ref']];
                            continue;
                        }
                        
                        // Handle property with a type
                        if (isset($propValue['type'])) {
                            $propResult = ['type' => $propValue['type']];
                            
                            // Copy other important property attributes
                            $attributesToCopy = [
                                'format', 'description', 'example', 'enum', 'default',
                                'minimum', 'maximum', 'nullable', 'readOnly', 'writeOnly',
                                'items', 'properties', 'required'
                            ];
                            
                            foreach ($attributesToCopy as $attr) {
                                if (isset($propValue[$attr])) {
                                    // Need to recursively process items and properties
                                    if (in_array($attr, ['items', 'properties'])) {
                                        $propResult[$attr] = $this->makeSerializable(
                                            $propValue[$attr], 
                                            $maxDepth - 1, 
                                            $processedNodes
                                        );
                                    } else {
                                        $propResult[$attr] = $propValue[$attr];
                                    }
                                }
                            }
                            
                            $properties[$propName] = $propResult;
                            continue;
                        }
                        
                        // Otherwise, recursively process the property
                        $properties[$propName] = $this->makeSerializable(
                            $propValue, 
                            $maxDepth - 1, 
                            $processedNodes
                        );
                    }
                    
                    $result[$key] = $properties;
                    continue;
                }
                
                // Special handling for 'required' - could be boolean or array depending on context
                if ($key === 'required') {
                    // If it's a boolean, keep it as is (for property/parameter level requirements)
                    if (is_bool($value)) {
                        $result[$key] = $value;
                        continue;
                    }
                    
                    // If it's supposed to be an array of required properties at schema level
                    if (empty($value) || !is_array($value)) {
                        // Check if we're in a schema context by looking for sibling keys
                        $inSchemaContext = isset($data['properties']) || isset($data['type']);
                        $result[$key] = $inSchemaContext ? [] : $value;
                        continue;
                    }
                }
                
                // Process recursively
                $result[$key] = $this->makeSerializable($value, $maxDepth - 1, $processedNodes);
            }
            
            return $result;
        }
        
        // Fallback for any other types
        return null;
    }

    private function initializeSpecification(): void
    {
        $this->specification = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Hospiria Multi-Unit API',
                'version' => '1.0.0',
                'description' => 'API for managing multi-unit properties in Hospiria'
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'JWT token obtained from authentication'
                    ]
                ],
                'responses' => [
                    'Unauthorized' => [
                        'description' => 'Unauthorized - Invalid or missing authentication token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Unauthenticated.'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Forbidden' => [
                        'description' => 'Forbidden - Insufficient permissions',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'This action is unauthorized.'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'NotFound' => [
                        'description' => 'Not found - The specified resource was not found',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'The requested resource was not found.'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'ValidationError' => [
                        'description' => 'Validation error - The request data is invalid',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'The given data was invalid.'
                                        ],
                                        'errors' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'string'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'security' => [
                [
                    'bearerAuth' => []
                ]
            ]
        ];
    }

    private function generateSchemas(): void
    {

        // Generate request schemas
        $requestSchemaResult = $this->requestSchemaGenerator->generate();
        $requestSchemas = $requestSchemaResult['schemas'] ?? [];
        
        $this->specification['components']['schemas'] = array_merge(
            $this->specification['components']['schemas'],
            $requestSchemas
        );

        // Generate resource schemas
        $resourceSchemas = $this->resourceSchemaGenerator->generate($this->resourceMap);
        $this->specification['components']['schemas'] = array_merge(
            $this->specification['components']['schemas'],
            $resourceSchemas
        );

        // Process nested schemas and references
        $this->processNestedSchemas($this->specification['components']['schemas']);
    }

    private function processNestedSchemas(array &$schemas): void
    {
        foreach ($schemas as $name => &$schema) {
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as &$property) {
                    if (isset($property['$ref'])) {
                        // Extract the referenced schema name
                        $refName = basename($property['$ref']);
                        if (isset($schemas[$refName])) {
                            // Merge the referenced schema properties
                            $property = array_merge($property, $schemas[$refName]);
                        }
                    }
                    
                    // Handle nested arrays with object items
                    if (isset($property['type']) && $property['type'] === 'array' && isset($property['items']['$ref'])) {
                        $refName = basename($property['items']['$ref']);
                    }
                }
            }
        }
    }

    private function convertEmptyObjectsToArrays(&$data, array &$processedReferences = []): void
    {
        // Just a compatibility stub for old code
        // The functionality is now part of makeSerializable
    }

    private function breakCircularReferences(&$data): void
    {
        // Just a compatibility stub for old code
        // The functionality is now part of makeSerializable
    }

    private function breakCircularReferencesHelper(&$data, array &$processedReferences): void
    {
        // Just a compatibility stub for old code
        // The functionality is now part of makeSerializable
    }

    /**
     * Replace any null values with appropriate empty structures
     * 
     * @param mixed $data The data to process
     * @param string|null $key The current key in the parent array (if any)
     * @param array|null $parent The parent array (if any)
     */
    private function cleanupNullValues(&$data, ?string $key = null, ?array &$parent = null): void
    {
        // Just a compatibility stub for old code
        // The functionality is now part of makeSerializable
    }
    
    /**
     * For backwards compatibility - redirects to the new method
     */
    private function cleanupProblematicValues(array &$data): void
    {
        // Just a compatibility stub for old code
        // The functionality is now part of makeSerializable
    }
} 