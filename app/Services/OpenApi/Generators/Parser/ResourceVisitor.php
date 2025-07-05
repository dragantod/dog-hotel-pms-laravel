<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\MultyType;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\ClassMethod;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;

/**
 * ResourceVisitor traverses a PHP AST to find the return type of a specific method.
 * It focuses on extracting type information for OpenAPI schema generation.
 */
class ResourceVisitor extends NodeVisitorAbstract
{
  /**
   * The return value/expression found in the method
   */
  private mixed $returnValue = null;
  
  /**
   * Use statements collected from the file
   */
  private array $useStatements = [];
  
  /**
   * Variable definitions and their values in the method scope
   */
  private array $variables = [];

  /**
   * @param string $methodName The name of the method to analyze
   * @param array $rootVariables Variables defined at class level
   */
  public function __construct(private string $methodName, private array $rootVariables)
  {
    $this->variables = $rootVariables;
  }

  /**
   * Get the extracted return value
   */
  public function getReturnValue()
  {
    return $this->returnValue;
  }

  /**
   * Get use statements collected during traversal
   */
  public function getUseStatements()
  {
    return $this->useStatements;
  }

  /**
   * Called when entering a node during traversal
   */
  public function enterNode(Node $node)
  {
    // Collect use statements for namespace resolution
    if ($node instanceof Node\Stmt\Use_) {
      foreach ($node->uses as $use) {
        $this->useStatements[] = [
          'class' => $use->name->toString(),
          'as' => $use->alias ? $use->alias->toString() : $use->name->getLast()
        ];
      }
    }
    
    // When we find the target method, extract its return value
    if ($node instanceof ClassMethod && $node->name->toString() === $this->methodName) {
      $returnNode = $this->findReturnStatement($node);
      
      if ($returnNode) {
        $this->returnValue = $this->resolveReturnValue($returnNode->expr);
      }
      
      // Stop traversal once we've found and processed the method
      return NodeVisitor::STOP_TRAVERSAL;
    }
    
    return null;
  }

  /**
   * Find the return statement in a method body
   */
  private function findReturnStatement(ClassMethod $node): ?Return_
  {
    foreach ($node->getStmts() as $stmt) {
      if ($stmt instanceof Return_) {
        return $stmt;
      }
    }
    
    return null;
  }

  /**
   * Resolve the return value by analyzing its expression
   */
  private function resolveReturnValue($expr)
  {
    // Handle array expressions
    if ($expr instanceof Array_) {
      return $this->resolveArrayValue($expr);
    }
    
    // Resolve variables to their values
    if ($expr instanceof Variable && isset($this->variables[$expr->name])) {
      return $this->resolveVariableValue($expr);
    }
    
    // Handle new object instantiation
    if ($expr instanceof Node\Expr\New_) {
      return $this->resolveNewExpression($expr);
    }
    
    // Handle static method calls (like Resource::collection())
    if ($expr instanceof Node\Expr\StaticCall) {
      return $this->resolveStaticCall($expr);
    }
    
    // Handle method calls (like response()->json())
    if ($expr instanceof Node\Expr\MethodCall) {
      return $this->resolveMethodCall($expr);
    }

    // Handle property access
    if ($expr instanceof Node\Expr\PropertyFetch) {
      return $expr;
    }
    
    // Return the expression as is for other cases
    return $expr;
  }

  /**
   * Resolve an array expression to its schema type representation
   * Distinguishes between associative arrays (objects) and sequential arrays
   */
  private function resolveArrayValue(Array_ $array): mixed
  {
    $items = [];
    $isAssociative = true;
    $hasStringKeys = false;
    
    foreach ($array->items as $item) {
      if ($item === null) {
        continue;
      }
      
      // Check if this is a sequential array rather than associative
      if ($item->key === null) {
        $isAssociative = false;
      } else if ($item->key instanceof String_) {
        $hasStringKeys = true;
      }
      
      // Recursively resolve the value
      $value = $this->resolveReturnValue($item->value);
      
      // Store with the appropriate key structure
      if ($item->key instanceof String_) {
        $items[$item->key->value] = $value;
      } else if ($item->key !== null) {
        // Handle numeric keys or other expression keys
        $items[(string)$item->key] = $value;
      } else {
        $items[] = $value;
      }
    }
    
    // Create the appropriate schema type
    // If it has string keys, it's an object in OpenAPI
    if ($hasStringKeys) {
      $schemaType = new SchemaType(SchemaType::OBJECT);
      $schemaType->setProperties($items);
      return $schemaType;
    }
    
    // If it's a sequential array without string keys, it's an array in OpenAPI
    if (!$isAssociative) {
      $schemaType = new SchemaType(SchemaType::ARRAY);
      $schemaType->setItems($items);
      return $schemaType;
    }
    
    // For numeric-keyed associative arrays, still treat as arrays
    $schemaType = new SchemaType(SchemaType::ARRAY);
    $schemaType->setItems(array_values($items));
    return $schemaType;
  }

