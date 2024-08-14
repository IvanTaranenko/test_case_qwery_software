<?php

namespace DevTest;

use Exception;
use mysqli;

abstract class AbstractDatabase
{
    protected mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    abstract public function buildQuery(string $query, array $args = []): string;

    protected function validateTemplate(string $query): void
    {
        if (substr_count($query, '{') !== substr_count($query, '}')) {
            throw new Exception('Unmatched curly braces in the template.');
        }
    }

    protected function processArray(array $values): string
    {
        if (empty($values)) {
            return 'NULL';
        }

        $processed = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $processed[] = "(" . $this->processArray($value) . ")";
            } else {
                $processed[] = $this->processDefault($value);
            }
        }

        return implode(', ', $processed);
    }

    protected function processIdentifiers(array $identifiers): string
    {
        return implode(', ', array_map(fn($identifier) => $this->quoteIdentifier($identifier), $identifiers));
    }

    protected function processDefault($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return "'" . $this->escapeString((string)$value) . "'";
    }

    protected function escapeString(string $value): string
    {
        return $this->mysqli->real_escape_string($value);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . $this->escapeString($identifier) . '`';
    }

    protected function processConditionalBlocks(string $query, array &$args): string
    {
        $queryParts = [];
        $currentPart = '';
        $inBlock = false;

        for ($i = 0; $i < strlen($query); $i++) {
            $char = $query[$i];

            if ($char === '{') {
                if ($inBlock) {
                    throw new Exception('Unmatched curly braces in the template.');
                }
                $inBlock = true;
                $queryParts[] = $currentPart;
                $currentPart = '';
            } elseif ($char === '}') {
                if (!$inBlock) {
                    throw new Exception('Unmatched curly braces in the template.');
                }
                $inBlock = false;

                // Evaluate the block content with the given arguments
                $blockContent = $this->buildQuery($currentPart, $args);

                // If the blockContent contains SKIP, do not include it in the final query
                if (strpos($blockContent, 'SKIP') === false) {
                    $queryParts[] = $blockContent;
                }

                $currentPart = '';
            } else {
                $currentPart .= $char;
            }
        }

        if ($inBlock) {
            throw new Exception('Unmatched curly braces in the template.');
        }

        $queryParts[] = $currentPart;

        return implode('', $queryParts);
    }
}
