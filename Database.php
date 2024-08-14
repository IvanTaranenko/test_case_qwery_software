<?php

namespace DevTest;

use Exception;
use mysqli;

class Database extends AbstractDatabase implements DatabaseInterface
{
    public function buildQuery(string $query, array $args = []): string
    {
        $this->validateTemplate($query);

        $argsCopy = $args;
        $query = $this->processConditionalBlocks($query, $argsCopy);

        $query = str_replace('SKIP', '', $query);

        $placeholders = [
            '?d' => fn($value) => $value === null ? 'NULL' : (int)$value,
            '?f' => fn($value) => $value === null ? 'NULL' : (float)$value,
            '?a' => fn($value) => $this->processArray($value),
            '?#' => function ($value) {
                if (!is_array($value)) {
                    throw new Exception('Expected array for ?# placeholder.');
                }
                return $this->processIdentifiers($value);
            },
            '?' => fn($value) => $this->processDefault($value)
        ];

        $i = 0;
        echo "Query before replacement: $query\n";
        echo "Arguments: " . print_r($args, true) . "\n";

        $result = preg_replace_callback(
            '/\?(d|f|a|#)?/',
            function ($matches) use (&$args, &$i, $placeholders) {
                $type = $matches[1] ?? '';
                $placeholder = $matches[0];
                if (!isset($args[$i])) {
                    return '?';
                }

                $value = $args[$i] ?? null;
                if (isset($placeholders[$placeholder])) {
                    try {
                        $result = $placeholders[$placeholder]($value);
                    } catch (Exception $e) {
                        throw $e;
                    }
                } else {
                    $result = $placeholders['?']($value);
                }

                $i++;
                return $result;
            },
            $query
        );
        echo "Query after replacement: $result\n";

        if ($i > count($args)) {
            throw new Exception('Too many arguments provided. Expected: ' . count($args) . ', Used: ' . $i);
        } elseif ($i < count($args)) {
            throw new Exception('Not enough arguments provided. Expected: ' . count($args) . ', Used: ' . $i);
        }

        return $result;
    }

    public function skip()
    {
        return 'SKIP';
    }
}
