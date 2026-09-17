<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rule based validator.
 *
 * Rules are declared as 'field' => 'required|string|max:120'. Every controller
 * that writes data validates first; nothing reaches a repository unvalidated.
 */
final class Validator
{
    /** @var array<string,array<int,string>> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];
    /** @var array<string,string> */
    private array $labels = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels  field => human readable name
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        array $labels = []
    ) {
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->validate();
        return $validator;
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = Arr::get($this->data, $field);
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($required && $isEmpty) {
                $this->addError($field, 'required');
                continue;
            }
            if ($isEmpty && ($nullable || !$required)) {
                if (array_key_exists($field, $this->data) || str_contains($field, '.')) {
                    $this->validated[$field] = $value === '' ? null : $value;
                }
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                if (!$this->applyRule($field, (string) $name, $parameter, $value)) {
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    private function applyRule(string $field, string $rule, ?string $parameter, mixed $value): bool
    {
        switch ($rule) {
            case 'string':
                if (!is_string($value)) {
                    return $this->fail($field, 'string');
                }
                break;

            case 'integer':
            case 'int':
                if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
                    if (!is_int($value)) {
                        return $this->fail($field, 'integer');
                    }
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->fail($field, 'numeric');
                }
                break;

            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'yes', 'no'], true)) {
                    return $this->fail($field, 'boolean');
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return $this->fail($field, 'array');
                }
                break;

            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    return $this->fail($field, 'email');
                }
                if (strlen((string) $value) > 190) {
                    return $this->fail($field, 'max', '190');
                }
                break;

            case 'url':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    return $this->fail($field, 'url');
                }
                if (!preg_match('#^https?://#i', (string) $value)) {
                    return $this->fail($field, 'url');
                }
                break;

            case 'phone':
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';
                if (strlen($digits) < 10 || strlen($digits) > 15) {
                    return $this->fail($field, 'phone');
                }
                break;

            case 'date':
                if (!$this->isValidDate((string) $value, ['Y-m-d', 'd-m-Y', 'd/m/Y'])) {
                    return $this->fail($field, 'date');
                }
                break;

            case 'time':
                if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
                    return $this->fail($field, 'time');
                }
                break;

            case 'datetime':
                if (!$this->isValidDate((string) $value, ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i'])) {
                    return $this->fail($field, 'datetime');
                }
                break;

            case 'after_today':
                $timestamp = strtotime((string) $value);
                if ($timestamp === false || $timestamp < strtotime('today')) {
                    return $this->fail($field, 'after_today');
                }
                break;

            case 'min':
                $min = (float) $parameter;
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value < $min) {
                        return $this->fail($field, 'min_numeric', $parameter);
                    }
                } elseif (is_array($value)) {
                    if (count($value) < $min) {
                        return $this->fail($field, 'min_array', $parameter);
                    }
                } elseif (mb_strlen((string) $value) < $min) {
                    return $this->fail($field, 'min_string', $parameter);
                }
                break;

            case 'max':
                $max = (float) $parameter;
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value > $max) {
                        return $this->fail($field, 'max_numeric', $parameter);
                    }
                } elseif (is_array($value)) {
                    if (count($value) > $max) {
                        return $this->fail($field, 'max_array', $parameter);
                    }
                } elseif (mb_strlen((string) $value) > $max) {
                    return $this->fail($field, 'max_string', $parameter);
                }
                break;

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $parameter), 2, '0');
                $length = is_numeric($value) ? (float) $value : mb_strlen((string) $value);
                if ($length < (float) $low || $length > (float) $high) {
                    return $this->fail($field, 'between', $parameter);
                }
                break;

            case 'in':
                $options = explode(',', (string) $parameter);
                if (!in_array((string) $value, $options, true)) {
                    return $this->fail($field, 'in', $parameter);
                }
                break;

            case 'not_in':
                if (in_array((string) $value, explode(',', (string) $parameter), true)) {
                    return $this->fail($field, 'not_in', $parameter);
                }
                break;

            case 'regex':
                if (!is_string($value) || @preg_match((string) $parameter, $value) !== 1) {
                    return $this->fail($field, 'regex');
                }
                break;

            case 'slug':
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                    return $this->fail($field, 'slug');
                }
                break;

            case 'alpha_dash':
                if (!preg_match('/^[A-Za-z0-9_\-]+$/', (string) $value)) {
                    return $this->fail($field, 'alpha_dash');
                }
                break;

            case 'hex_color':
                if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', (string) $value)) {
                    return $this->fail($field, 'hex_color');
                }
                break;

            case 'confirmed':
                $other = Arr::get($this->data, $field . '_confirmation');
                if (!is_string($other) || !hash_equals((string) $value, $other)) {
                    return $this->fail($field, 'confirmed');
                }
                break;

            case 'same':
                if ((string) Arr::get($this->data, (string) $parameter) !== (string) $value) {
                    return $this->fail($field, 'same', $parameter);
                }
                break;

            case 'different':
                if ((string) Arr::get($this->data, (string) $parameter) === (string) $value) {
                    return $this->fail($field, 'different', $parameter);
                }
                break;

            case 'password':
                $minLength = (int) Config::get('security.password_min_length', 8);
                if (mb_strlen((string) $value) < $minLength) {
                    return $this->fail($field, 'password_short', (string) $minLength);
                }
                if (!preg_match('/[A-Za-z]/', (string) $value) || !preg_match('/\d/', (string) $value)) {
                    return $this->fail($field, 'password_weak');
                }
                break;

            case 'unique':
                // unique:table,column[,ignoreId[,idColumn]]
                $parts = explode(',', (string) $parameter);
                $table = $parts[0] ?? '';
                $column = $parts[1] ?? $field;
                $ignore = $parts[2] ?? null;
                $idColumn = $parts[3] ?? 'id';
                if ($table !== '' && $this->existsInDatabase($table, $column, $value, $ignore, $idColumn)) {
                    return $this->fail($field, 'unique');
                }
                break;

            case 'exists':
                $parts = explode(',', (string) $parameter);
                $table = $parts[0] ?? '';
                $column = $parts[1] ?? 'id';
                if ($table !== '' && !$this->existsInDatabase($table, $column, $value, null, 'id')) {
                    return $this->fail($field, 'exists');
                }
                break;

            case 'json':
                if (!is_string($value) || json_decode($value) === null) {
                    return $this->fail($field, 'json');
                }
                break;

            case 'no_html':
                if (is_string($value) && $value !== strip_tags($value)) {
                    return $this->fail($field, 'no_html');
                }
                break;

            case 'safe_text':
                // Rejects the obvious script/handler injection attempts in text
                // that will be rendered inside an invitation.
                if (is_string($value) && preg_match('/<\s*(script|iframe|object|embed|link|meta)\b|javascript\s*:|on\w+\s*=/i', $value)) {
                    return $this->fail($field, 'safe_text');
                }
                break;

            case 'locale':
                $locales = array_keys((array) Config::get('app.locales', ['en' => 'English']));
                if (!in_array((string) $value, $locales, true)) {
                    return $this->fail($field, 'in', implode(',', $locales));
                }
                break;
        }

        return true;
    }

    private function isValidDate(string $value, array $formats): bool
    {
        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return true;
            }
        }
        return strtotime($value) !== false;
    }

    private function existsInDatabase(string $table, string $column, mixed $value, ?string $ignore, string $idColumn): bool
    {
        try {
            $db = Database::instance();
            if (!$db->tableExists($table)) {
                return false;
            }
            $sql = 'SELECT COUNT(*) FROM ' . $db->wrap($db->table($table))
                . ' WHERE ' . $db->wrap($column) . ' = :value';
            $bindings = ['value' => $value];
            if ($ignore !== null && $ignore !== '') {
                $sql .= ' AND ' . $db->wrap($idColumn) . ' <> :ignore';
                $bindings['ignore'] = $ignore;
            }
            return (int) $db->value($sql, $bindings, 0) > 0;
        } catch (\Throwable $e) {
            Logger::warning('Validator database check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function fail(string $field, string $key, ?string $parameter = null): bool
    {
        $this->addError($field, $key, $parameter);
        return false;
    }

    private function addError(string $field, string $key, ?string $parameter = null): void
    {
        $this->errors[$field][] = $this->message($field, $key, $parameter);
    }

    private function message(string $field, string $key, ?string $parameter): string
    {
        $label = $this->labels[$field] ?? ucwords(str_replace(['_', '.'], ' ', $field));
        $messages = [
            'required'       => '%s is required.',
            'string'         => '%s must be text.',
            'integer'        => '%s must be a whole number.',
            'numeric'        => '%s must be a number.',
            'boolean'        => '%s must be true or false.',
            'array'          => '%s must be a list.',
            'email'          => 'Please enter a valid email address.',
            'url'            => '%s must be a valid http(s) URL.',
            'phone'          => '%s must be a valid phone number.',
            'date'           => '%s must be a valid date.',
            'time'           => '%s must be a valid time (HH:MM).',
            'datetime'       => '%s must be a valid date and time.',
            'after_today'    => '%s cannot be in the past.',
            'min_string'     => '%s must be at least ' . $parameter . ' characters.',
            'min_numeric'    => '%s must be at least ' . $parameter . '.',
            'min_array'      => 'Please select at least ' . $parameter . ' item(s) for %s.',
            'max_string'     => '%s may not be longer than ' . $parameter . ' characters.',
            'max_numeric'    => '%s may not be greater than ' . $parameter . '.',
            'max_array'      => '%s may not have more than ' . $parameter . ' items.',
            'between'        => '%s must be between ' . str_replace(',', ' and ', (string) $parameter) . '.',
            'in'             => '%s must be one of: ' . str_replace(',', ', ', (string) $parameter) . '.',
            'not_in'         => 'That value is not allowed for %s.',
            'regex'          => '%s has an invalid format.',
            'slug'           => '%s may only contain lowercase letters, numbers and hyphens.',
            'alpha_dash'     => '%s may only contain letters, numbers, dashes and underscores.',
            'hex_color'      => '%s must be a hex colour such as #C8102E.',
            'confirmed'      => '%s confirmation does not match.',
            'same'           => '%s must match ' . $parameter . '.',
            'different'      => '%s must be different from ' . $parameter . '.',
            'password_short' => 'Password must be at least ' . $parameter . ' characters.',
            'password_weak'  => 'Password must contain at least one letter and one number.',
            'unique'         => 'That %s is already taken.',
            'exists'         => 'The selected %s does not exist.',
            'json'           => '%s must be valid JSON.',
            'no_html'        => '%s may not contain HTML.',
            'safe_text'      => '%s contains characters that are not allowed.',
        ];
        $template = $messages[$key] ?? '%s is invalid.';
        return sprintf($template, $label);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,string> first error per field */
    public function firstErrors(): array
    {
        $out = [];
        foreach ($this->errors as $field => $messages) {
            $out[$field] = (string) reset($messages);
        }
        return $out;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return (string) reset($messages);
        }
        return null;
    }

    /** @return array<string,mixed> only the fields that had rules and passed */
    public function validated(): array
    {
        return $this->validated;
    }

    public function addCustomError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
