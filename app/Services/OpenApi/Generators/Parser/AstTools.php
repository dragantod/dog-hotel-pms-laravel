<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * Utility class for AST (Abstract Syntax Tree) operations
 */
class AstTools
{
  /**
   * Extract variables defined in a class and method
   * 
   * @param array $stmts Parsed PHP statements
   * @param string $methodName The method name to analyze
   * @return array Variables and their values
   */
  public static function extractVariables(array $stmts, string $methodName): array
  {
    // Find the class method node
    $classMethod = self::findClassMethod($stmts, $methodName);
    if (!$classMethod) {
      return [];
    }

    // Use a VarVisitor to extract variables from the method
    $varTraverser = new NodeTraverser();
    $varVisitor = new VarVisitor();
    $varTraverser->addVisitor($varVisitor);
    $varTraverser->traverse($classMethod->getStmts());
    
    return $varVisitor->getRootVariables();
  }

  /**
   * Find a method node in the parsed statements
   * 
   * @param array $stmts Parsed PHP statements
   * @param string $methodName The method name to find
   * @return Node\Stmt\ClassMethod|null The method node if found
   */
  public static function findClassMethod(array $stmts, string $methodName): ?Node\Stmt\ClassMethod
  {
    foreach ($stmts as $stmt) {
      // Direct match for class method
      if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === $methodName) {
        return $stmt;
      }

      // Recursive search in namespaces and classes
      if ($stmt instanceof Node\Stmt\Namespace_ || $stmt instanceof Node\Stmt\Class_) {
        $method = self::findClassMethod($stmt->stmts, $methodName);
        if ($method) {
          return $method;
        }
      }
    }
    
    return null;
  }
} 