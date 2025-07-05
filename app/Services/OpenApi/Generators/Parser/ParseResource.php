<?php

namespace Hospiria\MultiUnit\Services\OpenApi\Generators\Parser;

use PhpParser\Node;
use PhpParser\Error;
use ReflectionClass;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use App\Helpers\ExtendedReflectionClass;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\Types\SchemaType;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\ClassResolver;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\TypeParser;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\SchemaProcessor;
use Hospiria\MultiUnit\Services\OpenApi\Generators\Parser\AstTools;

/**
 * ParseResource provides the core functionality for parsing PHP resource classes
 * to extract return types that will be used for OpenAPI schema generation.
 */
class ParseResource
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
   * Class resolver for handling class name resolution
   */
  private ClassResolver $classResolver;

  /**
   * Type parser for converting PHP AST nodes to schema types
   */
  private TypeParser $typeParser;

  /**
   * Schema processor for further processing schema types
   */
  private SchemaProcessor $schemaProcessor;

  /**
   * Parse a PHP method to determine its return type structure
   * 
   * @param ReflectionClass $reflection The class containing the method
   * @param string $methodName The method name to analyze
   * @return mixed The structured return type
   */
  public function parse(ReflectionClass $reflection, string $methodName)
  {
    // Initialize reflection and read the file
    $this->reflection = new ExtendedReflectionClass($reflection->getName());
    $this->useStatements = $this->reflection->getUseStatements();
    
    // Initialize component classes
    $this->classResolver = new ClassResolver($this->reflection, $this->useStatements);
    $this->schemaProcessor = new SchemaProcessor($this->classResolver);
    $this->typeParser = new TypeParser($this->classResolver, $this->schemaProcessor);
    
    // Set the TypeParser on the SchemaProcessor to handle complex property types
    $this->schemaProcessor->setTypeParser($this->typeParser);

    try {
      // Set up the PHP parser
      $parser = (new ParserFactory())->createForNewestSupportedVersion();
      $code = file_get_contents($reflection->getFileName());
      $stmts = $parser->parse($code);
      
      // Extract variables defined in the class/method scope
      $rootVariables = AstTools::extractVariables($stmts, $methodName);
      
      // Set up and run the visitor to extract the return type
      $traverser = new NodeTraverser();
      $visitor = new ResourceVisitor($methodName, $rootVariables);
      $traverser->addVisitor($visitor);
      $traverser->traverse($stmts);
      
      // Get the return value from the visitor
      $returnValue = $visitor->getReturnValue();
      if ($returnValue === null) {
        return null;
      }
      
      // Process use statements from the visitor
      $this->useStatements = array_merge(
        $this->useStatements, 
        $visitor->getUseStatements()
      );
      $this->classResolver->updateUseStatements($this->useStatements);
      
      // Parse the return value into a structured type
      return $this->typeParser->parseReturnValueType($returnValue);
    } catch (Error $e) {
      // Handle parsing errors gracefully
      error_log('ParseResource Error: ' . $e->getMessage());
      return null;
    }
  }
}
