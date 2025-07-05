<?php

declare(strict_types=1);

namespace Hospiria\MultiUnit\Services\OpenApi\Generators;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use App\Helpers\ExtendedReflectionClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Hospiria\MultiUnit\Services\OpenApi\Generators\SchemaClass;
use Hospiria\MultiUnit\Services\OpenApi\Generators\SchemaTypeConverter;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\MultyType;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;

class PathGenerator
{
    private RequestSchemaGenerator $requestSchemaGenerator;
    private array $resourceMap;
    private SchemaTypeConverter $schemaTypeConverter;
    private Parser\ParseResource $parser;

    public function __construct(
        RequestSchemaGenerator $requestSchemaGenerator,
        Parser\ParseResource $parser = null
    )
    {
        $this->requestSchemaGenerator = $requestSchemaGenerator;
        $this->schemaTypeConverter = new SchemaTypeConverter(function (SchemaClass $resource) {
            $this->resourceMap[$resource->getName()] = $resource;
        });
        $this->parser = $parser ?? new Parser\ParseResource();
    }

    public function generate(): array
    {
        $paths = [];
        $this->resourceMap = [];

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

            $path = $this->normalizePath($route->uri());
            $method = strtolower($route->methods()[0]);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $operation = $this->generateOperation($route, $method);
            if ($operation) {
                $paths[$path][$method] = $operation;
            }
        }

        // Generate request schemas
        $requestSchemaResult = $this->requestSchemaGenerator->generate();
        $requestSchemas = $requestSchemaResult['schemas'] ?? [];
        $requestMap = $requestSchemaResult['requestMap'] ?? [];

