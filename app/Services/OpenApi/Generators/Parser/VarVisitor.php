<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * VarVisitor extracts variable assignments from PHP code.
 * It creates a simple map of variable names to their assigned values.
 */
class VarVisitor extends NodeVisitorAbstract
{
  /**
   * The scope stack for tracking variable definitions in different contexts
   */
  private array $scopes = [[]];
  
  /**
   * Variables defined at the root scope
   */
  private array $rootVariables = [];

  /**
   * Called when entering a node during traversal
   */
  public function enterNode(Node $node)
  {
    // When entering a new scope, create a new level on the scope stack
    if ($node instanceof Node\Stmt\Function_ || 
        $node instanceof Node\Expr\Closure || 
        $node instanceof Node\Stmt\ClassMethod) {
      array_push($this->scopes, []);
    }

    // Track variable assignments 
    if ($node instanceof Node\Expr\Assign) {
      // Check if the variable being assigned is an array access expression
      if ($node->var instanceof Node\Expr\ArrayDimFetch) {
        $this->processArrayKeyAssignment($node);
      } else {
        $this->processAssignment($node);
      }
    }
  }

  /**
   * Called when leaving a node during traversal
   */
  public function leaveNode(Node $node)
  {
    // When leaving a scope, remove the scope from the stack
    if ($node instanceof Node\Stmt\Function_ || 
        $node instanceof Node\Expr\Closure || 
        $node instanceof Node\Stmt\ClassMethod) {
      array_pop($this->scopes);
    }
  }
  
  /**
   * Process an array key assignment like $array['key'] = value
   */
  private function processArrayKeyAssignment(Node\Expr\Assign $node): void
  {
    /** @var Node\Expr\ArrayDimFetch $arrayDimFetch */
    $arrayDimFetch = $node->var;
    
    // Get the variable being accessed (left side of the dimension fetch)
    if ($arrayDimFetch instanceof Node\Expr\ArrayDimFetch) {
      if ($arrayDimFetch->var instanceof Node\Expr\Variable && is_string($arrayDimFetch->var->name)) {
        $arrayName = $arrayDimFetch->var->name;
        $key = $arrayDimFetch->dim; // The dimension/key being accessed
        $value = $node->expr;
        
        // Look for the array variable in the current and parent scopes
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
          if (isset($this->scopes[$i][$arrayName])) {
            // We found the array, now update it with the new key-value pair
            // Note: This is a simplified approach - we're not actually modifying the AST
            // In a real implementation, we might want to build a more complex structure
            
            // If it's in the root scope, also update rootVariables
            if ($i === 0 && isset($this->rootVariables[$arrayName])) {
              foreach ($this->rootVariables[$arrayName] as &$rootValue) {
                // If this is actually an array assignment, add the key
                $this->addKeyToArray($rootValue, $key, $value);
              }
            }
            
            // Update the value in the scope
            $this->addKeyToArray($this->scopes[$i][$arrayName], $key, $value);
            break;
          }
        }
      }
    }
  }
  
  /**
   * Helper function to add a key to an array node
   */
  private function addKeyToArray(&$arrayNode, $key, $value): void
  {
    // If the node is an array already, try to add to it
    if ($arrayNode instanceof Node\Expr\Array_) {
      // Add a new item to the array
      $arrayNode->items[] = new Node\Expr\ArrayItem(
        $value,
        $key
      );
    }
    // Otherwise, we might want to convert it to an array in some cases
    // For simplicity, we're not handling all possible cases here
  }
  
  /**
   * Process an assignment expression
   */
  private function processAssignment(Node\Expr\Assign $node): void
  {
    // Only handle simple variable assignments for now
    if ($node->var instanceof Node\Expr\Variable) {
      // Variables in PHP-Parser can have name as string or as expression
      // We only handle the simple case of string names here
      if (is_string($node->var->name)) {
        $varName = $node->var->name;
        $value = $node->expr;
        
        // Add to current scope
        $currentScope = &$this->scopes[count($this->scopes) - 1];
        $currentScope[$varName] = $value;
        
        // If in root scope, add to root variables
        if (count($this->scopes) === 1) {
          if (!isset($this->rootVariables[$varName])) {
            $this->rootVariables[$varName] = [];
          }
          
          // Add this value to the array of possible values for this variable
          $this->rootVariables[$varName][] = $value;
        }
      }
    }
  }

  /**
   * Get all variables defined at the root scope
   */
  public function getRootVariables(): array
  {
    return $this->rootVariables;
  }
}
