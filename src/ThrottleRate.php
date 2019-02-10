<?php

namespace Drupal\queue_throttle;

/**
 * Class ThrottleRate.
 *
 * @package Drupal\queue_throttle
 */
class ThrottleRate {

  /**
   * Unit string representations.
   */
  const MICROSECOND = 'microsecond';
  const MILLISECOND = 'millisecond';
  const SECOND = 'second';
  const MINUTE = 'minute';
  const HOUR = 'hour';
  const DAY = 'day';
  const WEEK = 'week';

  /**
   * Unit map.
   */
  const UNIT_MAP = [
    self::MICROSECOND => 0.000001,
    self::MILLISECOND => 0.001,
    self::SECOND => 1,
    self::MINUTE => 60,
    self::HOUR => 3600,
    self::DAY => 86400,
    self::WEEK => 604800,
  ];

  /**
   * Tokens for unit.
   *
   * @var int
   */
  protected $tokens;

  /**
   * Unit for tokens.
   *
   * @var string
   */
  protected $unit;

  /**
   * TokenRate constructor.
   *
   * @param int $tokens
   *   Amount of tokens.
   * @param string $unit
   *   Unit for tokens.
   */
  public function __construct($tokens, $unit) {
    if (!isset(self::UNIT_MAP[$unit])) {
      throw new \InvalidArgumentException('Invalid unit.');
    }
    if ($tokens <= 0) {
      throw new \InvalidArgumentException('Invalid token amount.');
    }
    $this->tokens = $tokens;
    $this->unit = $unit;
  }

  /**
   * Get units.
   */
  public static function getUnits() {
    return array_keys(self::UNIT_MAP);
  }

  /**
   * Get 1 unit in seconds.
   *
   * @param string $unit
   *   Time unit.
   *
   * @return double
   *   Decimal.
   */
  public static function getUnitInSeconds($unit) {
    return self::UNIT_MAP[$unit];
  }

  /**
   * Get tokens allowed per second.
   */
  public function getTokensPerSecond() {
    return $this->tokens / self::UNIT_MAP[$this->unit];
  }

}
