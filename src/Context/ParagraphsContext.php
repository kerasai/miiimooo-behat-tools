<?php

namespace miiimooo\BehatTools\Context;

/**
 * Behat Context for adding paragraphs with API calls
 */
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;

class ParagraphsContext extends RawDrupalContext {
  /**
   * @var \Drupal\DrupalExtension\Context\DrupalContext
   */
  protected $drupalContext;

  protected $entities = [];

  const ENTITY_TYPE_ID = 'paragraph';

  /**
   * @BeforeScenario @javascript
   * Wrap XMLHttpRequest
   */
  public function prepare(BeforeScenarioScope $scope) {
    $this->drupalContext = $scope->getEnvironment()->getContext('Drupal\DrupalExtension\Context\DrupalContext');
  }

  /**
   * Create a paragraph.
   *
   * @param $entity
   *
   * @return saved
   *   The created paragraph.
   * @throws \Exception
   */
  public function create($entity) {
    $this->drupalContext->parseEntityFields(self::ENTITY_TYPE_ID, $entity);
    $this->dispatchHooks('BeforeParagraphCreateScope', $entity);
    $saved = $this->drupalContext->getDriver()->createEntity(self::ENTITY_TYPE_ID, $entity);
    $this->entities[] = $saved;
    $this->dispatchHooks('AfterParagraphCreateScope', $entity);
    return $saved;
  }

  /**
   * Remove any created products.
   *
   * @AfterScenario
   */
  public function cleanup() {
    // Remove any paragraphs that were created.
    foreach ($this->entities as $entity) {
      $this->drupalContext->getDriver()->entityDelete(self::ENTITY_TYPE_ID, $entity);
    }
    $this->entities = [];
  }

  /**
   * Creates paragraph of the given type, provided in the form:
   * | title     | My node        |
   * | Field One | My field value |
   * | author    | Joe Editor     |
   * | status    | 1              |
   * | ...       | ...            |
   *
   * @Given a/an :type paragraph named :name:
   */
  public function createParagraph($type, $name, TableNode $fields) {
    $entity = (object) [
      'type' => $type,
    ];
    foreach ($fields->getRowsHash() as $field => $value) {
      $entity->{$field} = $value;
    }
    $saved = $this->create($entity);
    $saved->__paragraph_name = $name;
  }

  protected function dispatchHooks($scopeType, \stdClass $entity) {
    $fullScopeClass = 'miiimooo\\BehatTools\\Hook\\Scope\\' . $scopeType;
    $scope = new $fullScopeClass($this->getDrupal()->getEnvironment(), $this, $entity);
    $callResults = $this->dispatcher->dispatchScopeHooks($scope);

    // The dispatcher suppresses exceptions, throw them here if there are any.
    foreach ($callResults as $result) {
      if ($result->hasException()) {
        $exception = $result->getException();
        throw $exception;
      }
    }
  }

  /**
   * @BeforeNodeCreate
   */
  public function beforeNodeCreateHook(BeforeNodeCreateScope $scope) {
    // This is missing in the Drupal driver
    /** @var \Drupal\field\Entity\FieldStorageConfig[] $fields */
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions('node');
    $node = $scope->getEntity();
    foreach ((array)$node as $field_name => $value) {
      if (is_array($value) || !isset($fields[$field_name])
        || !$fields[$field_name]->getSettings()
        || !isset($fields[$field_name]->getSettings()['target_type'])
        || $fields[$field_name]->getSettings()['target_type'] !== 'paragraph') {
        continue;
      }
      foreach ($this->entities as $entity) {
        if ($entity->__paragraph_name === $value) {
          $target_id = $entity->id;
          break;
        }
      }
      if (!$target_id) {
        sprintf('Referenced paragraph name "%s" not found.', $value);
        return;
      }
      $paragraph = \Drupal\paragraphs\Entity\Paragraph::load($target_id);
      $column_name = "$field_name:target_id";
      $node->$column_name = $target_id;
      $column_name = "$field_name:target_revision_id";
      $node->$column_name = $paragraph->getRevisionId();
    }
  }
}

