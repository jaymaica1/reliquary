<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LogFormatterExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_log', [$this, 'formatLog'], ['is_safe' => ['html']]),
        ];
    }

    public function formatLog(string $logContent): string
    {
        $lines = explode("\n", $logContent);
        // Remove the last empty line if it exists (usually due to the final newline in the file)
        if (!empty($lines) && empty(trim(end($lines)))) {
            array_pop($lines);
        }
        
        // Reverse lines to show last first
        $lines = array_reverse($lines);
        
        $formattedLines = [];

        foreach ($lines as $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                $formattedLines[] = $line;
                continue;
            }

            // Check if the line contains JSON
            if ($this->isJson($line)) {
                $formattedLines[] = $this->formatJsonLine($line);
            } else {
                $formattedLines[] = htmlspecialchars($line);
            }
        }

        return implode("\n", $formattedLines);
    }

    private function isJson(string $string): bool
    {
        // Simple check for JSON format - starts with { and ends with }
        $string = trim($string);
        return (str_starts_with($string, '{') && str_ends_with($string, '}'));
    }

    private function formatJsonLine(string $line): string
    {
        $data = json_decode($line, true);
        
        // If JSON parsing failed, return the original line
        if (json_last_error() !== JSON_ERROR_NONE) {
            return htmlspecialchars($line);
        }

        $html = '<div class="json-log">';
        
        // Format the message with special highlighting
        if (isset($data['message'])) {
            $html .= $this->formatMessage($data['message']);
        }
        
        // Format the context with special highlighting for exception details
        if (isset($data['context'])) {
            $html .= $this->formatContext($data['context']);
        }
        
        // Format other fields
        foreach ($data as $key => $value) {
            if ($key !== 'message' && $key !== 'context') {
                $html .= $this->formatKeyValue($key, $value);
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }

    private function formatMessage(string $message): string
    {
        // Extract the exception message if it exists
        if (preg_match('/Uncaught PHP Exception (.*?): "(.*?)" at (.*?) line (\d+)/', $message, $matches)) {
            $exceptionClass = $matches[1];
            $exceptionMessage = $matches[2];
            $file = $matches[3];
            $line = $matches[4];
            
            return sprintf(
                '<div class="log-message"><span class="log-label">Exception:</span> <span class="log-exception-class">%s</span><br>' .
                '<span class="log-label">Message:</span> <span class="log-exception-message">%s</span><br>' .
                '<span class="log-label">Location:</span> <span class="log-file">%s</span> line <span class="log-line">%s</span></div>',
                htmlspecialchars($exceptionClass),
                htmlspecialchars($exceptionMessage),
                htmlspecialchars($file),
                htmlspecialchars($line)
            );
        }
        
        return sprintf('<div class="log-message">%s</div>', htmlspecialchars($message));
    }

    private function formatContext(array $context): string
    {
        $html = '<div class="log-context">';
        $html .= '<span class="log-section-title">Context:</span>';
        
        // Special handling for exception context
        if (isset($context['exception'])) {
            $exception = $context['exception'];
            $html .= '<div class="log-exception">';
            
            if (isset($exception['class'])) {
                $html .= sprintf('<div><span class="log-key">Class:</span> <span class="log-value">%s</span></div>', 
                    htmlspecialchars($exception['class']));
            }
            
            if (isset($exception['message'])) {
                $html .= sprintf('<div><span class="log-key">Message:</span> <span class="log-value">%s</span></div>', 
                    htmlspecialchars($exception['message']));
            }
            
            if (isset($exception['code'])) {
                $html .= sprintf('<div><span class="log-key">Code:</span> <span class="log-value">%s</span></div>', 
                    htmlspecialchars((string)$exception['code']));
            }
            
            if (isset($exception['file'])) {
                $html .= sprintf('<div><span class="log-key">File:</span> <span class="log-value">%s</span></div>', 
                    htmlspecialchars($exception['file']));
            }
            
            $html .= '</div>';
        } else {
            // Format other context data
            foreach ($context as $key => $value) {
                $html .= $this->formatKeyValue($key, $value, 1);
            }
        }
        
        $html .= '</div>';
        return $html;
    }

    private function formatKeyValue(string $key, $value, int $depth = 0): string
    {
        $indent = str_repeat('&nbsp;&nbsp;', $depth);
        
        if (is_array($value)) {
            $html = sprintf('<div>%s<span class="log-key">%s:</span></div>', $indent, htmlspecialchars($key));
            foreach ($value as $subKey => $subValue) {
                $html .= $this->formatKeyValue($subKey, $subValue, $depth + 1);
            }
            return $html;
        } else {
            $formattedValue = is_string($value) ? htmlspecialchars($value) : htmlspecialchars(json_encode($value));
            return sprintf('<div>%s<span class="log-key">%s:</span> <span class="log-value">%s</span></div>', 
                $indent, htmlspecialchars($key), $formattedValue);
        }
    }
}