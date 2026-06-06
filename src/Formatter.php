<?php

declare(strict_types=1);

namespace Switon\Logging;

use Switon\Core\Json;

use function preg_match_all;
use function preg_replace;
use function str_contains;
use function strtr;

/**
 * Default formatter for text and JSON log output.
 *
 * Guidance: Output mode is controlled by <code>$line_format</code>; text mode keeps the configured prefix layout, and JSON mode preserves structured payloads.
 *
 * - <code>{key}</code> placeholders in <code>$line_format</code> select text mode.
 * - No braces in <code>$line_format</code> select JSON mode.
 *
 * @see \Switon\Logging\FormatterInterface
 * @see \Switon\Logging\LogEntry
 */
class Formatter implements FormatterInterface
{
    /**
     * Chooses text or JSON formatting based on whether the format string contains placeholders.
     */
    public function format(LogEntry $logEntry, string $line_format): string
    {
        if (str_contains($line_format, '{')) {
            return $this->formatText($logEntry, $line_format);
        }
        return $this->formatJson($logEntry, $line_format);
    }

    /**
     * Formats log entry as JSON.
     *
     * @param LogEntry $logEntry
     * @param string $field_list Empty = all fields; else comma-separated field names
     *
     * @return string JSON string with newline
     */
    protected function formatJson(LogEntry $logEntry, string $field_list): string
    {
        if ($field_list === '') {
            $data = [
                'timestamp' => $this->formatTimestampISO8601($logEntry->timestamp),
                'level' => $logEntry->level,
                'category' => $logEntry->category,
                'message' => $logEntry->message,
                'location' => $logEntry->location,
                'hostname' => $logEntry->hostname,
            ];

            if ($logEntry->extra !== []) {
                $data['extra'] = $logEntry->extra;
            }

            return Json::stringify($data) . PHP_EOL;
        }

        $fieldList = array_map('trim', explode(',', $field_list));
        $data = [];

        foreach ($fieldList as $field) {
            if ($field === 'extra') {
                if ($logEntry->extra !== []) {
                    $data['extra'] = $logEntry->extra;
                }
            } elseif ($field === 'message') {
                $data['message'] = $logEntry->message;
            } elseif ($field === 'timestamp') {
                $data['timestamp'] = $this->formatTimestampISO8601($logEntry->timestamp);
            } elseif ($field === 'timestamp_unix') {
                $data['timestamp_unix'] = $logEntry->timestamp;
            } elseif (isset($logEntry->$field)) {
                $data[$field] = $logEntry->$field;
            }
        }

        if (!str_contains($field_list, 'extra') && $logEntry->extra !== []) {
            $data['extra'] = $logEntry->extra;
        }

        return Json::stringify($data) . PHP_EOL;
    }

    /**
     * Formats log entry as text with {key} placeholder syntax (same as message interpolation).
     *
     * @param LogEntry $logEntry
     * @param string $line_format
     *
     * @return string Formatted text with newline
     */
    protected function formatText(LogEntry $logEntry, string $line_format): string
    {
        $replaced = [];

        // Extract all placeholder keys from the line format (e.g., {time}, {level}, {message})
        preg_match_all('#\{(\w+)\}#', $line_format, $matches);
        foreach ($matches[1] as $key) {
            if ($key === 'message') {
                $message = $logEntry->message;

                // Append extra data as JSON if present (single space only when message not empty)
                if ($logEntry->extra !== []) {
                    $message .= ($message !== '' ? ' ' : '') . Json::stringify($logEntry->extra);
                }

                // Special handling for multi-line messages (e.g., exception stack traces):
                // split and prefix each line with the formatted log prefix (time, level, etc.)
                if (str_contains($message, "\n")) {
                    // First, replace all placeholders except message to get the prefix
                    $replaced['{message}'] = '';
                    $prefix = strtr($line_format, $replaced);
                    // Replace newlines with the prefix, so each line has the log prefix
                    // \0 in replacement means the matched newline character is preserved
                    $message = preg_replace('#[\\r\\n]+#', '\0' . $prefix, $message);
                    $replaced['{message}'] = $message . PHP_EOL;
                } else {
                    // Regular messages: just append the message with newline
                    $replaced['{message}'] = $message . PHP_EOL;
                }
            } else {
                // Replace other placeholders with log properties or '-' if not available
                $replaced['{' . $key . '}'] = $logEntry->$key ?? '-';
            }
        }

        return strtr($line_format, $replaced);
    }

    /**
     * Formats Unix timestamp with microseconds to ISO 8601 format.
     *
     * @param float $timestamp Unix timestamp with microseconds
     *
     * @return string ISO 8601 formatted timestamp (e.g., "2024-01-15T10:30:45.123456Z")
     */
    protected function formatTimestampISO8601(float $timestamp): string
    {
        $microseconds = sprintf('%06d', ($timestamp - (int)$timestamp) * 1000000);
        return gmdate('Y-m-d\TH:i:s', (int)$timestamp) . '.' . $microseconds . 'Z';
    }
}
