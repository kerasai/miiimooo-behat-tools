<?php
/**
 * @file
 * Entity scope.
 */
namespace miiimooo\BehatTools\Hook\Scope;

use Behat\Testwork\Hook\Scope\HookScope;

/**
 * Represents an Entity hook scope.
 */
final class AfterParagraphCreateScope extends ParagraphScope
{

  /**
   * Return the scope name.
   *
   * @return string
   */
    public function getName()
    {
        return self::AFTER;
    }
}
