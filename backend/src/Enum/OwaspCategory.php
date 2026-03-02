<?php
namespace App\Enum;

enum OwaspCategory: string
{
    case A01_BROKEN_ACCESS_CONTROL     = 'A01';
    case A02_SECURITY_MISCONFIGURATION = 'A02';
    case A03_SUPPLY_CHAIN_FAILURES     = 'A03';
    case A04_CRYPTOGRAPHIC_FAILURES    = 'A04';
    case A05_INJECTION                 = 'A05';
    case A06_INSECURE_DESIGN           = 'A06';
    case A07_AUTHENTICATION_FAILURES   = 'A07';
    case A08_INTEGRITY_FAILURES        = 'A08';
    case A09_LOGGING_FAILURES          = 'A09';
    case A10_EXCEPTIONAL_CONDITIONS    = 'A10';
    case UNKNOWN                       = 'UNKNOWN';

    public function label(): string
    {
        return match($this) {
            self::A01_BROKEN_ACCESS_CONTROL     => 'A01 – Broken Access Control',
            self::A02_SECURITY_MISCONFIGURATION => 'A02 – Security Misconfiguration',
            self::A03_SUPPLY_CHAIN_FAILURES     => 'A03 – Software Supply Chain Failures',
            self::A04_CRYPTOGRAPHIC_FAILURES    => 'A04 – Cryptographic Failures',
            self::A05_INJECTION                 => 'A05 – Injection',
            self::A06_INSECURE_DESIGN           => 'A06 – Insecure Design',
            self::A07_AUTHENTICATION_FAILURES   => 'A07 – Authentication Failures',
            self::A08_INTEGRITY_FAILURES        => 'A08 – Software/Data Integrity Failures',
            self::A09_LOGGING_FAILURES          => 'A09 – Logging & Alerting Failures',
            self::A10_EXCEPTIONAL_CONDITIONS    => 'A10 – Mishandling of Exceptional Conditions',
            self::UNKNOWN                       => 'Unknown',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::A01_BROKEN_ACCESS_CONTROL     => 'IDOR, CORS mal configuré, escalade de privilèges',
            self::A02_SECURITY_MISCONFIGURATION => 'Headers manquants, debug actif, config par défaut',
            self::A03_SUPPLY_CHAIN_FAILURES     => 'Dépendances vulnérables, packages malveillants',
            self::A04_CRYPTOGRAPHIC_FAILURES    => 'Mots de passe en clair, algorithmes obsolètes',
            self::A05_INJECTION                 => 'SQL injection, XSS, command injection',
            self::A06_INSECURE_DESIGN           => 'Absence de validation, flux non sécurisés',
            self::A07_AUTHENTICATION_FAILURES   => 'Brute force, sessions non invalidées',
            self::A08_INTEGRITY_FAILURES        => 'CI/CD non sécurisé, désérialisation',
            self::A09_LOGGING_FAILURES          => 'Logs absents, pas d\'alertes sur erreurs',
            self::A10_EXCEPTIONAL_CONDITIONS    => 'Erreurs non gérées, stack traces exposées',
            self::UNKNOWN                       => '',
        };
    }
}