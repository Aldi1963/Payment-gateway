<?php
/**
 * Translation Service (i18n)
 * Multi-language support for PayGate Pro
 * 
 * Supported languages: id (Indonesian), en (English)
 * 
 * Usage:
 *   $t = new TranslationService('en');
 *   echo $t->get('auth.login_success');      // "Login successful"
 *   echo $t->get('errors.not_found');        // "Not found"
 *   echo __('auth.login_success');           // Using global helper
 * 
 * Placeholders:
 *   $t->get('wallet.balance_info', ['amount' => 'Rp 150.000']);
 *   // "Your balance is Rp 150.000"
 */

class TranslationService
{
    private static ?TranslationService $instance = null;
    private string $locale;
    private string $fallbackLocale = 'id';
    private array $translations = [];
    private array $loadedFiles = [];

    public function __construct(?string $locale = null)
    {
        $this->locale = $locale ?? $this->detectLocale();
        $this->loadTranslations($this->locale);
        
        if ($this->locale !== $this->fallbackLocale) {
            $this->loadTranslations($this->fallbackLocale);
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(?string $locale = null): self
    {
        if (self::$instance === null || ($locale && self::$instance->locale !== $locale)) {
            self::$instance = new self($locale);
        }
        return self::$instance;
    }

    /**
     * Translate a key with optional placeholders
     * 
     * @param string $key Dot-notation key (e.g., 'auth.login_success')
     * @param array $replace Placeholder replacements
     * @return string Translated string or key if not found
     */
    public function get(string $key, array $replace = []): string
    {
        // Try current locale first
        $translation = $this->findTranslation($key, $this->locale);
        
        // Fallback to default locale
        if ($translation === null && $this->locale !== $this->fallbackLocale) {
            $translation = $this->findTranslation($key, $this->fallbackLocale);
        }

        // If still not found, return the key itself
        if ($translation === null) {
            return $key;
        }

        // Replace placeholders :name with values
        foreach ($replace as $placeholder => $value) {
            $translation = str_replace(":{$placeholder}", (string)$value, $translation);
        }

        return $translation;
    }

    /**
     * Check if a translation key exists
     */
    public function has(string $key): bool
    {
        return $this->findTranslation($key, $this->locale) !== null 
            || $this->findTranslation($key, $this->fallbackLocale) !== null;
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->loadTranslations($locale);
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return ['id', 'en'];
    }

    /**
     * Load translation file for a locale
     */
    private function loadTranslations(string $locale): void
    {
        $langDir = base_path("lang/{$locale}");
        if (!is_dir($langDir)) return;

        $files = glob($langDir . '/*.php');
        foreach ($files as $file) {
            $group = basename($file, '.php');
            $fileKey = "{$locale}.{$group}";
            
            if (in_array($fileKey, $this->loadedFiles)) continue;
            
            $data = require $file;
            if (is_array($data)) {
                $this->translations[$locale][$group] = $data;
                $this->loadedFiles[] = $fileKey;
            }
        }
    }

    /**
     * Find translation for a dot-notation key
     */
    private function findTranslation(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);
        $itemKey = implode('.', $parts);

        if (!isset($this->translations[$locale][$group])) {
            return null;
        }

        $value = $this->translations[$locale][$group];
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Detect locale from request or user settings
     */
    private function detectLocale(): string
    {
        // 1. Check session
        if (!empty($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }

        // 2. Check query parameter
        if (!empty($_GET['lang']) && in_array($_GET['lang'], $this->getSupportedLocales())) {
            return $_GET['lang'];
        }

        // 3. Check Accept-Language header
        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (str_starts_with($acceptLang, 'en')) {
            return 'en';
        }

        // 4. Default from settings
        return setting('default_locale', 'id');
    }
}

// ==============================
// GLOBAL HELPER FUNCTIONS
// ==============================

/**
 * Translate a key (global shortcut)
 */
function __(?string $key = null, array $replace = []): string
{
    if ($key === null) return '';
    return TranslationService::getInstance()->get($key, $replace);
}

/**
 * Translate with choice (singular/plural)
 */
function trans_choice(string $key, int $count, array $replace = []): string
{
    $replace['count'] = $count;
    $translation = TranslationService::getInstance()->get($key, $replace);
    
    // Support "one|many" format
    if (str_contains($translation, '|')) {
        $parts = explode('|', $translation);
        return $count === 1 ? ($parts[0] ?? $translation) : ($parts[1] ?? $translation);
    }
    
    return $translation;
}
