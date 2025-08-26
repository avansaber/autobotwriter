<?php
/**
 * AutoBotWriter Autoloader
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Core;

/**
 * PSR-4 Autoloader for AutoBotWriter
 */
class Autoloader
{
    /**
     * Namespace prefix
     */
    private const NAMESPACE_PREFIX = 'AutoBotWriter\\';

    /**
     * Base directory for the namespace prefix
     */
    private string $base_dir;

    /**
     * Constructor
     *
     * @param string $base_dir Base directory for the namespace prefix
     */
    public function __construct(string $base_dir)
    {
        $this->base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Register the autoloader
     *
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Loads the class file for a given class name
     *
     * @param string $class The fully-qualified class name
     * @return void
     */
    public function loadClass(string $class): void
    {
        // Does the class use the namespace prefix?
        $len = strlen(self::NAMESPACE_PREFIX);
        if (strncmp(self::NAMESPACE_PREFIX, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $this->base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Static method to quickly register the autoloader
     *
     * @param string $base_dir Base directory for the namespace prefix
     * @return self
     */
    public static function register_autoloader(string $base_dir): self
    {
        $autoloader = new self($base_dir);
        $autoloader->register();
        return $autoloader;
    }
}
