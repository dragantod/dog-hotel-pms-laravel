<?php

declare(strict_types=1);

namespace Hospiria\MultiUnit\Services\OpenApi\Generators;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\In;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;

class RequestSchemaGenerator
{
    private array $requestMap = [];

    public function generate(): array
    {
        $schemas = [];
        $this->requestMap = [];

        // Create a new router instance to avoid interference with cached routes
        $router = app('router');
        
        // Load our package routes
        $router->group(['prefix' => ''], function ($router) {
            require __DIR__ . '/../../../../routes/api.php';
        });
        
        // Get all routes
        $routes = $router->getRoutes();

        foreach ($routes as $route) {
            if (!$this->shouldIncludeRoute($route)) {
                continue;
            }

            $this->processRouteRequests($route);
        }

        // Generate schemas for all found requests
        foreach ($this->requestMap as $requestName => $requestClass) {
            $schema = $this->generateSchemaFromRequest($requestClass);
            if (!empty($schema)) {
                $schemas[$requestName] = $schema;
            }
        }

        return [
            'schemas' => $schemas,
            'requestMap' => $this->requestMap
        ];
    }

    private function shouldIncludeRoute(RoutingRoute $route): bool
    {
        // Only include API routes from our package
        return str_starts_with($route->uri(), 'v1/') && 
               str_contains($route->getActionName(), 'Hospiria\\MultiUnit');
    }

