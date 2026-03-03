<?php
namespace App\Enum;

enum Severity: string
{
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case INFO = 'info';

    public static function fromToolLevel(string $level): self
    {
        return match (strtolower(trim($level))) {
            'critical', 'error', 'blocker' => self::CRITICAL,
            'high', 'warning', 'warn' => self::HIGH,
            'medium', 'moderate' => self::MEDIUM,
            'low', 'minor' => self::LOW,
            default => self::INFO,
        };
    }

    public function penaltyPoints(): int
    {
        return match ($this) {
            self::CRITICAL => 20,
            self::HIGH => 12,
            self::MEDIUM => 7,
            self::LOW => 3,
            self::INFO => 1,
        };
    }
}
