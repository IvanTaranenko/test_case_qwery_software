<?php

namespace DevTest;

class Database implements DatabaseInterface
{
    public function buildQuery(string $query, array $args = []): string
    {
        $index = 0;
        $skipValue = $this->skip();

        $query = preg_replace_callback('/\{(.*?)\}/s', function ($matches) use (&$args, $skipValue) {
            $innerQuery = $matches[1];
            foreach ($args as $arg) {
                if ($arg === $skipValue) {
                    return '';
                }
            }
            return $innerQuery;
        }, $query);

        $query = preg_replace_callback('/\?([dfa#])?/', function ($matches) use (&$args, &$index) {
            $type = $matches[1] ?? '';
            $value = $args[$index++] ?? null;

            switch ($type) {
                case 'd':
                    return $value === null ? 'NULL' : (int)$value;
                case 'f':
                    return $value === null ? 'NULL' : (float)$value;
                case 'a':
                    if (!is_array($value)) {
                        throw new \Exception('Invalid array value.');
                    }
                    return $this->formatArray($value);
                case '#':
                    return $this->formatIdentifier($value);
                default:
                    return $this->formatValue($value);
            }
        }, $query);

        if (preg_match('/\?([dfa#])/', $query)) {
            throw new \Exception('Not all placeholders were replaced.');
        }

        return $query;
    }

    public function skip()
    {
        return null;
    }

    private function formatValue($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value)) {
            return sprintf("'%s'", addslashes($value));
        }
        return $value;
    }

    private function formatIdentifier($value)
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'formatIdentifier'], $value));
        }
        return sprintf('`%s`', addslashes($value));
    }

    private function formatArray(array $array): string
    {
        $formatted = [];
        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $formatted[] = $this->formatValue($value);
            } else {
                $formatted[] = sprintf('%s = %s', $this->formatIdentifier($key), $this->formatValue($value));
            }
        }
        return implode(', ', $formatted);
    }
}