    private function processRouteRequests(RoutingRoute $route): void
    {
        $controller = $route->getController();
        if (!$controller) {
            return;
        }

        $action = $route->getActionMethod();
        $parameters = (new ReflectionMethod($controller, $action))->getParameters();

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string)$type;
                if (is_subclass_of($typeName, FormRequest::class)) {
                    // Get the request class name without namespace
                    $requestName = class_basename($typeName);
                    
                    // Store in the requestMap
                    $this->requestMap[$requestName] = $typeName;
                }
            }
        }
    }

    public function generateSchemaFromRequest(string $requestClass): array
    {
        try {
            /** @var FormRequest $request */
            $request = new $requestClass();
            
            if (!method_exists($request, 'rules')) {
                return ['type' => 'object', 'properties' => []];
            }

            // Skip validation by overriding the authorize method
            $request->setContainer(app());
            $request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'GET']);
            $request->setMethod('GET');

            // Initialize request with mock route parameters
            $this->initializeRequest($request);

            $rules = $request->rules();
            return $this->generateSchemaFromRules($rules);
        } catch (\Throwable $e) {
            Log::error("Error generating schema for request {$requestClass}: " . $e->getMessage());
            return ['type' => 'object', 'properties' => []];
        }
    }

    private function initializeRequest(FormRequest $request): void
    {
        // Mock route parameters that might be needed for validation rules
        $request->setRouteResolver(function () {
            return new class {
                public function parameter($key)
                {
                    return (object)['id' => 1];
                }
                public function getParameter($key)
                {
                    return (object)['id' => 1];
                }
            };
        });

        // Set default values for required fields
        if (method_exists($request, 'rules')) {
            $rules = $request->rules();
            $defaults = [];

            foreach ($rules as $field => $fieldRules) {
                if (!is_array($fieldRules)) {
                    $fieldRules = explode('|', $fieldRules);
                }

                if ($this->isRequiredField($fieldRules)) {
                    $type = $this->determineType($fieldRules);
                    $defaults[$field] = $this->getDefaultValue($type);
                }
            }

            $request->merge($defaults);
        }
    }

    public function generateSchemaFromRules(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $fieldSchema = $this->generateFieldSchema($field, $fieldRules);
            
            // Handle array fields with nested rules
            if (Str::contains($field, '.*.')) {
                $arrayField = explode('.', $field)[0];
                if (!isset($properties[$arrayField])) {
                    $properties[$arrayField] = [
                        'type' => 'array',
                        'items' => ['type' => 'object', 'properties' => []]
                    ];
                }

                if (!isset($properties[$arrayField]['items'])) {
                    $properties[$arrayField]['items'] = ['type' => 'object', 'properties' => []];
                }

                $nestedField = str_replace($arrayField . '.*.', '', $field);
                $properties[$arrayField]['items']['properties'][$nestedField] = $fieldSchema;
            } 
            // Handle dot notation for nested objects (e.g., unit_type.name)
            else if (Str::endsWith($field, '.*')) {
                $fieldName = explode('.', $field)[0];
                $properties[$fieldName] = $fieldSchema;
            }
            elseif (Str::contains($field, '.') && !Str::contains($field, '.*.')) {
                $segments = explode('.', $field);
                $this->setNestedProperty($properties, $segments, $fieldSchema);
            }
            else {
                $properties[$field] = $fieldSchema;
            }

            if ($this->isRequiredField($fieldRules)) {
                $required[] = $field;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Sets a nested property in the properties array based on dot notation
     */
    private function setNestedProperty(array &$properties, array $segments, array $schema): void
    {
        $current = &$properties;
        $lastIndex = count($segments) - 1;

        for ($i = 0; $i < $lastIndex; $i++) {
            $segment = $segments[$i];
            
            if (!isset($current[$segment])) {
                $current[$segment] = [
                    'type' => 'object',
                    'properties' => []
                ];
            } elseif (!isset($current[$segment]['properties'])) {
                // Ensure properties key exists
                $current[$segment]['properties'] = [];
            }
            
            $current = &$current[$segment]['properties'];
        }

        $current[$segments[$lastIndex]] = $schema;
    }

    private function generateFieldSchema(string $field, array $rules): array
    {
        $schema = ['type' => 'string'];
        $description = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $this->processStringRule($rule, $schema, $description);
            } elseif ($rule instanceof Rule || is_array($rule)) {
                $this->processRuleObject($rule, $schema, $description);
            } elseif (is_object($rule) && method_exists($rule, '__invoke')) {
                $this->processClosureRule($rule, $schema, $description);
            } elseif ($rule instanceof In) {
                $this->processInRule($rule, $schema, $description);
            }
        }

        // Handle array fields
        if (Str::endsWith($field, '.*')) {
            $schema = [
                'type' => 'array',
                'items' => [
                    'type' => $schema['type']
                ]
            ];
            
            // Copy relevant properties to items
            foreach (['enum', 'format', 'minimum', 'maximum', 'minLength', 'maxLength'] as $prop) {
                if (isset($schema[$prop])) {
                    $schema['items'][$prop] = $schema[$prop];
                    unset($schema[$prop]);
                }
            }
        }

        // Add description if any rules generated descriptions
        if (!empty($description)) {
            $schema['description'] = implode('. ', array_unique($description));
        }

        // Handle nullable fields
        if (in_array('nullable', $rules) || in_array('sometimes', $rules)) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    private function processStringRule(string $rule, array &$schema, array &$description): void
    {
        if (str_contains($rule, ':')) {
            [$ruleName, $parameters] = explode(':', $rule, 2);
            $parameters = explode(',', $parameters);
        } else {
            $ruleName = $rule;
            $parameters = [];
        }

        switch ($ruleName) {
            case 'integer':
            case 'numeric':
                $schema['type'] = 'integer';
                break;
            case 'decimal':
            case 'float':
            case 'double':
                $schema['type'] = 'number';
                $schema['format'] = 'float';
                break;
            case 'boolean':
                $schema['type'] = 'boolean';
                break;
            case 'array':
                $schema['type'] = 'array';
                break;
            case 'date':
                $schema['type'] = 'string';
                $schema['format'] = 'date';
                break;
            case 'datetime':
                $schema['type'] = 'string';
                $schema['format'] = 'date-time';
                break;
            case 'min':
                if ($schema['type'] === 'string') {
                    $schema['minLength'] = (int)$parameters[0];
                } else {
                    $schema['minimum'] = (int)$parameters[0];
                }
                break;
            case 'max':
                if ($schema['type'] === 'string') {
                    $schema['maxLength'] = (int)$parameters[0];
                } else {
                    $schema['maximum'] = (int)$parameters[0];
                }
                break;
            case 'size':
                if ($schema['type'] === 'string') {
                    $schema['minLength'] = (int)$parameters[0];
                    $schema['maxLength'] = (int)$parameters[0];
                } else {
                    $schema['minimum'] = (int)$parameters[0];
                    $schema['maximum'] = (int)$parameters[0];
                }
                break;
            case 'in':
                $schema['enum'] = $parameters;
                break;
            case 'required_if':
                $field = $parameters[0];
                $value = $parameters[1] ?? '';
                $description[] = "Required when $field is $value";
                break;
            case 'required_unless':
                $field = $parameters[0];
                $value = $parameters[1] ?? '';
                $description[] = "Required unless $field is $value";
                break;
            case 'required_with':
                $description[] = 'Required when ' . implode(' or ', $parameters) . ' is present';
                break;
            case 'exists':
                $description[] = 'Must exist in the database';
                break;
            case 'unique':
                $description[] = 'Must be unique';
                break;
            case 'nullable':
                $schema['nullable'] = true;
                break;
            case 'sometimes':
                $schema['nullable'] = true;
                break;
        }
    }

    private function processRuleObject($rule, array &$schema, array &$description): void
    {
        try {
            if ($rule instanceof Rule) {
                $ruleString = (string)$rule;
                $ruleClass = get_class($rule);
                
                if (str_contains($ruleString, 'unique:')) {
                    $description[] = 'Must be unique';
                } elseif (str_contains($ruleString, 'exists:')) {
                    $description[] = 'Must exist in the database';
                } elseif (str_contains($ruleString, 'RequiredIf')) {
                    $description[] = 'Conditionally required';
                } elseif (str_contains($ruleString, 'ProhibitedUnless')) {
                    $description[] = 'Conditionally prohibited';
                } elseif (str_contains($ruleClass, 'In')) {
                    // Handle Rule::in() validation rules
                    $reflection = new ReflectionClass($rule);
                    if ($reflection->hasProperty('values')) {
                        $values = $reflection->getProperty('values');
                        $values->setAccessible(true);
                        $schema['enum'] = $values->getValue($rule);
                    }
                }
            }
        } catch (\Throwable $e) {
            // If we can't convert the rule to string, try to get its class name
            $ruleClass = get_class($rule);
            if (str_contains($ruleClass, 'RequiredIf')) {
                $description[] = 'Conditionally required';
            } elseif (str_contains($ruleClass, 'ProhibitedUnless')) {
                $description[] = 'Conditionally prohibited';
            }
        }
    }

    private function processInRule(In $rule, array &$schema, array &$description): void
    {
        $reflection = new ReflectionClass($rule);
        $property = $reflection->getProperty('values');
        $property->setAccessible(true);

        $values = array_map(fn($value) => $this->parseInValue($value), $property->getValue($rule));
        
        $schema['enum'] = $values;
    }

    private function parseInValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        // Check if enum
        if (is_object($value)) {
            if (isset($value->value)) {
                return $value->value;
            }
        }

        return $value;
    }

    private function processClosureRule(\Closure $rule, array &$schema, array &$description): void
    {
        $reflection = new ReflectionFunction($rule);
        $staticVariables = $reflection->getStaticVariables();
        
        if (isset($staticVariables['parameters'])) {
            $description[] = 'Conditionally required based on other fields';
        }
    }

    private function isRequiredField(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule === 'required') {
                return true;
            }
            if (is_object($rule) && method_exists($rule, '__toString')) {
                try {
                    $ruleString = (string)$rule;
                    if (str_starts_with($ruleString, 'required') && !str_starts_with($ruleString, 'required_if') && !str_starts_with($ruleString, 'required_unless')) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // If we can't convert the rule to string, check its class name
                    $ruleClass = get_class($rule);
                    if (str_contains($ruleClass, 'Required') && !str_contains($ruleClass, 'RequiredIf') && !str_contains($ruleClass, 'RequiredUnless')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function determineType(array $rules): string
    {
        $typeMap = [
            'numeric' => 'number',
            'integer' => 'integer',
            'boolean' => 'boolean',
            'array' => 'array',
            'exists' => 'integer',
            'string' => 'string',
        ];

        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            $ruleType = explode(':', $rule)[0];
            if (isset($typeMap[$ruleType])) {
                return $typeMap[$ruleType];
            }
        }

        return 'string';
    }

    private function getDefaultValue(string $type): mixed
    {
        return match($type) {
            'integer' => 1,
            'number' => 1.0,
            'boolean' => true,
            'array' => [],
            default => 'example'
        };
    }

    private function getPhpFilesInDirectory(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Get the relative path from the base directory
                $relativePath = str_replace($directory . '/', '', $file->getPathname());
                // Remove .php extension
                $relativePath = substr($relativePath, 0, -4);
                // Replace directory separators with namespace separators
                $relativePath = str_replace('/', '\\', $relativePath);
                $files[] = $relativePath;
            }
        }

        return $files;
    }
} 