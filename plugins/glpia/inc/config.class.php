<?php

/**
 * glpIA plugin configuration handler.
 *
 * Stores and retrieves plugin settings using GLPI's Config API.
 * Settings are persisted in the glpi_configs database table
 * under the 'plugin_glpia' context.
 *
 * @since 1.0.0
 */
class PluginGlpiaConfig
{
    /** Config context key used in glpi_configs table */
    private const CONTEXT = 'plugin_glpia';

    /**
     * Default configuration values.
     * Used when no configuration has been saved yet.
     */
    private const DEFAULTS = [
        'api_key'       => '',
        'api_url'       => 'https://api.deepseek.com/v1/chat/completions',
        'model'         => 'deepseek-v4-flash',
        'temperature'   => '0.7',
        'max_tokens'    => '2000',
        'system_prompt' => '',
    ];

    /**
     * Get all configuration values, merged with defaults.
     *
     * @return array Associative array of config values
     */
    public static function getAll(): array
    {
        $saved = Config::getConfigurationValues(self::CONTEXT);
        $config = [];

        foreach (self::DEFAULTS as $key => $default) {
            $config[$key] = $saved[$key] ?? $default;
        }

        // Mask API key in the output for display
        // The raw key is only used when calling the API
        return $config;
    }

    /**
     * Get a single configuration value.
     *
     * @param string $key Config key
     * @return string|null Config value, or null if not set (without default)
     */
    public static function get(string $key): ?string
    {
        $all = self::getAll();
        return $all[$key] ?? null;
    }

    /**
     * Save configuration values to the database.
     *
     * @param array $values Associative array of config keys to save
     * @return void
     */
    public static function save(array $values): void
    {
        // Only save keys that exist in defaults
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (array_key_exists($key, $values)) {
                $clean[$key] = trim($values[$key]);
            }
        }

        if (!empty($clean)) {
            Config::setConfigurationValues(self::CONTEXT, $clean);
        }
    }

    /**
     * Get the default system prompt used when none is configured.
     *
     * @return string Default system prompt
     */
    public static function getDefaultSystemPrompt(): string
    {
        return <<<PROMPT
Eres un asistente que mejora textos de tickets de soporte IT escritos por técnicos.
Tu tarea es reescribir el texto proporcionado para que sea más profesional, claro y detallado.

Reglas:
- NO inventes información que no esté en el contexto ni en el texto original.
- Mantén el idioma original del texto (normalmente español).
- Sé conciso pero completo. No añadas paja.
- Si el texto describe una solución, explica qué se hizo y cómo.
- Si es un seguimiento, incluye el estado actual si se menciona.
- NO añadas saludos, despedidas ni frases de cortesía.
- NO uses formato markdown en la respuesta.
- Devuelve SOLO el texto mejorado, sin explicaciones ni prefijos.
PROMPT;
    }

    /**
     * Get the system prompt (custom or default).
     *
     * @return string System prompt text
     */
    public static function getSystemPrompt(): string
    {
        $custom = self::get('system_prompt');
        if (!empty($custom)) {
            return $custom;
        }
        return self::getDefaultSystemPrompt();
    }
}