        return [
            'paths' => $paths,
            'resourceMap' => $this->resourceMap,
            'requestMap' => $requestMap,
            'requestSchemas' => $requestSchemas
        ];
    }

    private function shouldIncludeRoute(RoutingRoute $route): bool
    {
        // Only include API routes from our package
        return str_starts_with($route->uri(), 'v1/') &&
               str_contains($route->getActionName(), 'Hospiria\\MultiUnit');
    }

    private function normalizePath(string $path): string
    {
        // Add leading slash and convert Laravel route parameters to OpenAPI parameters
        return '/' . preg_replace('/\{([^}]+)\}/', '{$1}', $path);
    }

    private function generateOperation(RoutingRoute $route, string $method): array
    {
        $operation = [
            'tags' => $this->generateTags($route),
            'summary' => $this->generateSummary($route),
            'description' => $this->generateDescription($route),
            'operationId' => $this->generateOperationId($route, $method),
        ];

        // Add parameters
        $parameters = $this->generateParameters($route);
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Add request body for POST, PUT, PATCH methods
        if (in_array($method, ['post', 'put', 'patch'])) {
            $requestBody = $this->generateRequestBody($route);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;
            }
        }

        // Add responses
        $operation['responses'] = $this->generateResponses($route);

        // Add security if route requires authentication
        if ($this->requiresAuth($route)) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        return $operation;
    }

    private function generateTags(RoutingRoute $route): array
    {
        $controller = $route->getController();
        if (!$controller) {
            return ['default'];
        }

        $reflection = new ReflectionClass($controller);
        $controllerName = $reflection->getShortName();
        // Remove 'Controller' suffix and split by uppercase letters
        $tag = str_replace('Controller', '', $controllerName);
        $tag = preg_replace('/(?<!^)[A-Z]/', ' $0', $tag);
        
        return [$tag];
    }

    private function generateSummary(RoutingRoute $route): string
    {
        $action = $route->getActionMethod();
        return ucfirst(str_replace('_', ' ', Str::snake($action)));
    }

    private function generateDescription(RoutingRoute $route): string
    {
        $controller = $route->getController();
        if (!$controller) {
            return '';
        }

        $reflection = new ReflectionMethod($controller, $route->getActionMethod());
        $docComment = $reflection->getDocComment();

        if ($docComment) {
            // Extract description from PHPDoc
            preg_match('/@description\s+(.+)/i', $docComment, $matches);
            if (isset($matches[1])) {
                return trim($matches[1]);
            }
        }

        return '';
    }

    private function generateOperationId(RoutingRoute $route, string $method): string
    {
        $controller = $route->getController();
        if (!$controller) {
            return $method . '_' . str_replace('/', '_', $route->uri());
        }

        $action = $route->getActionMethod();
        $controllerName = class_basename($controller);
        $controllerName = str_replace('Controller', '', $controllerName);
        
        return lcfirst($controllerName) . ucfirst($action);
    }

    private function generateParameters(RoutingRoute $route): array
    {
        $parameters = [];

        // Path parameters
        preg_match_all('/\{([^}]+)\}/', $route->uri(), $matches);
        foreach ($matches[1] as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => ucfirst(str_replace('_', ' ', $name)),
            ];
        }

        // Query parameters from form request
        $formRequest = $this->getFormRequest($route);
        if ($formRequest && method_exists($formRequest, 'rules')) {
            $rules = $formRequest->rules();
            foreach ($rules as $field => $fieldRules) {
                if (!is_array($fieldRules)) {
                    $fieldRules = explode('|', $fieldRules);
                }

                // Only add as query parameter if it's a GET request
                if (in_array('GET', $route->methods())) {
                    $parameters[] = [
                        'name' => $field,
                        'in' => 'query',
                        'required' => in_array('required', $fieldRules),
                        'schema' => $this->requestSchemaGenerator->generateSchemaFromRules([$field => $fieldRules])['properties'][$field],
                    ];
                }
            }
        }

        return $parameters;
    }

    private function generateRequestBody(RoutingRoute $route): ?array
    {
        $formRequest = $this->getFormRequest($route);
        if (!$formRequest) {
            return null;
        }

        // Get the request class name without namespace
        $requestClass = get_class($formRequest);
        $requestName = class_basename($requestClass);

        // Generate schema for this specific request if it hasn't been generated already
        $schema = $this->requestSchemaGenerator->generateSchemaFromRequest($requestClass);
        if (empty($schema['properties'])) {
            return null;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => "#/components/schemas/{$requestName}"
                    ]
                ]
            ]
        ];
    }

    private function generateResponses(RoutingRoute $route): array
    {
        $responses = [
            '401' => ['$ref' => '#/components/responses/Unauthorized'],
            '403' => ['$ref' => '#/components/responses/Forbidden'],
            '404' => ['$ref' => '#/components/responses/NotFound'],
        ];

        // Get the response resource
        $method = strtolower($route->methods()[0]);
        $resourceClass = $this->getResponseResource($route, $method);
        
        // Handle different return types from getResponseResource
        if ($resourceClass instanceof SchemaClass) {
            $this->resourceMap[$resourceClass->getName()] = $resourceClass;
        }

        // Add success response
        $responses['200'] = [
            'description' => 'Successful operation',
            'content' => [
                'application/json' => [
                    'schema' => $this->successSchema($resourceClass)
                ]
            ]
        ];

        // Add 422 validation error response for routes with form requests
        $formRequest = $this->getFormRequest($route);
        if ($formRequest) {
            $responses['422'] = ['$ref' => '#/components/responses/ValidationError'];
        }

        return $responses;
    }

    private function getResponseResource(RoutingRoute $route, string $method): mixed
    {
        $controller = $route->getController();
        if (!$controller) {
            return null;
        }

        $action = $route->getActionMethod();

        $reflection = new ReflectionClass($controller);
        
        // Try to get from PHPDoc first
        $responseResource = $this->getResponseResourceFromDocComment($controller, $action);
        if ($responseResource) {
            return $responseResource;
        }

        // If no PHPDoc, use the Parser to analyze the method return type
        $returnValue = $this->parser->parse($reflection, $action);
        if ($returnValue) {
            return $returnValue;
        }

        // Fall back to the existing logic for determining return types
        $controllerName = class_basename($controller);
        $baseControllerName = str_replace('Controller', '', $controllerName);
        
        // Handle specific cases first
        if (str_contains($route->uri(), '/performance')) {
            return new SchemaClass("{$baseControllerName}PerformanceResource", "Hospiria\\MultiUnit\\Http\\Resources\\{$baseControllerName}PerformanceResource", false);
        }

        // For marketing endpoints
        if (str_contains($controllerName, 'Marketing')) {
            return new SchemaClass("Marketing{$baseControllerName}Resource", "Hospiria\\MultiUnit\\Http\\Resources\\Marketing{$baseControllerName}Resource", false);
        }

        // Default cases based on HTTP method and action
        $resourceName = match(true) {
            // Index/List endpoints
            $method === 'get' && (
                str_contains($action, 'index') || 
                (!str_contains($route->uri(), '{') && !str_contains($route->uri(), '/performance'))
            ) => "{$baseControllerName}ListResource",
            
            // Single resource endpoints (show, store, update)
            $method === 'get' || in_array($method, ['post', 'put', 'patch']) =>
                "{$baseControllerName}Resource",
            
            default => null
        };

        return $resourceName ? new SchemaClass($resourceName, "Hospiria\\MultiUnit\\Http\\Resources\\{$resourceName}", false) : null;
    }

    private function getResponseResourceFromDocComment(mixed $controller, string $action): mixed
    {
        $reflection = new ExtendedReflectionClass($controller);
        $actionReflection = $reflection->getMethod($action);
        $useStatements = $reflection->getUseStatements();
        
        // Try to get from PHPDoc first
        $docComment = $actionReflection->getDocComment();
        if (!$docComment) {
            return null;
        }

        // Updated regex to capture union types with pipe (|) separator
        if (!preg_match('/@return\s+([\w<>]+(?:\s*\|\s*[\w<>]+)*)(?=\s|\||::|$)/', $docComment, $matches)) {
            return null;
        }

        $resourceNameString = $matches[1];
        
        // Check if we have a union type (contains pipe |)
        if (strpos($resourceNameString, '|') !== false) {
            $resourceNames = array_map('trim', explode('|', $resourceNameString));
            $schemaClasses = [];
            
            foreach ($resourceNames as $resourceName) {
                $resourceClass = $this->resolveResourceClass($resourceName, $useStatements);
                
                $schemaType = new SchemaType(SchemaType::OBJECT, true);
                $schemaType->setClassName($resourceClass);

                $schemaClasses[] = $schemaType;
            }
            
            // Return a MultyType containing all possible schema classes
            return new MultyType($schemaClasses);
        }
        
        // Original logic for single type
        $resourceName = $resourceNameString;
        $isPaginated = str_contains($resourceName, 'PaginatedResourceResponse');
        if ($isPaginated) {
            $resourceName = preg_match("/(?<=\w<)\w+(?>)/", $resourceName, $matches) ? $matches[0] : $resourceName;
        }

        $resourceClass = $this->resolveResourceClass($resourceName, $useStatements);
        $resourceName = $this->removeResourceSuffix($resourceName);

        return new SchemaClass($resourceName, $resourceClass, $isPaginated);
    }

    private function successSchema(mixed $resourceClass) {
        if ($resourceClass instanceof SchemaClass && $resourceClass->isPaginated()) {
            return $this->getPaginatedResourceSchema($resourceClass);
        }
        
        if ($resourceClass instanceof SchemaClass) {
            return $this->getResourceSchema($resourceClass);
        }
        
        // Use SchemaTypeConverter for Parser results
        if ($resourceClass instanceof Parser\Types\SchemaType || 
            $resourceClass instanceof Parser\Types\MultyType ||
            is_array($resourceClass)) {
            return $this->schemaTypeConverter->convertToOpenApiSchema($resourceClass);
        }

        return $this->getResourceSchema($resourceClass);
    }

    private function getResourceSchema(?SchemaClass $resourceClass) {
        if ($resourceClass) {
            return [
                '$ref' => "#/components/schemas/{$resourceClass->getName()}"
            ];
        }

        return [
            'type' => 'object'
        ];
    }

    private function removeResourceSuffix(string $name): string
    {
        return str_replace(['Resource'], '', $name);
    }

    private function resolveResourceClass(string $resourceClass, array $useStatements): string
    {
        foreach ($useStatements as $useStatement) {
            if ($resourceClass === $useStatement['as']) {
                return $useStatement['class'];
            }
        }

        return $resourceClass;
    }

    private function getPaginatedResourceSchema(SchemaClass $resourceClass) {
        return [
            'type' => 'object',
            'properties' => [
                'data' => $this->getResourceSchema($resourceClass),
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'string', 'format' => 'uri'],
                        'last' => ['type' => 'string', 'format' => 'uri'],
                        'prev' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'next' => ['type' => 'string', 'format' => 'uri', 'nullable' => true]
                    ]
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer'],
                        'from' => ['type' => 'integer'],
                        'last_page' => ['type' => 'integer'],
                        'links' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'url' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                                    'label' => ['type' => 'string'],
                                    'active' => ['type' => 'boolean']
                                ]
                            ]
                        ],
                        'path' => ['type' => 'string', 'format' => 'uri'],
                        'per_page' => ['type' => 'integer'],
                        'to' => ['type' => 'integer'],
                        'total' => ['type' => 'integer']
                    ]
                ],
                'filters' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'sort' => [
                    'type' => 'object',
                    'properties' => [
                        'by' => ['type' => 'string', 'nullable' => true],
                        'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]
                    ]
                ],
                'search' => ['type' => 'string', 'nullable' => true]
            ]
        ];
    }

    private function requiresAuth(RoutingRoute $route): bool
    {
        return collect($route->middleware())->contains(function ($middleware) {
            return Str::contains($middleware, ['auth', 'jwt']);
        });
    }

    private function getFormRequest(RoutingRoute $route): ?FormRequest
    {
        $controller = $route->getController();
        if (!$controller) {
            return null;
        }

        $action = $route->getActionMethod();
        $parameters = (new ReflectionMethod($controller, $action))->getParameters();

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string)$type;
                if (is_subclass_of($typeName, FormRequest::class)) {
                    $request = new $typeName();
                    
                    // Skip validation by overriding the authorize method
                    $request->setContainer(app());
                    $request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'GET']);
                    $request->setMethod('GET');

                    // Mock route parameters that might be needed for validation rules
                    $request->setRouteResolver(function () use ($route) {
                        return new class($route) {
                            private $route;
                            
                            public function __construct($route) {
                                $this->route = $route;
                            }
                            
                            public function parameter($key)
                            {
                                return (object)['id' => 1];
                            }
                            
                            public function getParameter($key)
                            {
                                return (object)['id' => 1];
                            }
                            
                            public function uri()
                            {
                                return $this->route->uri();
                            }
                            
                            public function methods()
                            {
                                return $this->route->methods();
                            }
                        };
                    });

                    return $request;
                }
            }
        }

        return null;
    }
}
