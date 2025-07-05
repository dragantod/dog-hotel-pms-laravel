<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use App\Helpers\ExtendedReflectionClass;

/**
 * Resolves class names using namespace and use statements
 */
class ClassResolver
{
  /**
   * Extended reflection for the resource class
   */
  private ExtendedReflectionClass $reflection;
  
  /**
   * Use statements collected from the file
   */
  private array $useStatements = [];

  /**
   * Constructor
   *
   * @param ExtendedReflectionClass $reflection The reflection of the class
   * @param array $useStatements Use statements from the file
   */
  public function __construct(ExtendedReflectionClass $reflection, array $useStatements)
  {
    $this->reflection = $reflection;
    $this->useStatements = $useStatements;
  }

  /**
   * Update use statements after parsing
   *
   * @param array $useStatements New use statements to merge
   */
  public function updateUseStatements(array $useStatements): void
  {
    $this->useStatements = $useStatements;
  }

  /**
   * Resolve a class name using the use statements
   * 
   * @param string $className The class name to resolve
   * @return string The fully qualified class name
   */
  public function resolveClassName(string $className): string
  {
    // Skip PHP keywords
    if (in_array(strtolower($className), ['parent', 'self', 'static'])) {
      // Log the issue so we know it happened
      error_log("Warning: PHP keyword '$className' was used as a class name. This is not a valid class.");
      return ""; // Return empty string to indicate invalid class
    }
    
    // If it's already a fully qualified name, return as is
    if (strpos($className, '\\') === 0) {
      return substr($className, 1);
    }
    
    // Try to find the class in use statements
    foreach ($this->useStatements as $useStatement) {
      if ($useStatement['as'] === $className) {
        return $useStatement['class'];
      }
    }
    
    // If not found, assume it's in the same namespace
    return $this->reflection->getNamespaceName() . '\\' . $className;
  }

  /**
   * Get the namespace of the current class
   *
   * @return string The namespace
   */
  public function getNamespace(): string
  {
    return $this->reflection->getNamespaceName();
  }

  /**
   * Get the reflection object
   *
   * @return ExtendedReflectionClass The reflection object
   */
  public function getReflection(): ExtendedReflectionClass
  {
    return $this->reflection;
  }
} 