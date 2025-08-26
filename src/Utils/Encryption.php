<?php
/**
 * Encryption Utility
 *
 * @package AutoBotWriter
 * @since 1.5.0
 */

namespace AutoBotWriter\Utils;

use AutoBotWriter\Core\Plugin;

/**
 * Encryption Utility Class
 */
class Encryption
{
    /**
     * Encryption method
     */
    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Initialization vector length
     */
    private const IV_LENGTH = 16;

    /**
     * Encrypt a string
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     * @throws \Exception If encryption fails
     */
    public function encrypt(string $data): string
    {
        if (empty($data)) {
            return '';
        }

        $key = $this->get_encryption_key();
        $iv = random_bytes(self::IV_LENGTH);

        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }

        // Prepend IV to encrypted data and encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string
     *
     * @param string $encrypted_data Encrypted data (base64 encoded)
     * @return string Decrypted data
     * @throws \Exception If decryption fails
     */
    public function decrypt(string $encrypted_data): string
    {
        if (empty($encrypted_data)) {
            return '';
        }

        $data = base64_decode($encrypted_data);

        if ($data === false || strlen($data) < self::IV_LENGTH) {
            throw new \Exception('Invalid encrypted data');
        }

        $key = $this->get_encryption_key();
        $iv = substr($data, 0, self::IV_LENGTH);
        $encrypted = substr($data, self::IV_LENGTH);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Generate encryption key from WordPress constants
     *
     * @return string Encryption key
     * @throws \Exception If required constants are not defined
     */
    private function get_encryption_key(): string
    {
        // Use WordPress security keys for encryption
        $key_parts = [];

        $wp_keys = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT'
        ];

        foreach ($wp_keys as $wp_key) {
            if (defined($wp_key)) {
                $key_parts[] = constant($wp_key);
            }
        }

        if (empty($key_parts)) {
            throw new \Exception('WordPress security keys are not defined');
        }

        // Add plugin-specific salt
        $key_parts[] = 'AutoBotWriter_' . Plugin::VERSION;

        // Create a consistent key from the parts
        $combined_key = implode('|', $key_parts);
        
        // Use hash to create a key of the correct length
        return hash('sha256', $combined_key, true);
    }

    /**
     * Verify if data can be decrypted (for migration purposes)
     *
     * @param string $encrypted_data Encrypted data
     * @return bool True if data can be decrypted
     */
    public function can_decrypt(string $encrypted_data): bool
    {
        try {
            $this->decrypt($encrypted_data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Migrate from old encryption method
     *
     * @param string $old_encrypted_data Data encrypted with old method
     * @return string Data encrypted with new method
     * @throws \Exception If migration fails
     */
    public function migrate_from_old_encryption(string $old_encrypted_data): string
    {
        if (empty($old_encrypted_data)) {
            return '';
        }

        // Try to decrypt with old method
        $decrypted = $this->decrypt_old_method($old_encrypted_data);
        
        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt with old method');
        }

        // Re-encrypt with new method
        return $this->encrypt($decrypted);
    }

    /**
     * Decrypt using old method (for migration)
     *
     * @param string $encrypted_data Data encrypted with old method
     * @return string|false Decrypted data or false on failure
     */
    private function decrypt_old_method(string $encrypted_data): string|false
    {
        $ciphering_value = "AES-128-CTR";
        $key = hash('sha256', (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . 'AIBOTWriter2023');
        $options = 0;
        $iv = '1234567891011121';

        return openssl_decrypt($encrypted_data, $ciphering_value, $key, $options, $iv);
    }

    /**
     * Hash a string (for non-reversible data)
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hash(string $data): string
    {
        return hash('sha256', $data . $this->get_hash_salt());
    }

    /**
     * Verify a hash
     *
     * @param string $data Original data
     * @param string $hash Hash to verify against
     * @return bool True if hash matches
     */
    public function verify_hash(string $data, string $hash): bool
    {
        return hash_equals($this->hash($data), $hash);
    }

    /**
     * Get hash salt
     *
     * @return string Hash salt
     */
    private function get_hash_salt(): string
    {
        return defined('NONCE_SALT') ? NONCE_SALT : 'AutoBotWriter_Salt';
    }
}