  /**
   * Resolve a variable to its value
   */
  private function resolveVariableValue(Variable $variable): mixed
  {
    $varName = $variable->name;
    
    if (!isset($this->variables[$varName])) {
      return $variable;
    }
    
    $value = $this->variables[$varName];
    $value = $this->resolvePrimitiveVariableValue($value);
    
    // If variable has multiple potential values, return as is
    if (is_array($value) && count($value) > 1) {
      return new MultyType(array_map(fn($item) => $this->resolveReturnValue($item), $value));
    }
    
    // If variable is an array with one value, return that value
    if (is_array($value) && count($value) === 1) {
      return $this->resolveReturnValue($value[0]);
    }
    
    // Try to resolve the value
    return $this->resolveReturnValue($value);
  }

  /**
   * Ensures that the Variable resolved is primitive and not a function call
   */
  private function resolvePrimitiveVariableValue(mixed $variable): mixed
  {
    if (is_array($variable)) {
      return array_map(fn($item) => $this->resolvePrimitiveVariableValue($item), $variable);
    }

    $whitelistedTypes = [
      Node\Scalar\String_::class,
      Node\Scalar\Int_::class,
      Node\Scalar\Float_::class,
    ];

    foreach ($whitelistedTypes as $type) {
      if ($variable instanceof $type) {
        return $variable;
      }
    }
    
    return $variable;
  }
  
  /**
   * Resolve a new expression (object instantiation)
   */
  private function resolveNewExpression(Node\Expr\New_ $expr): mixed
  {
    // Extract the class name that's being instantiated
    if ($expr->class instanceof Node\Name) {
      $className = $expr->class->toString();
      
      // Create an object schema type
      $schemaType = new SchemaType(SchemaType::OBJECT);
      $schemaType->setClassName($className);
      
      return $schemaType;
    }
    
    // Default fallback
    return $expr;
  }
  
  /**
   * Resolve a static method call expression
   * Handles collection() methods that return arrays of resources
   */
  private function resolveStaticCall(Node\Expr\StaticCall $expr): mixed
  {
    // Handle collections like Resource::collection()
    if ($expr->name->name === 'collection' && $expr->class instanceof Node\Name) {
      $resourceClass = $expr->class->toString();
      
      // Create an array schema with the resource class as item type
      $schemaType = new SchemaType(SchemaType::ARRAY);
      
      // Create and set the item type
      $itemType = new SchemaType(SchemaType::OBJECT);
      $itemType->setClassName($resourceClass);
      
      $schemaType->setItemType($itemType);
      return $schemaType;
    }
    
    // Default fallback
    return new SchemaType(SchemaType::ANY);
  }

  /**
   * Resolve a method call expression
   * Special handling for response()->json(Resource) pattern
   */
  private function resolveMethodCall(Node\Expr\MethodCall $expr): mixed
  {
    // Check if this is a response()->json() call
    if ($expr->name->toString() === 'json' &&
        ($expr->var instanceof Node\Expr\MethodCall || $expr->var instanceof Node\Expr\FuncCall) &&
        $expr->var->name->toString() === 'response') {
      
      // If there's an argument to json() method, process it
      if (!empty($expr->args)) {
        $resourceArg = $expr->args[0]->value;
        
        // If the argument is a Resource class instance, return that instead
        if ($resourceArg instanceof Node\Expr\New_) {
          $className = $resourceArg->class->toString();
          if (str_contains($className, 'Resource')) {
            return $this->resolveNewExpression($resourceArg);
          }
        } else if ($resourceArg instanceof Node\Expr\Variable) {
          return $this->resolveVariableValue($resourceArg);
        } else if ($resourceArg instanceof Node\Expr\StaticCall) {
          $className = $resourceArg->class->toString();
          if (str_contains($className, 'Resource')) {
            return $this->resolveStaticCall($resourceArg);
          }
        }
      }
    }
    
    // Default fallback for other method calls
    return new SchemaType($expr);
  }
}
