<?php
/**
 * Mapping Service
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.1.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

class MappingService
{
    private array $mappings = [];
    private bool $mapping_error = false;
    private string $current_mapping_file = '';

    public function __construct(string $mapping_file = 'bricks-default.csv')
    {
        // The mapping file is now located inside the plugin's 'mappings' directory.
        $this->current_mapping_file = IMMOBRIDGE_PLUGIN_DIR . 'mappings/' . $mapping_file;
        $this->fetchMappings();
    }

    public function getMappings(): array
    {
        return $this->mappings;
    }

    public function hasError(): bool
    {
        return $this->mapping_error;
    }

    /**
     * Read and set up the mappings from a CSV file.
     * Adapted from immonex-openimmo2wp/_fetch_mappings.
     */
    private function fetchMappings(): void
    {
        if (!file_exists($this->current_mapping_file)) {
            $this->mapping_error = true;
            // In a real app, we'd use a proper logging system.
            error_log('ImmoBridge Error: Mapping file not found at ' . $this->current_mapping_file);
            return;
        }

        $raw_mappings = [];
        if (! (bool) preg_match('//u', file_get_contents($this->current_mapping_file))) {
            $this->mapping_error = true;
            error_log('ImmoBridge Error: Mapping file encoding is not proper UTF-8.');
            return;
        }

        $f = fopen($this->current_mapping_file, 'r');
        $row = 0;
        $column_types = [];
        while (false !== ($row_values = fgetcsv($f, 1000, ',', '"'))) {
            if (empty($row_values[0]) || '#' === $row_values[0][0]) {
                continue;
            }

            $row++;
            if (1 === $row) {
                $column_types = array_map('strtolower', $row_values);
                continue;
            }

            $row_values_named = [];
            foreach ($row_values as $i_row => $value) {
                if (isset($column_types[$i_row])) {
                    $row_values_named[$column_types[$i_row]] = trim($value);
                }
            }
            $raw_mappings[] = $row_values_named;
        }
        fclose($f);

        if (count($raw_mappings) > 0) {
            $this->mappings = [];
            foreach ($raw_mappings as $mapping) {
                if (!isset($mapping['type']) || !isset($mapping['source'])) {
                    continue;
                }
                $this->mappings[] = $mapping;
            }
        } else {
            $this->mapping_error = true;
            error_log('ImmoBridge Error: No regular mappings found in CSV.');
        }
    }

    /**
     * Get XML node/attribute value and apply filters.
     * Adapted from immonex-openimmo2wp/_get_element_value.
     *
     * @param \SimpleXMLElement $xml XML document or node.
     * @param string $element XML element/attribute path.
     * @return string|bool|array Node/attribute value.
     */
    public function getElementValue(\SimpleXMLElement $xml, array $mapping): string|bool|array
    {
        $element = $mapping['source'];
        $element_split = $this->splitElement($element);
        $xpath_query = $this->buildXPathQuery($xml, $element_split);

        if ($xpath_query === false) {
            return false;
        }

        $data = $xml->xpath($xpath_query);

        if (empty($data)) {
            return false;
        }

        if ($element_split['attribute']) {
            return (string)$data[0][$element_split['attribute']];
        }

        return trim((string)$data[0]);
    }

    /**
     * Split XML element declaration into its parts.
     * Adapted from immonex-openimmo2wp/_split_element.
     *
     * @param string $element Node/value + attribute/value combination.
     * @return array Splitted XML element parts.
     */
    private function splitElement(string $element): array
    {
        $parts = [
            'node' => $element,
            'attribute' => false,
            'attribute_value' => false,
            'attribute_compare' => '=',
            'node_value_is' => false,
        ];

        if (str_contains($element, ':')) {
            $temp = explode(':', $element);
            $parts['node'] = $temp[0];
            $parts['attribute'] = $temp[1];
            if (isset($temp[2])) {
                $parts['attribute_value'] = $temp[2];
            }
        }

        if (str_contains($parts['node'], '=')) {
            $temp = explode('=', $parts['node']);
            $parts['node'] = $temp[0];
            $parts['node_value_is'] = trim($temp[1]);
        }

        return $parts;
    }

    private function buildXPathQuery(\SimpleXMLElement $xml, array $element_split): string|false
    {
        $xml_namespaces = $xml->getDocNamespaces();
        $ns_prefix = '';
        if (isset($xml_namespaces['']) && $xml_namespaces['']) {
            $ns_prefix = 'oi:';
            $xml->registerXPathNamespace('oi', $xml_namespaces['']);
        }

        $path_parts = explode('->', $element_split['node']);
        $xpath = './/' . $ns_prefix . implode('/' . $ns_prefix, $path_parts);

        $xqueries = [];

        if ($element_split['node_value_is'] !== false) {
            $xqueries[] = "text()='" . $element_split['node_value_is'] . "'";
        }

        if ($element_split['attribute']) {
            if ($element_split['attribute_value'] !== false) {
                $xqueries[] = '@' . $element_split['attribute'] . "='" . $element_split['attribute_value'] . "'";
            } else {
                $xqueries[] = '@' . $element_split['attribute'];
            }
        }

        if (!empty($xqueries)) {
            $xpath .= '[' . implode(' and ', $xqueries) . ']';
        }

        return $xpath;
    }
}
